<?php

namespace App\Services\SupplierInvoices;

use App\Models\SupplierInvoiceUpload;
use App\Services\PurchaseRequests\Odoo\OdooClient;
use App\Support\Rut;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Lleva a Odoo una factura subida a mano, como borrador de factura de proveedor.
 *
 * Primero la revisa —{@see revisar()}— y sólo si todo cuadra la crea —
 * {@see crear()}—. Se arma igual que las que ya están en Odoo: diario de
 * facturas de proveedor, tipo (33) o (34), el folio como número de documento,
 * una línea por renglón con el IVA 19% y el PDF adjunto. La cuenta y la
 * analítica se copian de la última factura confirmada del mismo proveedor; sin ella,
 * quedan las que Odoo ponga por defecto.
 *
 * Nunca la confirma: eso lo hace Contabilidad en Odoo.
 */
class InvoiceToOdoo
{
    private ?OdooClient $client = null;

    public function isEnabled(): bool
    {
        return (bool) config('supplier_invoices.odoo.enabled')
            && filled(config('supplier_invoices.odoo.url')) && filled(config('supplier_invoices.odoo.db'));
    }

    public function url(): string
    {
        return rtrim((string) config('supplier_invoices.odoo.url'), '/');
    }

    /** A qué Odoo va: lo dice la pantalla, para que nadie cree en producción creyendo que prueba. */
    public function destino(): string
    {
        return (string) parse_url((string) config('supplier_invoices.odoo.url'), PHP_URL_HOST);
    }

    /**
     * Lo que se comprueba antes de crear.
     *
     * @return array{
     *     revisiones: list<array{ok: bool, bloquea: bool, texto: string}>,
     *     proveedor: ?array{id: int, name: string},
     *     duplicada: ?array{id: int, name: string, state: string},
     *     cuenta: ?array{account_id: ?int, account: ?string, analytic: ?array, desde: string},
     *     se_puede: bool,
     *     error: ?string,
     * }
     */
    public function revisar(SupplierInvoiceUpload $factura): array
    {
        $revisiones = $this->revisionesLocales($factura);
        $proveedor = $duplicada = $cuenta = null;
        $error = null;

        if (! $this->isEnabled()) {
            $error = 'La conexión con Odoo para facturas está apagada en este entorno.';
        } elseif (($rut = Rut::normalize((string) $factura->dato('rut'))) !== null) {
            try {
                $proveedor = $this->proveedor($rut);
                $revisiones[] = $proveedor === null
                    ? ['ok' => false, 'bloquea' => true, 'texto' => sprintf('El RUT %s no está en Odoo. Créalo allá como proveedor y vuelve aquí.', Rut::format($rut))]
                    : ['ok' => true, 'bloquea' => false, 'texto' => 'Proveedor en Odoo: '.$proveedor['name'].'.'];

                if ($proveedor !== null && filled($factura->dato('folio'))) {
                    $duplicada = $this->yaEsta($proveedor['id'], (string) $factura->dato('folio'), (string) $factura->dato('tipo'));
                    $revisiones[] = $duplicada === null
                        ? ['ok' => true, 'bloquea' => false, 'texto' => 'No está en Odoo todavía.']
                        : ['ok' => false, 'bloquea' => true, 'texto' => sprintf('Ya está en Odoo: %s (%s). No se crea dos veces.', $duplicada['name'], $duplicada['state'])];
                    $cuenta = $proveedor === null ? null : $this->cuentaDeSuUltimaFactura($proveedor['id']);
                }
            } catch (\Throwable $e) {
                $error = 'Odoo no respondió: '.$e->getMessage();
            }
        }

        // Sin RUT o sin folio no se puede saber si es la misma; sí se puede
        // mostrar las que tienen su total y su fecha.
        $posibles = [];

        if ($error === null && $this->isEnabled() && $duplicada === null && (Rut::normalize((string) $factura->dato('rut')) === null || blank($factura->dato('folio')))) {
            try {
                $posibles = $this->posibles($factura);
            } catch (\Throwable) {
            }
        }

        return [
            'revisiones' => $revisiones,
            'proveedor' => $proveedor,
            'duplicada' => $duplicada,
            'posibles' => $posibles,
            'cuenta' => $cuenta,
            'se_puede' => $error === null && $proveedor !== null && $duplicada === null
                && ! collect($revisiones)->contains(fn (array $r): bool => ! $r['ok'] && $r['bloquea']),
            'error' => $error,
        ];
    }

