<?php

namespace App\Services\SupplierInvoices;

use App\Models\SupplierInvoiceUpload;
use App\Services\PurchaseRequests\Odoo\OdooClient;
use App\Support\Rut;
use Illuminate\Support\Str;
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
     * Quién emitió la factura cuando no se leyó su RUT, por coincidencias con
     * lo que ya existe.
     *
     * La 286429 de Carlos Rodríguez Soto llegó con el encabezado tapado por el
     * comprobante de Transbank, pero se leía «Av. Re… 2407» —en Odoo está
     * «AV. RENE SORIANO 2407»—, el código 21RT, que sólo aparece en sus
     * facturas, y $120.000, el total de su FAC 286429.
     *
     * Pesan más los datos de contacto (correo, teléfono, dirección) que los
     * productos, que sólo apoyan. Hace falta un puntaje mínimo y sacarle
     * ventaja al segundo; si no, no se sugiere nadie. Lo que sale es una
     * sugerencia: la confirma una persona.
     *
     * @return array{id: int, name: string, vat: ?string, puntaje: int, debil: bool, razones: list<string>}|null
     */
    public function sugerirProveedor(SupplierInvoiceUpload $factura): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $pistas = (array) $factura->dato('pistas');
        $descartados = array_map('intval', (array) $factura->dato('proveedores_descartados'));
        $puntos = [];
        $razones = [];
        $sumar = function (array $partners, int $peso, string $razon) use (&$puntos, &$razones, $descartados): void {
            foreach (array_unique($partners) as $id) {
                if ($id > 0 && ! in_array($id, $descartados, true)) {
                    $puntos[$id] = ($puntos[$id] ?? 0) + $peso;
                    $razones[$id][] = $razon;
                }
            }
        };
        $empresas = fn (array $socios): array => array_map(
            fn (array $p): int => is_array($p['commercial_partner_id'] ?? null) ? (int) $p['commercial_partner_id'][0] : (int) $p['id'], $socios);
        $buscar = fn (array $dominio): array => $empresas($this->client()->execute('res.partner', 'search_read', [$dominio], ['fields' => ['id', 'commercial_partner_id'], 'limit' => 10]));

        // Correo: el dominio si es propio de la empresa, y si no, los trozos legibles del usuario.
        if (filled($correo = $pistas['correo'] ?? null)) {
            [$usuario, $dominio] = array_pad(explode('@', (string) $correo, 2), 2, '');
            $dominio = preg_replace('/[^a-z0-9.-]/', '', $dominio);
            $trozos = array_filter(preg_split('/[^a-z0-9]+/', $usuario) ?: [], fn (string $t): bool => strlen($t) >= 4);
            $propio = $dominio !== '' && str_contains($dominio, '.') && ! preg_match('/gmail|hotmail|yahoo|outlook|live\./', $dominio);

            foreach ([...($propio ? ['@'.$dominio] : []), ...$trozos] as $trozo) {
                $sumar($buscar([['email', 'ilike', $trozo]]), 3, sprintf('el correo «%s» coincide', $correo));
            }
        }

        // Teléfono: los últimos seis dígitos, que no dependen de cómo se escriba el código de área.
        if (strlen($telefono = (string) ($pistas['telefono'] ?? '')) >= 6) {
            $cola = substr($telefono, -6);
            $sumar($buscar(['|', ['phone', 'ilike', $cola], ['mobile', 'ilike', $cola]]), 3, sprintf('el teléfono %s coincide', $telefono));
        }

        // Dirección: el número de la calle, y si además coincide una palabra, más.
        if (filled($direccion = $pistas['direccion'] ?? null) && preg_match_all('/\b\d{3,5}\b/', (string) $direccion, $numeros)) {
            $palabras = array_filter(preg_split('/[^\p{L}]+/u', Str::upper(Str::ascii((string) $direccion))) ?: [], fn (string $w): bool => strlen($w) >= 2 && ! in_array($w, ['AV', 'AVDA', 'CALLE', 'PJE'], true));

            foreach (array_unique($numeros[0]) as $numero) {
                foreach ($this->client()->execute('res.partner', 'search_read', [[['street', 'ilike', $numero]]], ['fields' => ['id', 'commercial_partner_id', 'street'], 'limit' => 10]) as $p) {
                    $calle = Str::upper(Str::ascii((string) $p['street']));
                    $palabra = collect($palabras)->first(fn (string $w) => preg_match('/\b'.preg_quote($w, '/').'/', $calle));
                    // Un número solo apenas apoya: muchas calles tienen un «1000».
                    $sumar($empresas([$p]), $palabra ? 3 : 1, sprintf('la dirección «%s» coincide con «%s»', $direccion, $p['street']));
                }
            }
        }

        // Lo que ya se subió aquí: Odoo no guarda el teléfono de muchos
        // proveedores, pero sus facturas sí lo traen. Una vez confirmado el
        // proveedor de una foto, la siguiente con el mismo teléfono, correo o
        // dirección lo reconoce. Laravel aprende; Odoo no.
        foreach ($this->yaSubidas($factura) as [$otra, $coincide]) {
            if (($p = $this->proveedor($otra->dato('rut'))) !== null) {
                $sumar([$p['id']], 3, sprintf('%s es el de su factura N° %s, ya subida aquí', $coincide, $otra->dato('folio') ?: '—'));
            }
        }

        // Productos, como apoyo: el código en sus facturas de Odoo y en los XML
        // del SII. También los que quedaron dentro de la descripción: en la
        // foto de los remaches el 21RT vino como «REMA 21RT · A 4.8X20».
        $enLasLineas = collect($factura->lineas())->flatMap(fn (array $l) => preg_match_all(
            '/(?<![\w.])(?=[A-Z0-9-]*\d)(?=[A-Z0-9-]*[A-Z])[A-Z0-9-]{3,12}(?![\w.])/', Str::upper((string) $l['detalle']), $m) ? $m[0] : []);
        $codigos = collect((array) ($pistas['codigos'] ?? []))->merge($enLasLineas)->unique()->take(6)->all();

        foreach ($codigos as $codigo) {
            $grupos = $this->client()->execute('account.move.line', 'read_group',
                [[['move_id.move_type', '=', 'in_invoice'], ['name', 'ilike', $codigo]], ['partner_id'], ['partner_id']], []);

            // Un código que aparece en muchos proveedores no distingue a nadie.
            if ($grupos !== [] && count($grupos) <= 2) {
                $sumar(array_map(fn (array $g): int => is_array($g['partner_id'] ?? null) ? (int) $g['partner_id'][0] : 0, $grupos), 1,
                    sprintf('el código %s aparece en sus facturas', $codigo));
            }

            try {
                $ruts = \Illuminate\Support\Facades\DB::connection('fuelcontrol')->table('gmail_dte_document_lines as l')
                    ->join('gmail_dte_documents as d', 'd.id', '=', 'l.document_id')
                    ->where('l.codigo', $codigo)->distinct()->limit(3)->pluck('d.proveedor_rut')->filter()->all();
            } catch (\Throwable) {
                $ruts = [];
            }

            if ($ruts !== [] && count($ruts) <= 2) {
                foreach ($ruts as $rut) {
                    if (($rut = Rut::normalize($rut)) !== null && ($p = $this->proveedor($rut)) !== null) {
                        $sumar([$p['id']], 1, sprintf('el código %s aparece en sus facturas XML', $codigo));
                    }
                }
            }
        }

        // El total, si coincide con una factura suya.
        foreach ($this->posibles($factura) as $posible) {
            $id = $this->client()->execute('account.move', 'read', [[$posible['id']]], ['fields' => ['commercial_partner_id']])[0]['commercial_partner_id'][0] ?? 0;
            $sumar([(int) $id], 2, sprintf('su %s tiene el mismo total', $posible['name']));
        }

        arsort($puntos);
        $ids = array_keys($puntos);

        // Con 3 o más hay al menos un dato de contacto; con 2, sólo productos
        // o el total: se ofrece igual, pero como pista débil.
        if ($ids === [] || $puntos[$ids[0]] < 2 || (isset($ids[1]) && $puntos[$ids[1]] >= $puntos[$ids[0]] - 1)) {
            return null;
        }

        $p = $this->client()->execute('res.partner', 'read', [[$ids[0]]], ['fields' => ['name', 'vat']])[0] ?? null;

        return $p === null ? null : [
            'id' => $ids[0],
            'name' => (string) $p['name'],
            'vat' => Rut::normalize((string) ($p['vat'] ?: '')),
            'puntaje' => $puntos[$ids[0]],
            'debil' => $puntos[$ids[0]] < 3,
            'razones' => array_values(array_unique($razones[$ids[0]])),
        ];
    }

    /**
     * Las facturas ya subidas, de proveedor conocido, que comparten con ésta
     * el teléfono, el correo o la dirección.
     *
     * @return list<array{0: SupplierInvoiceUpload, 1: string}>
     */
    private function yaSubidas(SupplierInvoiceUpload $factura): array
    {
        $pistas = (array) $factura->dato('pistas');
        $tel = substr((string) ($pistas['telefono'] ?? ''), -6);
        $correo = (string) ($pistas['correo'] ?? '');
        $calle = Str::upper(Str::ascii((string) ($pistas['direccion'] ?? '')));

        if (strlen($tel) < 6 && $correo === '' && $calle === '') {
            return [];
        }

        $encontradas = [];

        foreach (SupplierInvoiceUpload::query()->whereKeyNot($factura->getKey())->whereNotNull('data')->latest()->limit(500)->get() as $otra) {
            $suyas = (array) $otra->dato('pistas');
            $conocido = filled($otra->dato('rut')) && ($otra->isSent() || in_array($otra->origen('rut')['tipo'] ?? null, ['leido', 'confirmado'], true));

            if (! $conocido) {
                continue;
            }

            $coincide = match (true) {
                strlen($tel) >= 6 && str_ends_with((string) ($suyas['telefono'] ?? ''), $tel) => 'el teléfono '.$pistas['telefono'],
                $correo !== '' && $correo === ($suyas['correo'] ?? null) => 'el correo '.$correo,
                $calle !== '' && $calle === Str::upper(Str::ascii((string) ($suyas['direccion'] ?? ''))) => 'la dirección «'.$pistas['direccion'].'»',
                default => null,
            };

            if ($coincide !== null) {
                $encontradas[] = [$otra, $coincide];
            }
        }

        return $encontradas;
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

        $boleta = $factura->dato('tipo') === '39';
        $iva = $this->sinIva($factura) ? null : $this->ivaDeCompra();
        $cuenta = $revision['cuenta'];

        $lineas = array_map(fn (array $l): array => [0, 0, array_filter([
            // Como Contabilidad escribe las BEL: «Peaje … (Boleta N° 126499854491392175)».
            'name' => (string) $l['detalle'].($boleta ? ' (Boleta N° '.$factura->dato('folio').')' : ''),
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
                'name' => sprintf('Ajuste redondeo: %s $%s', $boleta ? 'total boleta' : 'neto factura', number_format($this->base($factura), 0, ',', '.')),
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

        if (($sugerido = $factura->proveedorPorConfirmar()) !== null) {
            $r[] = ['ok' => false, 'bloquea' => true, 'texto' => sprintf('El proveedor %s es inferido, no leído: confírmalo con «Sí, es este» antes de crearla.', $sugerido['name'])];
        }
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
        $boleta = $factura->dato('tipo') === '39';

        if ($total <= 0) {
            $r[] = ['ok' => false, 'bloquea' => true, 'texto' => 'Falta el total.'];
        } elseif ($boleta) {
            // Las 41 BEL que ya tiene Odoo van así: la boleta no da crédito de IVA.
            [$netoBoleta, $ivaBoleta] = $factura->netoEIvaDeBoleta();
            $r[] = ['ok' => true, 'bloquea' => false, 'texto' => sprintf('Boleta: neto %s + IVA %s = %s. Va a Odoo sin impuesto, por el total, como las otras BEL (no da crédito fiscal).', $peso($netoBoleta), $peso($ivaBoleta), $peso($total))];

            if (abs($netoBoleta + $ivaBoleta - $total) > 2) {
                $r[] = ['ok' => false, 'bloquea' => false, 'texto' => sprintf('El neto %s y el IVA %s no suman el total %s: revísalos (a Odoo va el total).', $peso($netoBoleta), $peso($ivaBoleta), $peso($total))];
            }
        } elseif (abs($neto + $exento + $iva - $total) > 2) {
            // Si un solo monto lo arregla todo, decir cuál: casi siempre es un dígito mal leído.
            $sugerencia = ! $exenta && ($arreglo = LocalInvoiceReader::montoMalLeido(compact('neto', 'exento', 'iva', 'total'))) !== null
                ? sprintf(' ¿No será el %s %s? Con eso cuadran el total y el IVA.', $arreglo[0] === 'iva' ? 'IVA' : $arreglo[0], $peso($arreglo[1]))
                : '';
            $r[] = ['ok' => false, 'bloquea' => true, 'texto' => sprintf('Neto %s + exento %s + IVA %s no da el total %s.', $peso($neto), $peso($exento), $peso($iva), $peso($total)).$sugerencia];
        } else {
            $r[] = ['ok' => true, 'bloquea' => false, 'texto' => 'Neto, IVA y total cuadran.'];
        }

        if (! $exenta && ! $boleta && $neto > 0 && abs(round($neto * 0.19) - $iva) > 2) {
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
            $cual = $this->sinIva($factura) ? 'el total' : 'el neto';

            $r[] = match (true) {
                $this->boletaSinIva($factura) => ['ok' => true, 'bloquea' => false, 'texto' => sprintf('Las líneas vienen sin IVA (suman %s): en una boleta van con IVA, así que se llevan al total %s.', $peso($this->sumaDeLineas($factura, false)), $peso($base))],
                $this->preciosConIva($factura) => ['ok' => true, 'bloquea' => false, 'texto' => sprintf('Los precios de las líneas traen el IVA incluido (suman %s, el total): van a Odoo divididos por 1,19, y dan el neto %s.', $peso($this->sumaDeLineas($factura, false)), $peso($base))],
                abs($suma - $base) < 0.5 => ['ok' => true, 'bloquea' => false, 'texto' => sprintf('Las %d líneas suman %s, %s.', count($lineas), $peso($suma), $cual)],
                $ajuste !== 0.0 => ['ok' => true, 'bloquea' => false, 'texto' => sprintf('Las %d líneas suman %s y %s es %s: va una línea de ajuste de redondeo de %s.', count($lineas), $peso($suma), $cual, $peso($base), ($ajuste < 0 ? '−' : '').$peso(abs($ajuste)))],
                default => ['ok' => false, 'bloquea' => true, 'texto' => sprintf('Las líneas suman %s y %s es %s: falta o sobra algo (un descuento, una línea).', $peso($suma), $cual, $peso($base))],
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
     * ¿La boleta trae el precio sin IVA? La 12772 de Distribuidora Mundial
     * imprime neto $420, IVA $80 y total $500, y la línea vino en $420. La
     * boleta va a Odoo sin impuesto, así que la línea tiene que dar el total.
     */
    private function boletaSinIva(SupplierInvoiceUpload $factura): bool
    {
        $total = (float) $factura->dato('total');
        $tolerancia = max((int) config('supplier_invoices.tolerancia', 5), count($factura->lineas()));
        $tal = $this->sumaDeLineas($factura, false);

        return $factura->dato('tipo') === '39' && $tal > 0
            && abs($tal * 1.19 - $total) <= $tolerancia && abs($tal - $total) > $tolerancia;
    }

    /**
     * El precio que va a Odoo: sin IVA en una factura, y en una boleta lo que
     * suma el total. Con IVA incluido se divide
     * por 1,19 sin redondear, como lo hace Contabilidad (el remache a 33,613):
     * Odoo calcula el IVA de cada línea sobre ese precio y el total da el del
     * papel, sin ajuste.
     *
     * @param  array<string, mixed>  $linea
     */
    private function precioNeto(SupplierInvoiceUpload $factura, array $linea): float
    {
        $precio = (float) ($linea['precio'] ?? 0);

        if ($this->boletaSinIva($factura)) {
            // Por la proporción y no por 1,19: así las líneas dan justo el total.
            return round($precio * (float) $factura->dato('total') / $this->sumaDeLineas($factura, false), 6);
        }

        return $this->preciosConIva($factura) ? round($precio / 1.19, 6) : $precio;
    }

    /** Exenta o boleta: va a Odoo sin impuesto, por el total. */
    private function sinIva(SupplierInvoiceUpload $factura): bool
    {
        return in_array($factura->dato('tipo'), ['34', '39'], true);
    }

    /** Lo que Odoo debe sumar en las líneas: el neto más lo exento, sin IVA. */
    private function base(SupplierInvoiceUpload $factura): float
    {
        return $this->sinIva($factura)
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
