<x-app-layout>
    @php
        $editable = $factura->isEditable();
        $peso = fn ($v) => '$'.number_format((float) $v, 0, ',', '.');
        $lineas = $factura->lineas() ?: [['detalle' => '', 'cantidad' => 1, 'precio' => null]];
        $configurado = rtrim((string) config('supplier_invoices.odoo.url'), '/');
        $odooUrl = $factura->dato('odoo_url') ?: $configurado;
        $otroOdoo = $factura->isSent() && $odooUrl !== $configurado;
        // Lo que acaba de ver en Odoo manda; sin revisión, lo que se anotó al leerla.
        $yaEsta = ($revision['duplicada'] ?? null)
            ? [...$revision['duplicada'], 'url' => $configurado]
            : ($revision === null ? $factura->yaEnOdoo() : null);
        $posibles = $yaEsta ? [] : ($revision !== null
            ? array_map(fn ($p) => [...$p, 'url' => $configurado], $revision['posibles'] ?? [])
            : $factura->posiblesEnOdoo());
        $bloqueos = collect($revision['revisiones'] ?? [])->filter(fn ($r) => ! $r['ok'] && $r['bloquea'])
            ->reject(fn ($r) => $yaEsta && str_starts_with($r['texto'], 'Ya está en Odoo'));
        $bienes = collect($revision['revisiones'] ?? [])->filter(fn ($r) => $r['ok']);
        $sePuede = $editable && ($revision['se_puede'] ?? false);
        $estados = ['draft' => 'en borrador', 'posted' => 'confirmada', 'cancel' => 'anulada'];
        $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d-m-Y') : '—';
        $etiqueta = 'text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400';
        $campo = 'mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white disabled:border-transparent disabled:bg-slate-50 disabled:shadow-none dark:disabled:bg-slate-900';
    @endphp

    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <a href="{{ route('odoo.invoice_uploads.index') }}" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 hover:bg-cyan-100 dark:bg-cyan-950/50 dark:text-cyan-400" title="Volver a las subidas">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">{{ $factura->dato('razon_social') ?: 'Factura subida' }}</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $factura->original_name }} · subida por {{ $factura->uploader_name_snapshot ?: '—' }} el {{ $factura->created_at->format('d-m-Y H:i') }}</p>
            </div>
        </div>
    </x-slot>

    @if($factura->isReading())
        {{-- Mientras la IA lee, la página se mira sola cada pocos segundos. --}}
        <meta http-equiv="refresh" content="4">
    @endif

    <div class="mx-auto max-w-full space-y-4 px-4 py-6 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">{{ session('error') }}</div>
        @endif

        {{-- Lo primero que hay que saber de esta factura, en grande. --}}
        @if($yaEsta)
            <div class="flex flex-col gap-4 rounded-2xl border-2 border-rose-300 bg-rose-50 p-5 sm:flex-row sm:items-center dark:border-rose-800 dark:bg-rose-950/40">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-600 text-2xl font-black text-white" aria-hidden="true">!</div>
                <div class="min-w-0 flex-1">
                    <p class="text-lg font-black text-rose-900 dark:text-rose-100">Esta factura ya está en Odoo</p>
                    <p class="mt-0.5 text-sm text-rose-800 dark:text-rose-200">
                        Es la <span class="font-extrabold">{{ $yaEsta['name'] }}</span>, {{ $estados[$yaEsta['state']] ?? $yaEsta['state'] }}: mismo proveedor y mismo folio. No hace falta crearla otra vez.
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a href="{{ $yaEsta['url'] }}/web#id={{ $yaEsta['id'] }}&model=account.move&view_type=form" target="_blank" rel="noopener"
                        class="inline-flex min-h-10 items-center rounded-xl bg-rose-600 px-4 text-sm font-extrabold text-white hover:bg-rose-700">Ver la que está en Odoo ↗</a>
                    <form method="POST" action="{{ route('odoo.invoice_uploads.destroy', $factura) }}">
                        @csrf @method('DELETE')
                        <button class="inline-flex min-h-10 items-center rounded-xl border border-rose-300 bg-white px-4 text-sm font-bold text-rose-800 hover:bg-rose-100 dark:border-rose-800 dark:bg-slate-900 dark:text-rose-200">Quitar esta subida</button>
                    </form>
                </div>
            </div>
        @elseif($posibles)
            {{-- Sin RUT o sin folio no hay certeza, pero el total y la fecha ya dicen mucho. --}}
            <div class="rounded-2xl border-2 border-orange-300 bg-orange-50 p-5 dark:border-orange-800 dark:bg-orange-950/30">
                <div class="flex gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-orange-500 text-2xl font-black text-white" aria-hidden="true">?</div>
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-black text-orange-900 dark:text-orange-100">¿Ya estará en Odoo?</p>
                        <p class="mt-0.5 text-sm text-orange-900 dark:text-orange-200">
                            No se pudo leer {{ blank($factura->dato('rut')) ? 'el RUT' : '' }}{{ blank($factura->dato('rut')) && blank($factura->dato('folio')) ? ' ni ' : '' }}{{ blank($factura->dato('folio')) ? 'el folio' : '' }}, pero en Odoo {{ count($posibles) === 1 ? 'hay una factura' : 'hay '.count($posibles).' facturas' }} con el mismo total ({{ $peso($factura->dato('total')) }}){{ $factura->dato('fecha') ? ' y fecha cercana' : '' }}:
                        </p>
                        <ul class="mt-2 space-y-1.5">
                            @foreach($posibles as $p)
                                <li class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-white/70 px-3 py-2 text-sm dark:bg-slate-900/60">
                                    <span class="font-extrabold text-slate-900 dark:text-white">{{ $p['name'] }}</span>
                                    <span class="text-slate-700 dark:text-slate-300">{{ $p['partner'] }}</span>
                                    <span class="tabular-nums text-slate-500">{{ $fecha($p['fecha'] ?? null) }} · {{ $estados[$p['state']] ?? $p['state'] }}</span>
                                    <a href="{{ $p['url'] }}/web#id={{ $p['id'] }}&model=account.move&view_type=form" target="_blank" rel="noopener" class="ml-auto text-xs font-extrabold text-orange-700 hover:text-orange-900 dark:text-orange-300">Ver en Odoo ↗</a>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-xs text-orange-900 dark:text-orange-200">Si es la misma, quita esta subida. Si no, escribe el RUT y el folio abajo y guarda: con eso se revisa de verdad.</p>
                    </div>
                </div>
            </div>
        @elseif($factura->isSent())
            <div class="flex flex-col gap-3 rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-5 sm:flex-row sm:items-center dark:border-emerald-800 dark:bg-emerald-950/30">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-2xl font-black text-white" aria-hidden="true">✓</div>
                <div class="min-w-0 flex-1">
                    <p class="text-lg font-black text-emerald-900 dark:text-emerald-100">En Odoo{{ $otroOdoo ? ' de '.parse_url($odooUrl, PHP_URL_HOST) : '' }} como borrador: {{ $factura->odoo_move_name }} · {{ $peso($factura->odoo_amount_total) }}</p>
                    <p class="mt-0.5 text-sm text-emerald-800 dark:text-emerald-200">La creó {{ $factura->sent_by_name }} el {{ $factura->sent_at?->format('d-m-Y H:i') }}. Contabilidad la confirma en Odoo.</p>
                </div>
                <a href="{{ $odooUrl }}/web#id={{ $factura->odoo_move_id }}&model=account.move&view_type=form" target="_blank" rel="noopener"
                    class="inline-flex min-h-10 shrink-0 items-center rounded-xl bg-emerald-600 px-4 text-sm font-extrabold text-white hover:bg-emerald-700">Abrir en Odoo ↗</a>
            </div>
        @elseif($factura->isReading())
            <div class="flex items-center gap-4 rounded-2xl border-2 border-cyan-200 bg-cyan-50 p-5 dark:border-cyan-900/60 dark:bg-cyan-950/30">
                <svg class="h-8 w-8 shrink-0 animate-spin text-cyan-600" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" /><path fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" class="opacity-75" /></svg>
                <div>
                    <p class="text-lg font-black text-cyan-900 dark:text-cyan-100">{{ $factura->status === 'waiting' ? 'Esperando a la IA' : 'La IA está leyendo la factura…' }}</p>
                    <p class="text-sm text-cyan-800 dark:text-cyan-200">{{ $factura->status === 'waiting' ? 'No está disponible ahora (¿la Mac durmiendo?). Se reintenta sola; puedes cerrar esta página.' : 'Suele tardar entre 5 y 30 segundos. La página se actualiza sola.' }}</p>
                </div>
            </div>
        @elseif($factura->status === 'failed')
            <div class="rounded-2xl border-2 border-rose-200 bg-rose-50 p-5 dark:border-rose-900/60 dark:bg-rose-950/40">
                <p class="text-lg font-black text-rose-900 dark:text-rose-100">La IA no pudo leerla</p>
                <p class="text-sm text-rose-800 dark:text-rose-200">{{ $factura->error_message }}. Llénala a mano abajo, o vuelve a intentarlo.</p>
            </div>
        @elseif($revision && $revision['error'])
            <div class="rounded-2xl border-2 border-rose-200 bg-rose-50 p-5 text-sm font-semibold text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">{{ $revision['error'] }}</div>
        @elseif($sePuede)
            <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                <span class="text-lg font-black text-emerald-600" aria-hidden="true">✓</span>
                <p class="text-sm font-bold text-emerald-900 dark:text-emerald-100">Todo cuadra. Compara los datos con el documento y créala en Odoo.</p>
            </div>
        @elseif($bloqueos->isNotEmpty())
            <div class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/30">
                <p class="text-lg font-black text-amber-900 dark:text-amber-100">Antes de crearla, {{ $bloqueos->count() === 1 ? 'falta una cosa' : 'faltan '.$bloqueos->count().' cosas' }}</p>
                <ul class="mt-1 space-y-0.5 text-sm text-amber-900 dark:text-amber-200">
                    @foreach($bloqueos as $r)
                        <li class="flex gap-2"><span aria-hidden="true">✕</span><span>{{ $r['texto'] }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- La factura de un vistazo. --}}
        @if($factura->data)
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="col-span-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-1 dark:border-slate-800 dark:bg-slate-900">
                    <p class="{{ $etiqueta }}">Proveedor</p>
                    <p class="mt-1 truncate text-sm font-extrabold text-slate-900 dark:text-white">{{ $factura->dato('razon_social') ?: '—' }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">RUT {{ \App\Support\Rut::format($factura->dato('rut')) ?? ($factura->dato('rut') ?: '—') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="{{ $etiqueta }}">{{ \App\Models\SupplierInvoiceUpload::TIPOS[$factura->dato('tipo')] ?? 'Factura' }}</p>
                    <p class="mt-1 text-xl font-black tabular-nums text-slate-900 dark:text-white">N° {{ $factura->dato('folio') ?: '—' }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="{{ $etiqueta }}">Emisión · vence</p>
                    <p class="mt-1 text-sm font-extrabold tabular-nums text-slate-900 dark:text-white">{{ $fecha($factura->dato('fecha')) }}</p>
                    <p class="text-xs tabular-nums text-slate-500 dark:text-slate-400">vence {{ $fecha($factura->dato('vencimiento') ?: $factura->dato('fecha')) }}</p>
                </div>
                <div class="col-span-2 rounded-2xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm lg:col-span-1 dark:border-cyan-900/60 dark:bg-cyan-950/30">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-300">Total</p>
                    <p class="mt-1 text-2xl font-black tabular-nums text-cyan-900 dark:text-cyan-100">{{ $peso($factura->dato('total')) }}</p>
                    <p class="text-xs tabular-nums text-cyan-800 dark:text-cyan-300">neto {{ $peso($factura->dato('neto')) }} · IVA {{ $peso($factura->dato('iva')) }}</p>
                </div>
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            {{-- El documento, a la vista mientras se revisa. En el celular se pliega. --}}
            <section class="lg:sticky lg:top-4 lg:self-start">
                <details class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" open
                    x-data x-init="if (window.innerWidth < 1024) $el.removeAttribute('open')">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 border-b border-slate-100 px-4 py-2.5 dark:border-slate-800">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Documento <span class="font-semibold normal-case tracking-normal lg:hidden">· tocar para ver</span></span>
                        <a href="{{ route('odoo.invoice_uploads.file', $factura) }}" target="_blank" rel="noopener" class="text-xs font-bold text-cyan-700 hover:text-cyan-900 dark:text-cyan-300">Abrir aparte ↗</a>
                    </summary>
                    @if($factura->isImage())
                        <div class="max-h-[calc(100vh-9rem)] overflow-auto bg-slate-100 dark:bg-slate-950">
                            <img src="{{ route('odoo.invoice_uploads.file', $factura) }}" alt="Factura subida" class="h-auto w-full">
                        </div>
                    @else
                        <iframe src="{{ route('odoo.invoice_uploads.file', $factura) }}" title="Factura subida" class="h-[70vh] w-full lg:h-[calc(100vh-9rem)]"></iframe>
                    @endif
                </details>
            </section>

            <section class="space-y-4">
                <form method="POST" action="{{ route('odoo.invoice_uploads.update', $factura) }}"
                    x-data="{
                        lineas: @js($lineas), accion: 'guardar', enviando: false,
                        neto: @js($factura->dato('neto')), exento: @js($factura->dato('exento')), total: @js($factura->dato('total')),
                        num(v) { return parseFloat(v) || 0 },
                        suma() { return this.lineas.reduce((s, l) => s + this.num(l.cantidad) * this.num(l.precio), 0) },
                        peso(v) { return '$' + Math.round(v).toLocaleString('es-CL') },
                        cuadra() { const s = this.suma(); return Math.abs(s - this.num(this.neto) - this.num(this.exento)) < 6 || Math.abs(s - this.num(this.total)) < 6 },
                    }" @submit="enviando = true"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @csrf @method('PUT')
                    {{-- El botón apretado viaja aquí: uno desactivado al enviar no manda su valor. --}}
                    <input type="hidden" name="accion" :value="accion">

                    <fieldset @disabled(! $editable) class="divide-y divide-slate-100 dark:divide-slate-800">
                        @if($errors->any())
                            <p class="bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">{{ $errors->first() }}</p>
                        @endif

                        <div class="space-y-3 p-5">
                            <p class="text-sm font-extrabold text-slate-900 dark:text-white">Datos de la factura</p>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-6">
                                <label class="col-span-2 sm:col-span-4"><span class="{{ $etiqueta }}">Proveedor</span>
                                    <input name="razon_social" value="{{ old('razon_social', $factura->dato('razon_social')) }}" class="{{ $campo }}"></label>
                                <label class="col-span-2"><span class="{{ $etiqueta }}">RUT</span>
                                    <input name="rut" value="{{ old('rut', \App\Support\Rut::format($factura->dato('rut')) ?? $factura->dato('rut')) }}" class="{{ $campo }} tabular-nums"></label>
                                <label class="col-span-2 sm:col-span-3"><span class="{{ $etiqueta }}">Tipo</span>
                                    <select name="tipo" class="{{ $campo }}">
                                        @foreach(\App\Models\SupplierInvoiceUpload::TIPOS as $codigo => $nombre)
                                            <option value="{{ $codigo }}" @selected(old('tipo', $factura->dato('tipo') ?? '33') === $codigo)>({{ $codigo }}) {{ $nombre }}</option>
                                        @endforeach
                                    </select></label>
                                <label class="col-span-2 sm:col-span-3"><span class="{{ $etiqueta }}">Folio</span>
                                    <input name="folio" inputmode="numeric" value="{{ old('folio', $factura->dato('folio')) }}" class="{{ $campo }} tabular-nums"></label>
                                <label class="sm:col-span-3"><span class="{{ $etiqueta }}">Emisión</span>
                                    <input type="date" name="fecha" value="{{ old('fecha', $factura->dato('fecha')) }}" class="{{ $campo }}"></label>
                                <label class="sm:col-span-3"><span class="{{ $etiqueta }}">Vencimiento</span>
                                    <input type="date" name="vencimiento" value="{{ old('vencimiento', $factura->dato('vencimiento')) }}" class="{{ $campo }}"></label>
                            </div>
                        </div>

                        <div class="p-5">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="text-sm font-extrabold text-slate-900 dark:text-white">Detalle</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">precio unitario tal como viene en la factura</p>
                            </div>
                            <div class="mt-2 hidden grid-cols-[minmax(0,1fr)_5rem_7.5rem_6.5rem_2rem] gap-2 px-1 sm:grid">
                                <span class="{{ $etiqueta }}">Descripción</span>
                                <span class="{{ $etiqueta }} text-right">Cant.</span>
                                <span class="{{ $etiqueta }} text-right">P. unitario</span>
                                <span class="{{ $etiqueta }} text-right">Subtotal</span>
                                <span></span>
                            </div>
                            <div class="mt-1 space-y-2">
                                <template x-for="(l, i) in lineas" :key="i">
                                    <div class="grid grid-cols-[1fr_auto] gap-2 rounded-xl bg-slate-50 p-2 sm:grid-cols-[minmax(0,1fr)_5rem_7.5rem_6.5rem_2rem] sm:items-center sm:bg-transparent sm:p-0 dark:bg-slate-800/40 sm:dark:bg-transparent">
                                        {{-- Crece con el texto: una descripción larga se lee entera, no cortada. --}}
                                        <textarea :name="`lineas[${i}][detalle]`" x-model="l.detalle" placeholder="Descripción" rows="1"
                                            x-init="$nextTick(() => { $el.style.height = $el.scrollHeight + 2 + 'px' })"
                                            @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 2 + 'px'"
                                            class="{{ $campo }} col-span-2 !mt-0 resize-none overflow-hidden leading-snug sm:col-span-1"></textarea>
                                        <div class="col-span-2 grid grid-cols-3 gap-2 sm:contents">
                                            <input :name="`lineas[${i}][cantidad]`" x-model="l.cantidad" type="number" step="any" min="0" placeholder="Cant." aria-label="Cantidad" class="{{ $campo }} !mt-0 text-right tabular-nums">
                                            <input :name="`lineas[${i}][precio]`" x-model="l.precio" type="number" step="any" placeholder="Precio" aria-label="Precio unitario" class="{{ $campo }} !mt-0 text-right tabular-nums">
                                            <span class="self-center text-right text-sm font-bold tabular-nums text-slate-700 dark:text-slate-200" x-text="peso(num(l.cantidad) * num(l.precio))"></span>
                                        </div>
                                        <button type="button" @click="lineas.splice(i, 1)" x-show="lineas.length > 1 && @js($editable)"
                                            class="hidden h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 sm:flex" title="Quitar línea">✕</button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="lineas.push({ detalle: '', cantidad: 1, precio: null })" x-show="@js($editable)"
                                class="mt-2 text-xs font-bold text-cyan-700 hover:text-cyan-900 dark:text-cyan-300">+ Agregar línea</button>
                        </div>

                        <div class="grid gap-5 p-5 sm:grid-cols-2">
                            <div class="space-y-2 text-sm">
                                <p class="font-extrabold text-slate-900 dark:text-white">Las líneas suman <span class="tabular-nums" x-text="peso(suma())"></span></p>
                                <p x-show="cuadra()" class="font-semibold text-emerald-700 dark:text-emerald-400">✓ Cuadra con la factura.</p>
                                <p x-show="!cuadra()" class="font-semibold text-amber-700 dark:text-amber-400">No cuadra con el neto ni con el total: falta, sobra o está mal leída una línea.</p>
                                @if($revision['cuenta'] ?? null)
                                    <p class="text-xs text-slate-500 dark:text-slate-400">En Odoo: cuenta <span class="font-bold">{{ $revision['cuenta']['account'] ?? 'por defecto' }}</span>{{ $revision['cuenta']['analytic'] ? ' y su analítica' : '' }}, como en su última factura confirmada ({{ $revision['cuenta']['desde'] }}).</p>
                                @elseif($revision['proveedor'] ?? null)
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Es su primera factura: la cuenta la pone Odoo por defecto y la analítica queda vacía.</p>
                                @endif
                            </div>
                            <div class="space-y-2">
                                @foreach(['neto' => 'Neto', 'exento' => 'Exento', 'iva' => 'IVA 19%', 'total' => 'Total'] as $clave => $nombre)
                                    <label class="flex items-center justify-between gap-3 {{ $clave === 'total' ? 'border-t border-slate-200 pt-2 dark:border-slate-700' : '' }}">
                                        <span class="whitespace-nowrap text-sm {{ $clave === 'total' ? 'font-black text-slate-900 dark:text-white' : 'font-semibold text-slate-600 dark:text-slate-300' }}">{{ $nombre }}</span>
                                        <input type="number" step="any" min="0" name="{{ $clave }}" value="{{ old($clave, $factura->dato($clave)) }}"
                                            @if($clave !== 'iva') x-model="{{ $clave }}" @endif
                                            class="{{ $campo }} !mt-0 !w-36 shrink-0 text-right tabular-nums {{ $clave === 'total' ? 'font-black' : '' }}">
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </fieldset>

                    @if($editable)
                        <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white/95 px-5 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Va a <span class="font-bold">{{ $destino }}</span>, en borrador.</p>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" @click="accion = 'guardar'" :disabled="enviando"
                                    class="inline-flex min-h-10 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Guardar</button>
                                @unless($yaEsta)
                                    <button type="submit" @click="accion = 'crear'" :disabled="enviando"
                                        class="inline-flex min-h-10 items-center rounded-xl px-4 text-sm font-extrabold text-white disabled:opacity-40 {{ $sePuede ? 'bg-cyan-600 hover:bg-cyan-700' : 'bg-slate-400 hover:bg-slate-500' }}"
                                        @unless($sePuede) title="Primero corrige lo que falta: al guardar se vuelve a revisar" @endunless>
                                        <span x-text="enviando ? 'Un momento…' : 'Guardar y crear borrador en Odoo'"></span>
                                    </button>
                                @endunless
                            </div>
                        </div>
                    @endif
                </form>

                @if($revision)
                    <details class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <summary class="cursor-pointer list-none px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-200">
                            Revisiones contra {{ $destino }} · <span class="text-emerald-700 dark:text-emerald-400">{{ $bienes->count() }} bien</span>@if($bloqueos->isNotEmpty() || $yaEsta) · <span class="text-rose-700 dark:text-rose-400">{{ $bloqueos->count() + ($yaEsta ? 1 : 0) }} con problema</span>@endif
                        </summary>
                        <ul class="space-y-1 border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                            @foreach($revision['revisiones'] as $r)
                                <li class="flex gap-2 text-sm {{ $r['ok'] ? 'text-slate-700 dark:text-slate-300' : 'font-semibold text-rose-700 dark:text-rose-300' }}">
                                    <span aria-hidden="true">{{ $r['ok'] ? '✓' : '✕' }}</span><span>{{ $r['texto'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                @if($factura->warnings)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3 dark:border-amber-900/60 dark:bg-amber-950/30">
                        <p class="text-xs font-extrabold uppercase tracking-wider text-amber-700 dark:text-amber-300">La IA avisa</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-amber-900 dark:text-amber-200">
                            @foreach($factura->warnings as $aviso)
                                <li>{{ $aviso }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @unless($factura->isSent() || $factura->isReading() || $yaEsta)
                    <div class="flex flex-wrap justify-end gap-4 px-1">
                        <form method="POST" action="{{ route('odoo.invoice_uploads.reread', $factura) }}">
                            @csrf
                            <button class="text-xs font-bold text-slate-500 hover:text-cyan-700">Volver a leer con la IA</button>
                        </form>
                        <form method="POST" action="{{ route('odoo.invoice_uploads.destroy', $factura) }}" onsubmit="return confirm('¿Quitar esta factura subida?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-bold text-slate-500 hover:text-rose-600">Quitar</button>
                        </form>
                    </div>
                @endunless
            </section>
        </div>
    </div>
</x-app-layout>