    /**
     * Las facturas de Odoo con el mismo total y una fecha cercana.
     *
     * Para cuando no se pudo leer el RUT o el folio: la 286429 de Carlos
     * Rodríguez Soto llegó en una foto con el comprobante de Transbank tapando
     * el encabezado, y sólo se leían la fecha, el remache y los $120.000. Con
     * eso basta para preguntar «¿no será ésta?».
     *
     * @return list<array{id: int, name: string, state: string, partner: string, fecha: ?string}>
     */
    public function posibles(SupplierInvoiceUpload $factura): array
    {
        $total = (float) $factura->dato('total');

        if (! $this->isEnabled() || $total <= 0) {
            return [];
        }

        // Con fecha, las cercanas a ella o las de los últimos cuatro meses: en
        // esa misma foto el papel cortaba el año y la IA leyó 2021.
        $fecha = filled($factura->dato('fecha')) ? \Illuminate\Support\Carbon::parse($factura->dato('fecha')) : null;
        $recientes = [['invoice_date', '>=', now()->subMonths(4)->toDateString()]];
        $cuando = $fecha === null ? $recientes : ['|', '&',
            ['invoice_date', '>=', $fecha->copy()->subDays(7)->toDateString()],
            ['invoice_date', '<=', $fecha->copy()->addDays(7)->toDateString()],
            ...$recientes,
        ];

        return array_map(fn (array $m): array => [
            'id' => (int) $m['id'],
            'name' => (string) ($m['name'] ?: 'borrador #'.$m['id']),
            'state' => (string) $m['state'],
            'partner' => is_array($m['partner_id'] ?? null) ? (string) $m['partner_id'][1] : '',
            'fecha' => ($m['invoice_date'] ?? null) ?: null,
        ], $this->client()->execute('account.move', 'search_read', [[
            ['move_type', '=', 'in_invoice'],
            ['state', '!=', 'cancel'],
            ['amount_total', '>=', $total - 1],
            ['amount_total', '<=', $total + 1],
            ...$cuando,
        ]], ['fields' => ['id', 'name', 'state', 'partner_id', 'invoice_date'], 'limit' => 5, 'order' => 'invoice_date desc']));
    }

    /**
     * Crea el borrador. Vuelve a revisar justo antes: entre la pantalla y el
     * botón alguien pudo crearla en Odoo a mano.
     *
     * @return array{id: int, name: string, total: float}
     */
    public function crear(SupplierInvoiceUpload $factura): array
    {
        $revision = $this->revisar($factura);

        if (! $revision['se_puede']) {
            throw new RuntimeException($revision['error']
                ?? collect($revision['revisiones'])->first(fn (array $r): bool => ! $r['ok'] && $r['bloquea'])['texto']
                ?? 'La factura no está lista para Odoo.');
        }

        $exenta = $factura->dato('tipo') === '34';
        $iva = $exenta ? null : $this->ivaDeCompra();
        $cuenta = $revision['cuenta'];

        $lineas = array_map(fn (array $l): array => [0, 0, array_filter([
            'name' => (string) $l['detalle'],
            'quantity' => (float) ($l['cantidad'] ?? 1),
            'price_unit' => $this->precioNeto($factura, $l),
            'tax_ids' => [[6, 0, $iva === null ? [] : [$iva]]],
            'account_id' => $cuenta['account_id'] ?? null,
            'analytic_distribution' => $cuenta['analytic'] ?? null,
        ], fn ($v) => $v !== null)], $factura->lineas());

        // Lo que no cuadra por redondeo va en una línea propia, como hizo
        // Contabilidad en la FAC 000606 de CDTEC: «Ajuste redondeo … −1».
        // Sin ella, Odoo suma las líneas y el total queda un peso corrido.
        if (($ajuste = $this->ajusteDeRedondeo($factura)) !== 0.0) {
            $lineas[] = [0, 0, array_filter([
                'name' => sprintf('Ajuste redondeo: neto factura $%s', number_format($this->base($factura), 0, ',', '.')),
                'quantity' => 1.0,
                'price_unit' => $ajuste,
                'tax_ids' => [[6, 0, $iva === null ? [] : [$iva]]],
                'account_id' => $cuenta['account_id'] ?? null,
                'analytic_distribution' => $cuenta['analytic'] ?? null,
            ], fn ($v) => $v !== null)];
        }

        $id = (int) $this->client()->execute('account.move', 'create', [[
            'move_type' => 'in_invoice',
            'partner_id' => $revision['proveedor']['id'],
            'journal_id' => $this->diario(),
            'invoice_date' => (string) $factura->dato('fecha'),
            // Sin esto Odoo la deja venciendo hoy, el día en que se creó.
            'invoice_date_due' => (string) ($factura->dato('vencimiento') ?: $factura->dato('fecha')),
            'l10n_latam_document_type_id' => $this->tipoDeDocumento((string) $factura->dato('tipo')),
            'l10n_latam_document_number' => (string) $factura->dato('folio'),
            'invoice_line_ids' => $lineas,
        ]]);

        // El documento, a la vista en la factura de Odoo: adjunto y principal,
        // que es el que Odoo muestra al lado.
        $adjunto = (int) $this->client()->execute('ir.attachment', 'create', [[
            'name' => $factura->original_name,
            'datas' => base64_encode((string) Storage::disk($factura->disk)->get($factura->path)),
            'res_model' => 'account.move',
            'res_id' => $id,
            'mimetype' => $factura->mime_type,
        ]]);
        $this->client()->execute('account.move', 'write', [[$id], ['message_main_attachment_id' => $adjunto]]);

        $creada = $this->client()->execute('account.move', 'read', [[$id]], ['fields' => ['name', 'amount_total']])[0] ?? [];

        return ['id' => $id, 'name' => (string) ($creada['name'] ?? ''), 'total' => (float) ($creada['amount_total'] ?? 0)];
    }

