<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class PurchaseReplyPdfAutofillService
{
    /** @var string[] */
    private array $stopwords = [
        'de', 'del', 'la', 'las', 'el', 'los', 'y', 'con', 'para', 'por',
        'en', 'kg', 'kgs', 'un', 'und', 'unidad', 'x', 'a', 'al',
    ];

    /** @var array<string, string> */
    private array $unitMap = [
        'KG' => 'KG',
        'KGS' => 'KG',
        'UN' => 'UN',
        'UND' => 'UN',
        'UNID' => 'UN',
        'UNIDAD' => 'UN',
        'CAJA' => 'CJ',
        'CAJAS' => 'CJ',
        'CJ' => 'CJ',
        'L' => 'L',
        'LT' => 'L',
        'LTS' => 'L',
        'M' => 'M',
        'MT' => 'M',
        'MTS' => 'M',
    ];

    public function autofillFromStoredAttachment(
        ConnectionInterface $db,
        object $order,
        int $replyId,
        ?string $storedPath
    ): array {
        if (!$storedPath) {
            return ['ok' => false, 'message' => 'La respuesta no tiene adjunto.'];
        }

        $ext = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return ['ok' => false, 'message' => 'El autocompletado solo aplica para PDF.'];
        }

        if (!Storage::disk('public')->exists($storedPath)) {
            return ['ok' => false, 'message' => 'No se encontró el archivo PDF en storage.'];
        }

        $pdfPath = Storage::disk('public')->path($storedPath);
        $text = $this->extractTextFromPdf($pdfPath);
        $lines = $this->toLines($text);

        if (count($lines) === 0) {
            return ['ok' => false, 'message' => 'No se pudo extraer texto del PDF (posible escaneo/imágen).'];
        }

        $items = $db->table('purchase_order_items')
            ->where('purchase_order_id', $order->id)
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return ['ok' => false, 'message' => 'La cotización no tiene ítems para comparar.'];
        }

        [$rows, $matchedCount] = $this->buildRowsFromLines($items, $lines, $replyId);

        if (empty($rows)) {
            return ['ok' => false, 'message' => 'No se encontraron coincidencias confiables en el PDF.'];
        }

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) ($row['line_total_quoted'] ?? 0);
        }

        $db->transaction(function () use ($db, $replyId, $rows, $total) {
            $db->table('purchase_order_reply_items')->where('reply_id', $replyId)->delete();
            $db->table('purchase_order_reply_items')->insert($rows);

            $db->table('purchase_order_replies')
                ->where('id', $replyId)
                ->update([
                    'total_quoted' => round($total, 2),
                    'updated_at' => now(),
                ]);
        });

        return [
            'ok' => true,
            'message' => "Autocompletado OK: {$matchedCount} ítem(s) detectado(s).",
            'matched' => $matchedCount,
            'total' => round($total, 2),
        ];
    }

    private function extractTextFromPdf(string $pdfPath): string
    {
        $process = new Process(['pdftotext', '-layout', $pdfPath, '-']);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            return '';
        }

        return (string) $process->getOutput();
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function buildRowsFromLines(Collection $items, array $lines, int $replyId): array
    {
        $rows = [];
        $matchedCount = 0;
        $lineNorm = array_map(fn($line) => $this->normalizeText($line), $lines);

        foreach ($items as $item) {
            $itemName = (string) $item->product_name;
            $itemNorm = $this->normalizeText($itemName);
            $tokens = $this->buildTokens($itemNorm);

            [$bestIndex, $bestScore] = $this->findBestLineIndex($tokens, $itemNorm, $lineNorm);
            if ($bestIndex === null || $bestScore < 0.38) {
                continue;
            }

            $block = $this->lineBlock($lines, $bestIndex, 1);
            $qtyExpected = (float) $item->quantity;

            $detectedQty = $this->detectQuantity($block, $qtyExpected);
            $finalQty = $detectedQty ?? $qtyExpected;
            if ($finalQty <= 0) {
                $finalQty = $qtyExpected > 0 ? $qtyExpected : 1.0;
            }

            $price = $this->detectUnitPrice($block, $finalQty);
            if ($price === null || $price <= 0) {
                continue;
            }

            $unitDetected = $this->detectUnit($block);
            $finalUnit = $unitDetected ?: ((string) $item->unit ?: 'UN');

            $rows[] = [
                'reply_id' => $replyId,
                'purchase_order_item_id' => $item->id,
                'product_name' => $itemName,
                'unit' => mb_substr($finalUnit, 0, 30),
                'quantity' => round($finalQty, 4),
                'unit_price_quoted' => round($price, 4),
                'line_total_quoted' => round($finalQty * $price, 2),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $matchedCount++;
        }

        return [$rows, $matchedCount];
    }

    /**
     * @return array{0: int|null, 1: float}
     */
    private function findBestLineIndex(array $tokens, string $itemNorm, array $lineNorm): array
    {
        $bestIndex = null;
        $bestScore = 0.0;

        foreach ($lineNorm as $idx => $line) {
            if ($line === '') {
                continue;
            }

            $tokenHits = 0;
            foreach ($tokens as $t) {
                if (str_contains($line, $t)) {
                    $tokenHits++;
                }
            }
            if ($tokenHits === 0) {
                continue;
            }

            similar_text($itemNorm, $line, $pct);
            $ratioToken = $tokenHits / max(1, count($tokens));
            $ratioText = ((float) $pct) / 100.0;
            $score = ($ratioToken * 0.72) + ($ratioText * 0.28);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $idx;
            }
        }

        return [$bestIndex, $bestScore];
    }

    private function lineBlock(array $lines, int $index, int $radius = 1): string
    {
        $from = max(0, $index - $radius);
        $to = min(count($lines) - 1, $index + $radius);
        $slice = array_slice($lines, $from, $to - $from + 1);
        return implode(' ', $slice);
    }

    private function detectUnit(string $text): ?string
    {
        if (!preg_match('/\b(KGS?|KG|UNID(?:AD)?|UND|UN|CAJAS?|CJ|LTS?|LT|L|MTS?|MT|M)\b/ui', $text, $m)) {
            return null;
        }
        $k = strtoupper(trim((string) $m[1]));
        return $this->unitMap[$k] ?? $k;
    }

    private function detectQuantity(string $text, float $expected): ?float
    {
        $nums = $this->extractNumbers($text);
        if (empty($nums)) {
            return null;
        }

        $best = null;
        $bestDist = INF;

        foreach ($nums as $n) {
            if ($n <= 0) {
                continue;
            }
            $dist = abs($n - $expected);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $n;
            }
        }

        if ($best === null) {
            return null;
        }

        $tol = max(1.0, abs($expected) * 0.2);
        if ($bestDist <= $tol) {
            return $best;
        }

        return null;
    }

    private function detectUnitPrice(string $text, float $quantity): ?float
    {
        $currencyNums = [];
        if (preg_match_all('/(?:\$|clp|usd|eur)\s*([0-9][0-9\.\,\s]*)/iu', $text, $m) && !empty($m[1])) {
            foreach ($m[1] as $raw) {
                $n = $this->parseNumber((string) $raw);
                if ($n !== null && $n > 0) {
                    $currencyNums[] = $n;
                }
            }
        }
        if (!empty($currencyNums)) {
            return min($currencyNums);
        }

        $nums = array_values(array_filter($this->extractNumbers($text), fn($n) => $n > 0));
        if (empty($nums)) {
            return null;
        }

        $qtyTol = max(0.1, $quantity * 0.1);
        $candidates = array_values(array_filter($nums, fn($n) => abs($n - $quantity) > $qtyTol));
        if (empty($candidates)) {
            return null;
        }

        if ($quantity > 0) {
            foreach ($candidates as $c) {
                $possibleTotal = $c * $quantity;
                foreach ($candidates as $other) {
                    if ($other <= $c) {
                        continue;
                    }
                    $diff = abs($other - $possibleTotal);
                    if ($diff <= max(2.0, $possibleTotal * 0.12)) {
                        return $c;
                    }
                }
            }
        }

        sort($candidates);
        return $candidates[0] ?? null;
    }

    /**
     * @return float[]
     */
    private function extractNumbers(string $text): array
    {
        preg_match_all('/\d{1,3}(?:[.\s]\d{3})*(?:,\d{1,4})|\d+(?:[.,]\d{1,4})?/', $text, $m);
        $nums = [];
        foreach ($m[0] ?? [] as $raw) {
            $n = $this->parseNumber((string) $raw);
            if ($n !== null) {
                $nums[] = $n;
            }
        }
        return $nums;
    }

    private function parseNumber(string $raw): ?float
    {
        $raw = trim(str_replace(' ', '', $raw));
        if ($raw === '') {
            return null;
        }

        $comma = str_contains($raw, ',');
        $dot = str_contains($raw, '.');

        if ($comma && $dot) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
            return is_numeric($raw) ? (float) $raw : null;
        }

        if ($comma) {
            $parts = explode(',', $raw);
            $last = end($parts);
            if ($last !== false && strlen((string) $last) <= 3) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
            return is_numeric($raw) ? (float) $raw : null;
        }

        if ($dot) {
            $parts = explode('.', $raw);
            $last = end($parts);
            if ($last !== false && strlen((string) $last) > 3) {
                $raw = str_replace('.', '', $raw);
            }
            return is_numeric($raw) ? (float) $raw : null;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    /**
     * @return string[]
     */
    private function toLines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $out = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * @return string[]
     */
    private function buildTokens(string $normalized): array
    {
        $parts = array_values(array_filter(explode(' ', $normalized), function (string $part): bool {
            if (strlen($part) < 2) {
                return false;
            }
            return !in_array($part, $this->stopwords, true);
        }));

        if (empty($parts)) {
            return array_values(array_filter(explode(' ', $normalized)));
        }

        return $parts;
    }
}

