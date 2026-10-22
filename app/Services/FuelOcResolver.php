<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Consulta Odoo mediante search_read; nunca crea ni modifica una OC. */
class FuelOcResolver
{
    public function __construct(private readonly OdooService $odoo) {}

    public static function tanqueEnTexto(string $text): ?int
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '</div>', '<br>', '<br/>'], ' ', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/\bestanque\s*(?:(?:n(?:ro)?\.?|n[°º])\s*)?([12])\b/iu', $text, $matches);
        $numbers = array_values(array_unique($matches[1]));

        return count($numbers) === 1 ? (int) $numbers[0] : null;
    }

    private function rut(?string $value): string
    {
        return preg_replace('/[^0-9K]/', '', strtoupper($value ?? ''));
    }

    /** Una referencia ausente, ambigua o de otro proveedor queda pendiente. */
    public function resolve(array $numbers, int $productId, string $supplierRut, string $buyerRut): bool
    {
        if ($numbers === [] || $this->rut($supplierRut) === '' || $this->rut($buyerRut) === '') {
            return false;
        }
        try {
            $resolved = [];
            foreach (array_unique($numbers) as $number) {
                $orders = $this->odoo->searchRead('purchase.order', [['name', '=', $number], ['state', 'in', ['purchase', 'done']]], ['id', 'name', 'notes', 'order_line', 'partner_id', 'company_id'], ['limit' => 2]);
                if (count($orders) !== 1) {
                    return false;
                }
                $order = $orders[0];
                $partners = $this->odoo->searchRead('res.partner', [['id', '=', $order['partner_id'][0]]], ['commercial_partner_id'], ['limit' => 1]);
                $commercial = $partners[0]['commercial_partner_id'][0] ?? null;
                if (! $commercial) {
                    return false;
                }
                $supplier = $this->odoo->searchRead('res.partner', [['id', '=', $commercial]], ['vat'], ['limit' => 1]);
                $company = $this->odoo->searchRead('res.company', [['id', '=', $order['company_id'][0]]], ['vat'], ['limit' => 1]);
                if ($this->rut($supplier[0]['vat'] ?? null) !== $this->rut($supplierRut) || $this->rut($company[0]['vat'] ?? null) !== $this->rut($buyerRut)) {
                    return false;
                }
                $lines = $this->odoo->searchRead('purchase.order.line', [['id', 'in', $order['order_line']]], ['name'], ['limit' => 200]);
                $text = ($order['notes'] ?: '').' '.implode(' ', array_column($lines, 'name'));
                $numberTank = self::tanqueEnTexto($text);
                if (! $numberTank) {
                    return false;
                }
                $tank = DB::connection('fuelcontrol')->table('fuel_estanques')->where('producto_id', $productId)->where('nombre', 'Estanque '.$numberTank)->first();
                if (! $tank) {
                    return false;
                }
                $resolved[$number] = $tank->id;
            }
            foreach ($resolved as $number => $tankId) {
                DB::connection('fuelcontrol')->table('fuel_oc_destinos')->updateOrInsert(
                    ['numero_oc' => $number],
                    ['estanque_id' => $tankId, 'created_at' => now(), 'updated_at' => now()],
                );
            }

            return true;
        } catch (Throwable $exception) {
            Log::warning('Recepción de diésel pendiente: no se pudo verificar el destino de su OC.', ['ordenes' => $numbers, 'motivo' => $exception->getMessage()]);

            return false;
        }
    }
}