    /** @return list<array{ok: bool, bloquea: bool, texto: string}> */
    private function revisionesLocales(SupplierInvoiceUpload $factura): array
    {
        $r = [];
        $peso = fn (?float $v): string => '$'.number_format((float) $v, 0, ',', '.');
        $rut = Rut::normalize((string) $factura->dato('rut'));

        $r[] = $rut !== null
            ? ['ok' => true, 'bloquea' => false, 'texto' => 'RUT '.Rut::format($rut).' válido.']
            : ['ok' => false, 'bloquea' => true, 'texto' => 'Falta el RUT del proveedor, o su dígito verificador no cuadra.'];

        foreach (['folio' => 'el folio', 'fecha' => 'la fecha de emisión'] as $clave => $nombre) {
            if (blank($factura->dato($clave))) {
                $r[] = ['ok' => false, 'bloquea' => true, 'texto' => 'Falta '.$nombre.'.'];
            }
        }

        $neto = (float) $factura->dato('neto');
        $exento = (float) $factura->dato('exento');
        $iva = (float) $factura->dato('iva');
        $total = (float) $factura->dato('total');
        $exenta = $factura->dato('tipo') === '34';

        if ($total <= 0) {
            $r[] = ['ok' => false, 'bloquea' => true, 'texto' => 'Falta el total.'];
        } elseif (abs($neto + $exento + $iva - $total) > 2) {
            $r[] = ['ok' => false, 'bloquea' => true, 'texto' => sprintf('Neto %s + exento %s + IVA %s no da el total %s.', $peso($neto), $peso($exento), $peso($iva), $peso($total))];
        } else {
            $r[] = ['ok' => true, 'bloquea' => false, 'texto' => 'Neto, IVA y total cuadran.'];
        }

        if (! $exenta && $neto > 0 && abs(round($neto * 0.19) - $iva) > 2) {
            $r[] = ['ok' => false, 'bloquea' => true, 'texto' => sprintf('El IVA %s no es el 19%% del neto (%s).', $peso($iva), $peso(round($neto * 0.19)))];
        }

        if ($exenta && $iva > 0) {
            $r[] = ['ok' => false, 'bloquea' => true, 'texto' => 'Una factura exenta no lleva IVA.'];
        }

        $lineas = $factura->lineas();

        if ($lineas === []) {
            $r[] = ['ok' => false, 'bloquea' => true, 'texto' => 'No tiene líneas.'];
        } else {
            $suma = $this->sumaDeLineas($factura);
            $base = $this->base($factura);
            $ajuste = $this->ajusteDeRedondeo($factura);

            $r[] = match (true) {
                $this->preciosConIva($factura) => ['ok' => true, 'bloquea' => false, 'texto' => sprintf('Los precios de las líneas traen el IVA incluido (suman %s, el total): van a Odoo divididos por 1,19, y dan el neto %s.', $peso($this->sumaDeLineas($factura, false)), $peso($base))],
                abs($suma - $base) < 0.5 => ['ok' => true, 'bloquea' => false, 'texto' => sprintf('Las %d líneas suman %s, el neto.', count($lineas), $peso($suma))],
                $ajuste !== 0.0 => ['ok' => true, 'bloquea' => false, 'texto' => sprintf('Las %d líneas suman %s y el neto es %s: va una línea de ajuste de redondeo de %s.', count($lineas), $peso($suma), $peso($base), ($ajuste < 0 ? '−' : '').$peso(abs($ajuste)))],
                default => ['ok' => false, 'bloquea' => true, 'texto' => sprintf('Las líneas suman %s y el neto es %s: falta o sobra algo (un descuento, una línea).', $peso($suma), $peso($base))],
            };
        }

        return $r;
    }

