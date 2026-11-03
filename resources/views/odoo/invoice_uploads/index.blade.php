<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-950/50 dark:text-cyan-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Subir factura</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Las que no llegaron a Odoo: la IA local la lee y tú la revisas</p>
            </div>
        </div>
    </x-slot>

    @php
        $peso = fn ($v) => '$'.number_format((float) $v, 0, ',', '.');
        $yaEn = $facturas->filter(fn ($f) => $f->yaEnOdoo() !== null);
        $grupos = [
            ['Por revisar', $facturas->filter(fn ($f) => $f->isEditable() && $f->yaEnOdoo() === null)->count(), 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200'],
            ['Ya estaban en Odoo', $yaEn->count(), 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200'],
            ['Creadas en Odoo', $facturas->filter->isSent()->count(), 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200'],
            ['Leyendo', $facturas->filter->isReading()->count(), 'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-900/50 dark:bg-cyan-950/30 dark:text-cyan-200'],
        ];
    @endphp

    <div class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</div>
        @endif

        @unless($lectorEncendido)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                La IA local está apagada en este entorno: las facturas se guardan, pero hay que llenarlas a mano.
            </div>
        @endunless

        {{-- Elegir el archivo ya lo sube: no hay un segundo botón que olvidar. --}}
        <form method="POST" action="{{ route('odoo.invoice_uploads.store') }}" enctype="multipart/form-data"
            x-data="{ encima: false, enviando: false, subir(archivos) { if (! archivos?.length) return; const dt = new DataTransfer(); dt.items.add(archivos[0]); this.$refs.documento.files = dt.files; this.enviando = true; this.$refs.documento.form.submit() } }"
            @dragover.prevent="encima = true" @dragleave.prevent="encima = false" @drop.prevent="encima = false; subir($event.dataTransfer.files)"
            :class="encima ? 'border-cyan-500 bg-cyan-50 dark:bg-cyan-950/30' : 'border-cyan-300 bg-white dark:border-cyan-800 dark:bg-slate-900'"
            class="rounded-2xl border-2 border-dashed p-6 text-center shadow-sm transition sm:p-8">
            @csrf
            <input type="file" name="documento" x-ref="documento" accept="application/pdf,image/jpeg,image/png" class="sr-only" @change="subir($event.target.files)">
            <input type="file" x-ref="camara" accept="image/*" capture="environment" class="sr-only" @change="subir($event.target.files)">

            <template x-if="! enviando">
                <div>
                    <svg class="mx-auto h-11 w-11 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="mt-2 text-base font-extrabold text-slate-900 dark:text-white">Arrastra aquí el PDF o la foto de la factura</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">PDF, JPG o PNG, hasta 15 MB. Se sube y la IA la empieza a leer al tiro.</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <button type="button" @click="$refs.camara.click()"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-cyan-600 px-5 text-sm font-extrabold text-white hover:bg-cyan-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            Sacar foto
                        </button>
                        <button type="button" @click="$refs.documento.click()"
                            class="inline-flex min-h-11 items-center rounded-xl border border-cyan-300 bg-white px-5 text-sm font-extrabold text-cyan-800 hover:bg-cyan-50 dark:border-cyan-800 dark:bg-slate-900 dark:text-cyan-200">Elegir archivo</button>
                    </div>
                </div>
            </template>
            <template x-if="enviando">
                <p class="py-6 text-sm font-extrabold text-cyan-800 dark:text-cyan-200">Subiendo…</p>
            </template>

            @error('documento')
                <p class="mt-3 text-sm font-bold text-rose-600">{{ $message }}</p>
            @enderror
            <p class="mt-4 text-[11px] text-slate-500 dark:text-slate-400">
                Se crea en Odoo <span class="font-bold">{{ $destino ?: 'sin configurar' }}</span>, siempre como borrador. Si ya estaba, te avisa.
            </p>
        </form>

        @if($facturas->isNotEmpty())
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach($grupos as [$nombre, $cuantas, $color])
                    <div class="rounded-2xl border p-3 {{ $color }}">
                        <p class="text-[11px] font-bold uppercase tracking-wider opacity-80">{{ $nombre }}</p>
                        <p class="mt-1 text-2xl font-black tabular-nums">{{ $cuantas }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-extrabold text-slate-900 dark:border-slate-800 dark:text-white">Subidas</h2>
            @forelse($facturas as $f)
                @php
                    $ya = $f->yaEnOdoo();
                    [$chip, $color] = match (true) {
                        $ya !== null => ['Ya está en Odoo · '.$ya['name'], 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200'],
                        $f->isSent() => ['Creada · '.$f->odoo_move_name, 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'],
                        $f->status === 'read' => ['Por revisar', 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'],
                        $f->status === 'failed' => ['No se pudo leer', 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200'],
                        default => [$f->statusLabel(), 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/50 dark:text-cyan-200'],
                    };
                @endphp
                <a href="{{ route('odoo.invoice_uploads.show', $f) }}"
                    class="flex items-center gap-4 border-b border-slate-100 px-5 py-3.5 transition last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $f->dato('razon_social') ?: $f->original_name }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                            @if($f->dato('folio')) N° {{ $f->dato('folio') }} · @endif
                            @if($f->dato('fecha')) emitida {{ \Illuminate\Support\Carbon::parse($f->dato('fecha'))->format('d-m-Y') }} · @endif
                            subida por {{ $f->uploader_name_snapshot ?: '—' }} {{ $f->created_at->diffForHumans() }}
                        </p>
                    </div>
                    @if($f->dato('total'))
                        <p class="hidden shrink-0 text-sm font-black tabular-nums text-slate-900 sm:block dark:text-white">{{ $peso($f->dato('total')) }}</p>
                    @endif
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $color }}">{{ $chip }}</span>
                </a>
            @empty
                <p class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Todavía no se ha subido ninguna.</p>
            @endforelse
        </section>
    </div>
</x-app-layout>
