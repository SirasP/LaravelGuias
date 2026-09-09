<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6H16a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Leer una cotización</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Sube el PDF o la foto y se arma el borrador</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-full space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav', ['status' => null])

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
                {{ session('error') }}
            </div>
        @endif

        {{-- Mientras haya algo leyéndose, la página se refresca sola: así el
             estado avanza a la vista sin que nadie tenga que recargar. --}}
        @if($hayEnProceso && ! $procesadorDetenido)
            <div class="flex items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-900/60 dark:bg-blue-950/40"
                x-data x-init="setTimeout(() => window.location.reload(), 5000)">
                <span class="inline-block h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-blue-600" aria-hidden="true"></span>
                <p class="text-sm font-medium text-blue-900 dark:text-blue-200">
                    Estamos leyendo un documento. Esta página se actualiza sola; puedes cerrarla y te avisamos igual.
                </p>
            </div>
        @endif

        @if($procesadorDetenido)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-900/60 dark:bg-amber-950/40">
                <p class="text-sm font-extrabold text-amber-900 dark:text-amber-100">
                    ⚠ Hay un documento esperando hace rato
                </p>
                <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                    Normalmente se lee en segundos. Si sigue así, es que el procesador de tareas no está corriendo.
                    En el servidor lo levanta PM2; en un equipo de desarrollo se arranca con:
                </p>
                <code class="mt-2 block rounded-lg bg-white px-3 py-2 font-mono text-xs text-slate-800 dark:bg-slate-950 dark:text-slate-200">php artisan queue:work</code>
                <p class="mt-2 text-xs text-amber-800 dark:text-amber-200">
                    El documento no se perdió: en cuanto el procesador arranque, se lee y te llega el aviso.
                </p>
            </div>
        @endif

        {{-- El formulario arriba y la tabla a lo ancho, no uno al lado del otro:
             partir la pantalla en un tercio y dos tercios dejaba las columnas
             tan estrechas que los nombres de archivo se rompían en tres líneas,
             y era eso lo que hacía crecer la lista hacia abajo. --}}
        <div class="space-y-5">
            {{-- La subida ocupa una franja, no media pantalla: es una acción de
                 dos segundos y lo que uno viene a mirar es la lista. --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">

                @if(! $readerEnabled)
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-semibold text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                        ⊘ El asistente está apagado en este entorno. El formulario manual funciona igual.
                    </div>
                @endif

                <form method="POST" action="{{ route('purchase_requests.ingestions.store') }}"
                    enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row sm:items-center"
                    x-data="{ enviando: false, archivo: '' }"
                    @submit="enviando = true">
                    @csrf
                    {{-- El input nativo se pinta solo y mal: el navegador escribe
                         «Sin archivos seleccionados» y recorta el nombre a media
                         palabra. Se esconde y se dibuja la etiqueta. --}}
                    <label for="document"
                        class="flex min-h-12 min-w-0 flex-1 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-4 transition hover:border-blue-400 hover:bg-blue-50/50 dark:border-slate-700 dark:bg-slate-950/40 dark:hover:border-blue-600">
                        <svg class="h-5 w-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" /></svg>
                        <span class="min-w-0 truncate text-sm font-bold text-slate-700 dark:text-slate-200"
                            x-text="archivo || 'Elegir el PDF o la foto de la cotización'"></span>
                        <span class="ml-auto hidden shrink-0 text-xs text-slate-400 sm:block">PDF, JPG o PNG · 15 MB</span>
                    </label>
                    <input id="document" type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png"
                        @change="archivo = $event.target.files[0]?.name ?? ''"
                        class="sr-only">

                    <button type="submit" :disabled="enviando || {{ $readerEnabled ? 'false' : 'true' }}"
                        class="min-h-12 shrink-0 rounded-xl bg-blue-600 px-6 text-sm font-extrabold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!enviando">Subir y leer</span>
                        <span x-show="enviando" x-cloak>Subiendo…</span>
                    </button>
                </form>
                @error('document') <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror

                <p class="mt-2.5 text-xs text-slate-500 dark:text-slate-400">
                    Se lee por detrás: puedes cerrar la página. <strong>Sólo prepara un borrador</strong> —nada se envía a
                    revisión hasta que tú lo confirmes— y si no logra leer una cantidad la deja vacía en vez de inventarla.
                </p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="font-extrabold text-slate-900 dark:text-white">Documentos leídos</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $ingestions->total() }} en total · el archivo y lo que entendió la IA quedan guardados
                        </p>
                    </div>
                    {{-- Qué modelo leyó: importa cuando algo sale raro, pero no
                         es lo primero que alguien viene a ver aquí. --}}
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-[11px] text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        {{ $readerDescription }}
                    </span>
                </div>

                {{-- Una tabla y no tarjetas: con diecinueve documentos la lista
                     se iba hacia abajo sin fin y había que hacer scroll para
                     saber si algo falló. Aquí cada documento es un renglón y
                     los avisos se abren sólo si alguien los pide. --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200/80 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3 font-bold">Documento</th>
                                <th class="w-40 whitespace-nowrap px-4 py-3 font-bold">Estado</th>
                                <th class="w-44 whitespace-nowrap px-4 py-3 font-bold">Fue a parar a</th>
                                <th class="w-32 whitespace-nowrap px-4 py-3 text-right font-bold">Subido</th>
                                <th class="w-px whitespace-nowrap px-4 py-3 text-right font-bold">Acciones</th>
                            </tr>
                        </thead>
                        {{-- Un tbody por documento, no uno para todos: el detalle
                             es una fila hermana de la principal, y con el x-data
                             en la fila quedaba fuera de su alcance —el
                             desplegable no abría nunca—. El tbody es el único
                             elemento que puede envolver a las dos sin romper la
                             tabla. --}}
                        @forelse($ingestions as $ingestion)
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80" x-data="{ abierto: false }">
                                @php
                                    $avisos = filled($ingestion->warnings) ? $ingestion->warnings : [];
                                    $dictada = $ingestion->source_kind === \App\Services\PurchaseRequests\Reading\PurchaseRequestSourceKind::TEXT;
                                    $conProblema = in_array($ingestion->status, ['failed', 'needs_review'], true);
                                @endphp
                                <tr class="align-middle transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-3">
                                        <p class="font-bold text-slate-800 dark:text-slate-100">{{ $ingestion->original_name }}</p>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            @if(filled($ingestion->supplier_name))
                                                <span class="font-semibold text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($ingestion->supplier_name, 32) }}</span>
                                            @endif
                                            <span class="tabular-nums">{{ number_format($ingestion->size / 1024, 0, ',', '.') }} KB</span>
                                            @if($ingestion->duration_ms)
                                                <span class="tabular-nums">· {{ number_format($ingestion->duration_ms / 1000, 1, ',', '.') }} s</span>
                                            @endif
                                            @if($dictada)
                                                <span class="rounded bg-indigo-100 px-1.5 font-bold text-indigo-800 dark:bg-indigo-950/80 dark:text-indigo-300">escrita a mano</span>
                                            @endif
                                            @if($avisos !== [])
                                                <button type="button" @click="abierto = !abierto"
                                                    class="rounded bg-amber-100 px-1.5 font-bold text-amber-900 hover:bg-amber-200 dark:bg-amber-950/60 dark:text-amber-300">
                                                    ⚠ {{ count($avisos) }} {{ count($avisos) === 1 ? 'aviso' : 'avisos' }}
                                                    <span x-text="abierto ? '▴' : '▾'"></span>
                                                </button>
                                            @endif
                                        </p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-bold
                                            @class([
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' => $ingestion->status === 'completed',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' => $ingestion->status === 'needs_review',
                                                'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300' => $ingestion->status === 'failed',
                                                'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300' => $ingestion->status === 'waiting',
                                                'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => in_array($ingestion->status, ['pending','processing'], true),
                                            ])">
                                            {{ $ingestion->statusIcon() }} {{ $ingestion->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs">
                                        @if($ingestion->purchaseRequest)
                                            <a href="{{ route('purchase_requests.edit', $ingestion->purchaseRequest) }}"
                                                class="font-mono font-bold text-blue-700 underline decoration-dotted underline-offset-2 hover:text-blue-900 dark:text-blue-400">
                                                {{ $ingestion->purchaseRequest->folio }}
                                            </a>
                                            <span class="ml-1.5 text-[11px] text-slate-400">borrador</span>
                                        @elseif($ingestion->comparedRequest)
                                            <a href="{{ route('purchase_requests.show', $ingestion->comparedRequest) }}"
                                                class="font-mono font-bold text-sky-700 underline decoration-dotted underline-offset-2 hover:text-sky-900 dark:text-sky-400">
                                                {{ $ingestion->comparedRequest->folio }}
                                            </a>
                                            <span class="ml-1.5 text-[11px] text-slate-400">comparada</span>
                                        @else
                                            <span class="text-slate-300 dark:text-slate-600">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-xs tabular-nums text-slate-500 dark:text-slate-400">
                                        {{ $ingestion->created_at?->format('d-m-Y H:i') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('purchase_requests.ingestions.download', $ingestion) }}"
                                                class="inline-flex min-h-9 items-center rounded-xl border border-slate-200 px-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">
                                                {{ $dictada ? 'Ver texto' : 'Ver' }}
                                            </a>
                                            @if($conProblema)
                                                <form method="POST" action="{{ route('purchase_requests.ingestions.reread', $ingestion) }}">
                                                    @csrf
                                                    <button type="submit"
                                                        class="inline-flex min-h-9 items-center rounded-xl border border-violet-300 px-2.5 text-xs font-bold text-violet-800 hover:bg-violet-50 dark:border-violet-800 dark:text-violet-300">
                                                        Releer
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                @if($avisos !== [] || ($ingestion->status === 'failed' && $ingestion->error_message) || $ingestion->status === 'waiting')
                                    <tr x-show="abierto" x-cloak class="bg-slate-50/60 dark:bg-slate-950/40">
                                        <td colspan="5" class="px-4 pb-3 pt-0">
                                            @if($ingestion->status === 'waiting')
                                                <p class="rounded-xl bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:bg-sky-950/40 dark:text-sky-200">
                                                    El asistente de lectura no está disponible ahora mismo.
                                                    Este documento se leerá solo en cuanto vuelva; no hace falta subirlo de nuevo.
                                                </p>
                                            @endif
                                            @if($ingestion->status === 'failed' && $ingestion->error_message)
                                                <p class="rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:bg-rose-950/40 dark:text-rose-200">
                                                    {{ $ingestion->error_message }}
                                                </p>
                                            @endif
                                            @if($avisos !== [])
                                                <ul class="mt-1 list-inside list-disc space-y-0.5 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                                    @foreach($avisos as $aviso)
                                                        <li>{{ $aviso }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        @empty
                            <tbody>
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                        Todavía no has subido ninguna cotización.
                                    </td>
                                </tr>
                            </tbody>
                        @endforelse
                    </table>
                </div>

                @if($ingestions->hasPages())
                    <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $ingestions->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