    /** Lo que suman las líneas, ya sin IVA salvo que se pida tal como vienen. */
    private function sumaDeLineas(SupplierInvoiceUpload $factura, bool $neto = true): float
    {
        return array_sum(array_map(
            fn (array $l): float => (float) ($l['cantidad'] ?? 1) * ($neto ? $this->precioNeto($factura, $l) : (float) ($l['precio'] ?? 0)),
            $factura->lineas(),
        ));
    }

    /**
     * ¿La factura escribe el precio unitario con IVA? Pasa en repuestos y
     * ferretería: la 11660 de N.D.F Rodamientos dice 3 correas a $7.450, que
     * suman $22.350, el total, con un neto de $18.782.
     */
    private function preciosConIva(SupplierInvoiceUpload $factura): bool
    {
        $total = (float) $factura->dato('total');
        $tolerancia = max((int) config('supplier_invoices.tolerancia', 5), count($factura->lineas()));
        $tal = $this->sumaDeLineas($factura, false);

        return $factura->dato('tipo') === '33' && (float) $factura->dato('iva') > 0
            && abs($tal - $total) <= $tolerancia && abs($tal - $this->base($factura)) > $tolerancia;
    }

    /**
     * El precio que va a Odoo, siempre sin IVA. Con IVA incluido se divide
     * por 1,19 sin redondear, como lo hace Contabilidad (el remache a 33,613):
     * Odoo calcula el IVA de cada línea sobre ese precio y el total da el del
     * papel, sin ajuste.
     *
     * @param  array<string, mixed>  $linea
     */
    private function precioNeto(SupplierInvoiceUpload $factura, array $linea): float
    {
        $precio = (float) ($linea['precio'] ?? 0);

        return $this->preciosConIva($factura) ? round($precio / 1.19, 6) : $precio;
    }

    /** Lo que Odoo debe sumar en las líneas: el neto más lo exento, sin IVA. */
    private function base(SupplierInvoiceUpload $factura): float
    {
        return $factura->dato('tipo') === '34'
            ? (float) $factura->dato('total')
            : (float) $factura->dato('neto') + (float) $factura->dato('exento');
    }

    /**
     * Los pesos que faltan o sobran entre las líneas y el neto, si son pocos:
     * cantidad × precio con decimales redondea distinto que la factura. Más
     * que la tolerancia ya no es redondeo, y no se ajusta: se revisa.
     */
    private function ajusteDeRedondeo(SupplierInvoiceUpload $factura): float
    {
        $diferencia = round($this->base($factura) - $this->sumaDeLineas($factura));
        $tolerancia = max((int) config('supplier_invoices.tolerancia', 5), count($factura->lineas()));

        return abs($diferencia) >= 1 && abs($diferencia) <= $tolerancia ? (float) $diferencia : 0.0;
    }

    /**
     * La empresa con ese RUT. Un contacto («fbecerra@cooprinsem.cl») trae el
     * RUT de su empresa, así que se sube a la empresa.
     *
     * @return array{id: int, name: string}|null
     */
    private function proveedor(string $rut): ?array
    {
        $socios = $this->client()->execute('res.partner', 'search_read',
            [[['vat', 'in', [$rut, Rut::format($rut), str_replace('-', '', $rut)]]]],
            ['fields' => ['id', 'name', 'commercial_partner_id'], 'limit' => 10]);

        if ($socios === []) {
            return null;
        }

        $empresa = $socios[0]['commercial_partner_id'] ?? false;

        return is_array($empresa)
            ? ['id' => (int) $empresa[0], 'name' => (string) $empresa[1]]
            : ['id' => (int) $socios[0]['id'], 'name' => (string) $socios[0]['name']];
    }

