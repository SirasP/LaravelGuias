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

    <div class="mx-auto max-w-8xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
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

        <div class="grid gap-5 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-1">
                <h2 class="font-extrabold text-slate-900 dark:text-white">Subir documento</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    PDF o foto de una cotización. Se lee por detrás: puedes cerrar esta página y seguir trabajando.
                </p>

                @if(! $readerEnabled)
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-semibold text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                        ⊘ El asistente está apagado en este entorno. El formulario manual funciona igual.
                    </div>
                @endif

                <form method="POST" action="{{ route('purchase_requests.ingestions.store') }}"
                    enctype="multipart/form-data" class="mt-4 space-y-3"
                    x-data="{ enviando: false, archivo: '' }"
                    @submit="enviando = true">
                    @csrf
                    <div>
                        <label for="document" class="block text-sm font-bold text-slate-700 dark:text-slate-200">
                            Documento <span class="text-rose-500">*</span>
                        </label>
                        <input id="document" type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png"
                            @change="archivo = $event.target.files[0]?.name ?? ''"
                            class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:min-h-11 file:rounded-xl file:border-0 file:bg-blue-600 file:px-4 file:text-sm file:font-bold file:text-white hover:file:bg-blue-700 dark:text-slate-300">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">PDF, JPG o PNG. Hasta 15 MB.</p>
                        @error('document') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" :disabled="enviando || {{ $readerEnabled ? 'false' : 'true' }}"
                        class="min-h-11 w-full rounded-xl bg-blue-600 px-4 text-sm font-extrabold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!enviando">Subir y leer</span>
                        <span x-show="enviando" x-cloak>Subiendo…</span>
                    </button>
                </form>

                <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    El asistente <strong>sólo prepara un borrador</strong>. Nada se envía a revisión hasta que tú lo confirmes.
                    Si no logra leer una cantidad, la deja vacía en vez de inventarla.
                </p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800">
                    <h2 class="font-extrabold text-slate-900 dark:text-white">Documentos leídos</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Motor: {{ $readerDescription }}
                    </p>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($ingestions as $ingestion)
                        <div class="p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                                        <span class="truncate">{{ $ingestion->original_name }}</span>
                                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold
                                            @class([
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' => $ingestion->status === 'completed',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' => $ingestion->status === 'needs_review',
                                                'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300' => $ingestion->status === 'failed',
                                                'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300' => $ingestion->status === 'waiting',
                                                'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => in_array($ingestion->status, ['pending','processing'], true),
                                            ])">
                                            {{ $ingestion->statusIcon() }} {{ $ingestion->statusLabel() }}
                                        </span>
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $ingestion->created_at?->format('d-m-Y H:i') }}
                                        · {{ number_format($ingestion->size / 1024, 0, ',', '.') }} KB
                                        @if($ingestion->duration_ms) · leído en {{ number_format($ingestion->duration_ms / 1000, 1, ',', '.') }} s @endif
                                    </p>
                                </div>

                                <div class="flex shrink-0 gap-2">
                                    <a href="{{ route('purchase_requests.ingestions.download', $ingestion) }}"
                                        class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">
                                        Documento
                                    </a>
                                    @if($ingestion->purchaseRequest)
                                        <a href="{{ route('purchase_requests.edit', $ingestion->purchaseRequest) }}"
                                            class="inline-flex min-h-11 items-center rounded-xl bg-blue-600 px-3 text-xs font-extrabold text-white hover:bg-blue-700">
                                            Revisar {{ $ingestion->purchaseRequest->folio }}
                                        </a>
                                    @endif
                                </div>
                            </div>

                            {{-- Esperar no es fallar: no hay nada que hacer ni nada
                                 que reintentar a mano, y decirlo evita que alguien
                                 vuelva a subir el mismo archivo creyendo que se perdió. --}}
                            @if($ingestion->status === 'waiting')
                                <p class="mt-2 rounded-xl bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:bg-sky-950/40 dark:text-sky-200">
                                    El asistente de lectura no está disponible ahora mismo.
                                    Este documento se leerá solo en cuanto vuelva; no hace falta subirlo de nuevo.
                                </p>
                            @endif

                            @if($ingestion->status === 'failed' && $ingestion->error_message)
                                <p class="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:bg-rose-950/40 dark:text-rose-200">
                                    {{ $ingestion->error_message }}
                                </p>
                            @endif

                            @if(filled($ingestion->warnings))
                                <div class="mt-2 rounded-xl bg-amber-50 px-3 py-2 dark:bg-amber-950/40">
                                    <p class="text-xs font-bold text-amber-900 dark:text-amber-200">Revisa antes de enviar</p>
                                    <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs text-amber-800 dark:text-amber-200">
                                        @foreach($ingestion->warnings as $aviso)
                                            <li>{{ $aviso }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                            Todavía no has subido ninguna cotización.
                        </div>
                    @endforelse
                </div>

                @if($ingestions->hasPages())
                    <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $ingestions->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
