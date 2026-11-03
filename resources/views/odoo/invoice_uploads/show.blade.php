<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <a href="{{ route('odoo.invoice_uploads.index') }}" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 hover:bg-cyan-100 dark:bg-cyan-950/50 dark:text-cyan-400" title="Volver a las subidas">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">{{ $factura->dato('razon_social') ?: 'Factura subida' }}{{ $factura->dato('folio') ? ' · N° '.$factura->dato('folio') : '' }}</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $factura->original_name }} · {{ $factura->statusLabel() }}</p>
            </div>
        </div>
    </x-slot>

    @if($factura->isReading())
        {{-- Mientras la IA lee, la página se mira sola cada pocos segundos. --}}
        <meta http-equiv="refresh" content="4">
    @endif

    @php
        $editable = $factura->isEditable();
        $peso = fn ($v) => '$'.number_format((float) $v, 0, ',', '.');
        $lineas = $factura->lineas() ?: [['detalle' => '', 'cantidad' => null, 'precio' => null]];
        $odooUrl = rtrim((string) config('supplier_invoices.odoo.url'), '/');
    @endphp

    <div class="mx-auto max-w-full space-y-4 px-4 py-6 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">{{ session('error') }}</div>
        @endif

        @if($factura->isSent())
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                <p class="text-sm font-bold text-emerald-800 dark:text-emerald-200">
                    En Odoo como borrador: {{ $factura->odoo_move_name }} · {{ $peso($factura->odoo_amount_total) }}
                    <span class="font-medium">— la creó {{ $factura->sent_by_name }} el {{ $factura->sent_at?->format('d-m-Y H:i') }}. Se confirma allá.</span>
                </p>
                <a href="{{ $odooUrl }}/web#id={{ $factura->odoo_move_id }}&model=account.move&view_type=form" target="_blank" rel="noopener"
                    class="inline-flex min-h-9 items-center rounded-xl border border-emerald-300 bg-white px-3 text-xs font-extrabold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-slate-900 dark:text-emerald-200">Abrir en Odoo ↗</a>
            </div>
        @elseif($factura->isReading())
            <div class="flex items-center gap-3 rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-semibold text-cyan-900 dark:border-cyan-900/60 dark:bg-cyan-950/30 dark:text-cyan-200">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" /><path fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" class="opacity-75" /></svg>
                {{ $factura->status === 'waiting' ? 'La IA no está disponible ahora (¿la Mac durmiendo?). Se reintenta sola.' : 'La IA está leyendo la factura…' }}
            </div>
        @elseif($factura->status === 'failed')
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
                La IA no pudo leerla: {{ $factura->error_message }}. Puedes llenarla a mano o volver a intentarlo.
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- El documento, a la vista mientras se revisa. --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @if($factura->isImage())
                    <img src="{{ route('odoo.invoice_uploads.file', $factura) }}" alt="Factura subida" class="h-auto w-full">
                @else
                    <iframe src="{{ route('odoo.invoice_uploads.file', $factura) }}" title="Factura subida" class="h-[75vh] w-full"></iframe>
                @endif
            </section>

            <section class="space-y-4">
                @if($factura->warnings)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/60 dark:bg-amber-950/30">
                        <p class="text-xs font-extrabold uppercase tracking-wider text-amber-700 dark:text-amber-300">Avisos de la lectura</p>
                        <ul class="mt-1.5 list-disc space-y-0.5 pl-5 text-xs text-amber-900 dark:text-amber-200">
                            @foreach($factura->warnings as $aviso)
                                <li>{{ $aviso }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($revision)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Antes de crearla en {{ $destino }}</p>
                        <ul class="mt-2 space-y-1">
                            @foreach($revision['revisiones'] as $r)
                                <li class="flex gap-2 text-sm {{ $r['ok'] ? 'text-slate-700 dark:text-slate-300' : 'font-semibold text-rose-700 dark:text-rose-300' }}">
                                    <span aria-hidden="true">{{ $r['ok'] ? '✓' : '✕' }}</span><span>{{ $r['texto'] }}</span>
                                </li>
                            @endforeach
                            @if($revision['error'])
                                <li class="flex gap-2 text-sm font-semibold text-rose-700 dark:text-rose-300"><span aria-hidden="true">✕</span><span>{{ $revision['error'] }}</span></li>
                            @endif
                        </ul>
                        @if($revision['proveedor'])
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                @if($revision['cuenta'])
                                    Cuenta {{ $revision['cuenta']['account'] ?? 'por defecto' }}{{ $revision['cuenta']['analytic'] ? ' y su analítica' : '' }}, como en su última factura ({{ $revision['cuenta']['desde'] }}).
                                @else
                                    Es su primera factura: la cuenta la pone Odoo por defecto y la analítica queda vacía.
                                @endif
                            </p>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('odoo.invoice_uploads.update', $factura) }}"
                    x-data="{ lineas: @js($lineas), accion: 'guardar', enviando: false }" @submit="enviando = true"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @csrf @method('PUT')
                    {{-- El botón apretado viaja aquí: uno desactivado al enviar no manda su valor. --}}
                    <input type="hidden" name="accion" :value="accion">
                    <fieldset @disabled(! $editable) class="space-y-3">
                        @php
                            $campo = 'mt-1 block w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white disabled:bg-slate-50 dark:disabled:bg-slate-900';
                            $etiqueta = 'text-[11px] font-bold uppercase tracking-wider text-slate-500';
                        @endphp
                        @if($errors->any())
                            <p class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">{{ $errors->first() }}</p>
                        @endif
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <label class="col-span-2"><span class="{{ $etiqueta }}">Proveedor</span>
                                <input name="razon_social" value="{{ old('razon_social', $factura->dato('razon_social')) }}" class="{{ $campo }}"></label>
                            <label><span class="{{ $etiqueta }}">RUT</span>
                                <input name="rut" value="{{ old('rut', \App\Support\Rut::format($factura->dato('rut')) ?? $factura->dato('rut')) }}" class="{{ $campo }}"></label>
                            <label><span class="{{ $etiqueta }}">Tipo</span>
                                <select name="tipo" class="{{ $campo }}">
                                    @foreach(\App\Models\SupplierInvoiceUpload::TIPOS as $codigo => $nombre)
                                        <option value="{{ $codigo }}" @selected(old('tipo', $factura->dato('tipo') ?? '33') === $codigo)>({{ $codigo }}) {{ $nombre }}</option>
                                    @endforeach
                                </select></label>
                            <label><span class="{{ $etiqueta }}">Folio</span>
                                <input name="folio" inputmode="numeric" value="{{ old('folio', $factura->dato('folio')) }}" class="{{ $campo }}"></label>
                            <label><span class="{{ $etiqueta }}">Fecha emisión</span>
                                <input type="date" name="fecha" value="{{ old('fecha', $factura->dato('fecha')) }}" class="{{ $campo }}"></label>
                            <label><span class="{{ $etiqueta }}">Vencimiento</span>
                                <input type="date" name="vencimiento" value="{{ old('vencimiento', $factura->dato('vencimiento')) }}" class="{{ $campo }}"></label>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach(['neto' => 'Neto', 'exento' => 'Exento', 'iva' => 'IVA', 'total' => 'Total'] as $clave => $nombre)
                                <label><span class="{{ $etiqueta }}">{{ $nombre }}</span>
                                    <input type="number" step="any" min="0" name="{{ $clave }}" value="{{ old($clave, $factura->dato($clave)) }}" class="{{ $campo }} text-right tabular-nums"></label>
                            @endforeach
                        </div>

                        <div>
                            <p class="{{ $etiqueta }}">Líneas <span class="normal-case tracking-normal font-semibold">· precio unitario neto</span></p>
                            <div class="mt-1 space-y-2">
                                <template x-for="(l, i) in lineas" :key="i">
                                    <div class="grid grid-cols-[1fr_5rem_7rem_auto] items-center gap-2">
                                        <input :name="`lineas[${i}][detalle]`" x-model="l.detalle" placeholder="Detalle" class="{{ $campo }} !mt-0">
                                        <input :name="`lineas[${i}][cantidad]`" x-model="l.cantidad" type="number" step="any" min="0" placeholder="Cant." class="{{ $campo }} !mt-0 text-right tabular-nums">
                                        <input :name="`lineas[${i}][precio]`" x-model="l.precio" type="number" step="any" placeholder="Precio" class="{{ $campo }} !mt-0 text-right tabular-nums">
                                        <button type="button" @click="lineas.splice(i, 1)" x-show="lineas.length > 1 && @js($editable)" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Quitar línea">✕</button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="lineas.push({ detalle: '', cantidad: 1, precio: null })" x-show="@js($editable)"
                                class="mt-2 text-xs font-bold text-cyan-700 hover:text-cyan-900 dark:text-cyan-300">+ Agregar línea</button>
                            <p class="mt-1 text-right text-xs text-slate-500 dark:text-slate-400">
                                Suman <span class="font-bold tabular-nums" x-text="'$' + Math.round(lineas.reduce((s, l) => s + (parseFloat(l.cantidad) || 0) * (parseFloat(l.precio) || 0), 0)).toLocaleString('es-CL')"></span>
                            </p>
                        </div>
                    </fieldset>

                    @if($editable)
                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                            <button type="submit" @click="accion = 'guardar'" :disabled="enviando"
                                class="inline-flex min-h-10 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Guardar</button>
                            <button type="submit" @click="accion = 'crear'" :disabled="enviando"
                                class="inline-flex min-h-10 items-center rounded-xl bg-cyan-600 px-4 text-sm font-extrabold text-white hover:bg-cyan-700 disabled:opacity-40">
                                <span x-text="enviando ? 'Un momento…' : 'Guardar y crear borrador en Odoo'"></span>
                            </button>
                        </div>
                        <p class="mt-2 text-right text-[11px] text-slate-500 dark:text-slate-400">Va a <span class="font-bold">{{ $destino }}</span>. Queda en borrador: se confirma en Odoo.</p>
                    @endif
                </form>

                @unless($factura->isSent() || $factura->isReading())
                    <div class="flex flex-wrap justify-end gap-2">
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
