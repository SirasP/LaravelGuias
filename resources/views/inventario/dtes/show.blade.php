<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                {{ $dte->tipo_nombre ?? $dte->tipo_dte }} #{{ $dte->folio }}
            </div>

            <a href="{{ route('inventario.dtes.index') }}"
               class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-sm">
                Volver
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 space-y-4">

            {{-- Feedback --}}
            @if(session('ok'))
                <div class="rounded-xl bg-green-50 border border-green-200 p-3 text-green-800">
                    {{ session('ok') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-xl bg-red-50 border border-red-200 p-3 text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Resumen --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-xl bg-white dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700">
                    <div class="text-xs text-gray-500">Emisor</div>
                    <div class="font-semibold">{{ $dte->rz_emisor }}</div>
                    <div class="text-sm text-gray-500">{{ $dte->rut_emisor }}</div>
                </div>

                <div class="rounded-xl bg-white dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700">
                    <div class="text-xs text-gray-500">Receptor</div>
                    <div class="font-semibold">{{ $dte->rz_receptor }}</div>
                    <div class="text-sm text-gray-500">{{ $dte->rut_receptor }}</div>
                </div>

                <div class="rounded-xl bg-white dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700">
                    <div class="text-xs text-gray-500">Totales</div>
                    <div class="text-sm">
                        Neto: <b>{{ number_format((float) ($dte->mnt_neto ?? 0), 0, ',', '.') }}</b>
                    </div>
                    <div class="text-sm">
                        IVA: <b>{{ number_format((float) ($dte->iva ?? 0), 0, ',', '.') }}</b>
                    </div>
                    <div class="text-sm">
                        Total: <b>{{ number_format((float) ($dte->mnt_total ?? 0), 0, ',', '.') }}</b>
                    </div>
                </div>
            </div>

            {{-- DETALLE + SELECCIÓN INVENTARIO --}}
            <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden">

                {{-- ✅ UN SOLO FORM PARA TODO --}}
                <form id="frmDetInv" method="POST">
                    @csrf

                    {{-- Barra de acciones --}}
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-4">
                        <div class="font-semibold text-sm">Detalle</div>

                        <div class="ml-auto flex gap-2">
                            <button
                                type="submit"
                                formaction="{{ route('inventario.dtes.inventario.ingresar', $dte->id) }}"
                                class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm">
                                Ingresar a inventario
                            </button>

                            <button
                                type="submit"
                                formaction="{{ route('inventario.dtes.detalles.updateSelection', $dte->id) }}"
                                class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm">
                                Guardar selección
                            </button>
                        </div>
                    </div>

                    {{-- Controles: seleccionar todo + bodega --}}
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-4">
                        {{-- Seleccionar todo --}}
                        <label class="inline-flex items-center gap-2 text-sm font-normal">
                            <input id="checkAll" type="checkbox" class="rounded border-gray-300">
                            Seleccionar todo
                        </label>

                        {{-- Bodega --}}
                        <div class="flex items-center gap-2 flex-1 max-w-xl">
                            <span class="text-sm text-gray-600 dark:text-gray-300">Bodega:</span>

                            <select id="bodega_select" name="bodega_id"
                                class="flex-1 max-w-md rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                                @foreach($bodegas as $b)
                                    <option value="{{ $b->id }}"
                                        {{ (string) old('bodega_id', request('bodega_id')) === (string) $b->id ? 'selected' : '' }}>
                                        {{ $b->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Ayuda --}}
                        <div class="ml-auto text-xs text-gray-500">
                            (La bodega elegida se usa también al “Ingresar a inventario”)
                        </div>
                    </div>

                    {{-- Tabla --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-left">
                                    <th>Inventario</th>
                                    <th>#</th>
                                    <th>Item</th>
                                    <th>Cant</th>
                                    <th>A ingresar</th>
                                    <th>UM</th>
                                    <th>Precio</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($detalles as $l)
                                    @php
                                        $qty = (float) ($l->qty ?? 0);
                                        $precio = (float) ($l->prc_item ?? 0);
                                        $monto = (float) ($l->monto_item ?? 0);

                                        $ingresada = (float) ($l->qty_ingresada ?? 0);
                                        $pendiente = max(0, $qty - $ingresada);
                                        $completo = $pendiente <= 0;

                                        $checked = isset($l->seleccionado_inventario) ? (bool) $l->seleccionado_inventario : false;

                                        $qtyIng = (isset($l->qty_a_ingresar) && $l->qty_a_ingresar !== null)
                                            ? (float) $l->qty_a_ingresar
                                            : $pendiente;

                                        $qtyIng = min($qtyIng, $pendiente);

                                        // ✅ si está completo, mostramos 0 (y el input igual manda qty=0 si llega)
                                        $qtyIngValue = $pendiente > 0
                                            ? rtrim(rtrim(number_format($qtyIng, 3, '.', ''), '0'), '.')
                                            : '0';
                                    @endphp

                                    <tr class="{{ $completo ? 'bg-gray-100 dark:bg-gray-900 text-gray-400' : '' }}">
                                        {{-- Inventario --}}
                                        <td class="px-4 py-3">
                                            <input type="checkbox"
                                                name="lines[{{ $l->id }}][selected]"
                                                value="1"
                                                class="rounded border-gray-300 rowCheck {{ $completo ? 'opacity-50 pointer-events-none' : '' }}"
                                                data-line="{{ $l->id }}"
                                                data-completo="{{ $completo ? '1' : '0' }}"
                                                {{ $checked ? 'checked' : '' }}>
                                        </td>

                                        {{-- # --}}
                                        <td class="px-4 py-3">{{ $l->nro_lin_det }}</td>

                                        {{-- Item --}}
                                        <td class="px-4 py-3 font-medium">{{ $l->nmb_item }}</td>

                                        {{-- Cant total --}}
                                        <td class="px-4 py-3">
                                            {{ number_format($qty, 3, ',', '.') }}
                                            @if($ingresada > 0)
                                                <div class="text-xs text-gray-500">
                                                    Ingresado: {{ number_format($ingresada, 3, ',', '.') }}
                                                </div>
                                            @endif
                                        </td>

                                        {{-- A ingresar --}}
                                        <td class="px-4 py-3">
                                            <input type="number"
                                                step="0.001"
                                                min="0"
                                                max="{{ $pendiente }}"
                                                name="lines[{{ $l->id }}][qty]"
                                                value="{{ $qtyIngValue }}"
                                                data-line="{{ $l->id }}"
                                                data-completo="{{ $completo ? '1' : '0' }}"
                                                {{ $completo ? 'readonly' : '' }}
                                                class="w-28 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-2 py-1 text-sm qtyInput {{ $completo ? 'opacity-60' : '' }}">

                                            <div class="text-xs mt-1 {{ $completo ? 'text-gray-400 italic' : 'text-gray-500' }}">
                                                Máx: {{ number_format($pendiente, 3, ',', '.') }}
                                                @if($completo)
                                                    <div class="text-xs text-green-600 font-semibold mt-1">
                                                        ✔ Completamente ingresado
                                                    </div>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- UM --}}
                                        <td class="px-4 py-3">{{ $l->unmd_item }}</td>

                                        {{-- Precio --}}
                                        <td class="px-4 py-3">{{ number_format($precio, 4, ',', '.') }}</td>

                                        {{-- Monto --}}
                                        <td class="px-4 py-3">{{ number_format($monto, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                            Sin líneas (normal en algunas guías).
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>

            @if($dte->xml)
                <details class="rounded-xl bg-white dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700">
                    <summary class="cursor-pointer font-semibold">Ver XML</summary>
                    <pre class="mt-3 text-xs overflow-x-auto">{{ $dte->xml }}</pre>
                </details>
            @endif

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const checkAll = document.getElementById('checkAll');
            const rowChecks = () => document.querySelectorAll('.rowCheck');

            const toggleQty = (lineId, enabled) => {
                const input = document.querySelector(`.qtyInput[data-line="${lineId}"]`);
                const cb = document.querySelector(`.rowCheck[data-line="${lineId}"]`);
                if (!input) return;

                const completo = (input.getAttribute('data-completo') === '1') || (cb?.getAttribute('data-completo') === '1');

                // si completo: readonly visual + siempre deshabilitamos edición
                if (completo) {
                    input.readOnly = true;
                    return;
                }

                input.readOnly = false;
                // opcional: si quieres bloquear qty cuando no está seleccionado:
                // input.disabled = !enabled;
            };

            // Seleccionar todo (no toca completos)
            checkAll?.addEventListener('change', () => {
                rowChecks().forEach(cb => {
                    const completo = cb.getAttribute('data-completo') === '1';
                    if (completo) return;

                    cb.checked = checkAll.checked;
                    cb.dispatchEvent(new Event('change'));
                });
            });

            document.querySelectorAll('.rowCheck').forEach(cb => {
                const lineId = cb.getAttribute('data-line');
                const apply = () => toggleQty(lineId, cb.checked);
                cb.addEventListener('change', apply);
                apply();
            });
        });
    </script>
</x-app-layout>
