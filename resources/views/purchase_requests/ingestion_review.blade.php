<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <a href="{{ route('purchase_requests.ingestions.index') }}"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                aria-label="Volver">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">{{ $ingestion->original_name }}</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Revisa lo leído antes de crear la solicitud</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-8xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav', ['status' => null])

        @foreach(['success' => 'emerald', 'info' => 'blue', 'error' => 'rose'] as $tipo => $color)
            @if(session($tipo))
                <div class="rounded-2xl border border-{{ $color }}-200 bg-{{ $color }}-50 px-4 py-3 text-sm font-medium text-{{ $color }}-800 dark:border-{{ $color }}-900/60 dark:bg-{{ $color }}-950/40 dark:text-{{ $color }}-200">
                    {{ session($tipo) }}
                </div>
            @endif
        @endforeach

        {{-- Estado de la lectura --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold
                            @class([
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' => $ingestion->status === 'completed',
                                'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' => $ingestion->status === 'needs_review',
                                'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300' => $ingestion->status === 'failed',
                                'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => in_array($ingestion->status, ['pending','processing'], true),
                            ])">
                            {{ $ingestion->statusIcon() }} {{ $ingestion->statusLabel() }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $ingestion->created_at?->format('d-m-Y H:i') }}
                            @if($ingestion->duration_ms) · leído en {{ number_format($ingestion->duration_ms / 1000, 1, ',', '.') }} s @endif
                            @if($ingestion->model_used) · {{ $ingestion->model_used }} @endif
                        </span>
                    </p>

                    @if($ingestion->supplier_tax_id)
                        <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">
                            <span class="font-bold">Proveedor:</span>
                            {{ $ingestion->supplier_name ?: 'sin nombre' }}
                            <span class="font-mono text-xs text-slate-500 dark:text-slate-400">· RUT {{ \App\Support\Rut::format($ingestion->supplier_tax_id) }}</span>
                        </p>
                    @endif

                    @if($ingestion->customer_matches_company === false)
                        <p class="mt-1 text-sm font-bold text-rose-700 dark:text-rose-300">
                            ⚠ Este documento va dirigido a otra empresa. Verifica que corresponda.
                        </p>
                    @endif
                </div>

                <div class="flex shrink-0 gap-2">
                    <a href="{{ route('purchase_requests.ingestions.download', $ingestion) }}"
                        class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">
                        Ver documento
                    </a>
                    @if($ingestion->purchase_request_id === null && $ingestion->isFinished())
                        <form method="POST" action="{{ route('purchase_requests.ingestions.reread', $ingestion) }}">
                            @csrf
                            <button type="submit" class="min-h-11 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">
                                Leer de nuevo
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if(filled($ingestion->warnings))
                <div class="mt-3 rounded-xl bg-amber-50 px-3 py-2 dark:bg-amber-950/40">
                    <p class="text-xs font-bold text-amber-900 dark:text-amber-200">Revisa esto</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs text-amber-800 dark:text-amber-200">
                        @foreach($ingestion->warnings as $aviso) <li>{{ $aviso }}</li> @endforeach
                    </ul>
                </div>
            @endif

            @if($ingestion->status === 'failed')
                <p class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:bg-rose-950/40 dark:text-rose-200">
                    {{ $ingestion->error_message }}
                </p>
            @endif
        </section>

        @if($ingestion->purchase_request_id !== null)
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/40">
                <p class="font-extrabold text-emerald-900 dark:text-emerald-100">Ya se creó la solicitud {{ $ingestion->purchaseRequest?->folio }}</p>
                <a href="{{ route('purchase_requests.show', $ingestion->purchaseRequest) }}"
                    class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-emerald-600 px-4 text-sm font-extrabold text-white hover:bg-emerald-700">
                    Ver la solicitud
                </a>
            </section>

        @elseif(in_array($ingestion->status, ['pending', 'processing'], true))
            <section class="flex items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4 dark:border-blue-900/60 dark:bg-blue-950/40"
                x-data x-init="setTimeout(() => window.location.reload(), 5000)">
                <span class="inline-block h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-blue-600" aria-hidden="true"></span>
                <p class="text-sm font-medium text-blue-900 dark:text-blue-200">
                    Leyendo el documento. Esta página se actualiza sola.
                </p>
            </section>

        @elseif(count($items) > 0)
            {{-- La tabla editable: nada se guarda hasta que se aprieta el botón --}}
            <form method="POST" action="{{ route('purchase_requests.ingestions.confirm', $ingestion) }}"
                x-data="{ filas: {{ count($items) }} }">
                @csrf

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800">
                        <h2 class="font-extrabold text-slate-900 dark:text-white">Lo que se leyó del documento</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Corrige lo que haga falta. Para quitar una partida, borra su nombre.
                            Nada se guarda hasta que crees la solicitud.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[64rem] text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950/50 dark:text-slate-400">
                                <tr>
                                    <th class="px-3 py-2 text-left font-bold" style="width:2.5rem">N°</th>
                                    <th class="px-3 py-2 text-left font-bold">Producto / Servicio</th>
                                    <th class="px-3 py-2 text-left font-bold" style="width:12rem">Especificación</th>
                                    <th class="px-3 py-2 text-left font-bold" style="width:7rem">Cantidad</th>
                                    <th class="px-3 py-2 text-left font-bold" style="width:9rem">Unidad</th>
                                    <th class="px-3 py-2 text-left font-bold" style="width:9rem">Precio unit.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($items as $i => $item)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-400">{{ $i + 1 }}</td>
                                        <td class="px-2 py-2">
                                            <input name="items[{{ $i }}][product_service]" value="{{ $item['product_service'] ?? '' }}"
                                                class="min-h-11 w-full rounded-lg border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input name="items[{{ $i }}][specification]" value="{{ $item['specification'] ?? '' }}"
                                                class="min-h-11 w-full rounded-lg border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? '' }}" inputmode="decimal"
                                                class="min-h-11 w-full rounded-lg border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input name="items[{{ $i }}][unit]" value="{{ $item['unit'] ?? '' }}" list="units-review"
                                                class="min-h-11 w-full rounded-lg border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        </td>
                                        <td class="px-2 py-2">
                                            {{-- Vacío cuando la cotización no lo traía, o
                                                 cuando el precio leído no aparecía escrito. --}}
                                            <input name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" inputmode="decimal"
                                                placeholder="—"
                                                class="min-h-11 w-full rounded-lg border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <datalist id="units-review">
                        @foreach($units as $u) <option value="{{ $u->name }}"></option> @endforeach
                    </datalist>
                </section>

                <section class="mt-5 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-2">
                    <div>
                        <label for="department" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Área o departamento</label>
                        <select id="department" name="department"
                            class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <option value="">La que uso siempre</option>
                            @foreach($departments as $d) <option value="{{ $d->name }}">{{ $d->name }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="reason" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Motivo de la compra</label>
                        <input id="reason" name="reason"
                            value="{{ old('reason', $ingestion->extracted['reason'] ?? '') }}"
                            placeholder="Para qué se necesita"
                            class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Si lo dejas vacío se anota el proveedor y lo completas después.</p>
                    </div>
                </section>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <a href="{{ route('purchase_requests.ingestions.index') }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-5 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">
                        Volver sin crear nada
                    </a>
                    <button type="submit"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-6 text-sm font-extrabold text-white hover:bg-blue-700">
                        Crear la solicitud con estas partidas
                    </button>
                </div>
            </form>
        @else
            <section class="rounded-2xl border border-slate-200 bg-white p-6 text-center dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    No se reconoció ninguna partida en este documento.
                </p>
            </section>
        @endif
    </div>
</x-app-layout>