    /**
     * La misma factura, si ya está: del mismo proveedor y con el mismo folio.
     *
     * El folio se compara aquí y no en el dominio: Odoo no filtra por
     * `l10n_latam_document_number` —en el Odoo de pruebas, buscar el 3748
     * devolvió decenas de facturas de otros folios—, y así toda factura de un
     * proveedor con otra en borrador parecía repetida. Sí sirve `name`
     * («FAC 003748»), pero depende del prefijo del tipo; el número no.
     *
     * @return array{id: int, name: string, state: string}|null
     */
    private function yaEsta(int $proveedor, string $folio, string $tipo): ?array
    {
        $sinCeros = ltrim($folio, '0') ?: '0';

        $suyas = $this->client()->execute('account.move', 'search_read', [[
            ['move_type', '=', 'in_invoice'],
            ['commercial_partner_id', '=', $proveedor],
            ['state', '!=', 'cancel'],
        ]], ['fields' => ['id', 'name', 'state', 'l10n_latam_document_number']]);

        foreach ($suyas as $factura) {
            $numero = ltrim((string) ($factura['l10n_latam_document_number'] ?: ''), '0') ?: null;
            // Sin número propio, el que trae el nombre: «FAC 003748».
            $numero ??= preg_match('/(\d+)\s*$/', (string) $factura['name'], $m) ? (ltrim($m[1], '0') ?: '0') : null;

            if ($numero === $sinCeros) {
                return ['id' => (int) $factura['id'], 'name' => (string) ($factura['name'] ?: 'borrador #'.$factura['id']), 'state' => (string) $factura['state']];
            }
        }

        return null;
    }

    /** @return array{account_id: ?int, account: ?string, analytic: ?array, desde: string}|null */
    private function cuentaDeSuUltimaFactura(int $proveedor): ?array
    {
        $linea = $this->client()->execute('account.move.line', 'search_read', [[
            ['move_id.move_type', '=', 'in_invoice'],
            ['move_id.commercial_partner_id', '=', $proveedor],
            // Sólo las ya confirmadas por Contabilidad: un borrador puede
            // traer la cuenta equivocada, y se copiaría en cada factura nueva.
            ['move_id.state', '=', 'posted'],
            ['display_type', '=', 'product'],
        ]], ['fields' => ['account_id', 'analytic_distribution', 'move_id'], 'limit' => 1, 'order' => 'id desc'])[0] ?? null;

        if ($linea === null) {
            return null;
        }

        return [
            'account_id' => is_array($linea['account_id'] ?? null) ? (int) $linea['account_id'][0] : null,
            'account' => is_array($linea['account_id'] ?? null) ? (string) $linea['account_id'][1] : null,
            'analytic' => is_array($linea['analytic_distribution'] ?? null) && $linea['analytic_distribution'] !== [] ? $linea['analytic_distribution'] : null,
            'desde' => is_array($linea['move_id'] ?? null) ? (string) $linea['move_id'][1] : '',
        ];
    }

    private function diario(): int
    {
        $id = $this->client()->execute('account.journal', 'search',
            [[['type', '=', 'purchase'], ['l10n_latam_use_documents', '=', true]]], ['limit' => 1])[0] ?? null;

        return $id ?? throw new RuntimeException('Odoo no tiene un diario de compras con documentos chilenos.');
    }

    private function tipoDeDocumento(string $codigo): int
    {
        $id = $this->client()->execute('l10n_latam.document.type', 'search',
            [[['code', '=', $codigo], ['country_id.code', '=', 'CL']]], ['limit' => 1])[0] ?? null;

        return $id ?? throw new RuntimeException("Odoo no tiene el tipo de documento ($codigo).");
    }

    private function ivaDeCompra(): int
    {
        $id = $this->client()->execute('account.tax', 'search', [[
            ['type_tax_use', '=', 'purchase'], ['amount_type', '=', 'percent'], ['amount', '=', 19],
        ]], ['limit' => 1, 'order' => 'id asc'])[0] ?? null;

        return $id ?? throw new RuntimeException('Odoo no tiene el IVA de compra del 19%.');
    }

    private function client(): OdooClient
    {
        return $this->client ??= new OdooClient(
            (string) config('supplier_invoices.odoo.url'), (string) config('supplier_invoices.odoo.db'),
            (string) config('supplier_invoices.odoo.user'), (string) config('supplier_invoices.odoo.password'),
            (int) config('supplier_invoices.odoo.timeout', 30),
        );
    }
}
