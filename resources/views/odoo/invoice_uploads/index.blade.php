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

    <div class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</div>
        @endif

        @unless($lectorEncendido)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                La IA local está apagada en este entorno: las facturas se guardan, pero hay que llenarlas a mano.
            </div>
        @endunless

        <form method="POST" action="{{ route('odoo.invoice_uploads.store') }}" enctype="multipart/form-data"
            x-data="{ nombre: '', enviando: false }" @submit="enviando = true"
            class="rounded-2xl border-2 border-dashed border-cyan-300 bg-white p-6 text-center shadow-sm dark:border-cyan-800 dark:bg-slate-900">
            @csrf
            <label class="block cursor-pointer">
                <svg class="mx-auto h-10 w-10 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="mt-2 block text-sm font-extrabold text-slate-900 dark:text-white">Elige el PDF o saca una foto de la factura</span>
                <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400" x-text="nombre || 'PDF, JPG o PNG, hasta 15 MB'"></span>
                <input type="file" name="documento" accept="application/pdf,image/jpeg,image/png" class="sr-only" required
                    @change="nombre = $event.target.files[0]?.name || ''">
            </label>
            @error('documento')
                <p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>
            @enderror
            <button type="submit" :disabled="!nombre || enviando"
                class="mt-4 inline-flex min-h-10 items-center justify-center rounded-xl bg-cyan-600 px-5 text-sm font-extrabold text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-40">
                <span x-text="enviando ? 'Subiendo…' : 'Subir y leer'"></span>
            </button>
            <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400">
                Se crea en Odoo <span class="font-bold">{{ $destino ?: 'sin configurar' }}</span>, siempre como borrador.
            </p>
        </form>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-extrabold text-slate-900 dark:border-slate-800 dark:text-white">Subidas</h2>
            @forelse($facturas as $f)
                @php
                    $color = match ($f->status) {
                        'sent' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                        'read' => 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
                        'failed' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
                        default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                    };
                @endphp
                <a href="{{ route('odoo.invoice_uploads.show', $f) }}"
                    class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 transition last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-slate-900 dark:text-white">
                            {{ $f->dato('razon_social') ?: $f->original_name }}
                            @if($f->dato('folio'))
                                <span class="font-semibold text-slate-500">· N° {{ $f->dato('folio') }}</span>
                            @endif
                        </p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ $f->created_at->format('d-m-Y H:i') }} · {{ $f->uploader_name_snapshot }}
                            @if($f->dato('total'))
                                · ${{ number_format((float) $f->dato('total'), 0, ',', '.') }}
                            @endif
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $color }}">
                        {{ $f->isSent() ? $f->odoo_move_name : $f->statusLabel() }}
                    </span>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">Todavía no se ha subido ninguna.</p>
            @endforelse
        </section>
    </div>
</x-app-layout>
