<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Catálogos de solicitudes</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Áreas, unidades, centros de costo y lugares</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-8xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav', ['status' => null])

        {{-- Selector de catálogo --}}
        <nav class="flex flex-wrap gap-2" aria-label="Catálogo a editar">
            @foreach($catalogs as $key => $meta)
                <a href="{{ route('purchase_catalogs.index', $key) }}"
                    @if($key === $catalog) aria-current="page" @endif
                    class="inline-flex min-h-11 items-center rounded-xl px-4 text-sm font-bold transition
                        {{ $key === $catalog
                            ? 'bg-blue-600 text-white shadow-sm'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300' }}">
                    {{ $meta['plural'] }}
                </a>
            @endforeach
        </nav>

        <p class="text-sm text-slate-600 dark:text-slate-400">{{ $config['hint'] }}</p>

        <div class="grid gap-5 lg:grid-cols-3">
            {{-- Alta --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-1">
                <h2 class="font-extrabold text-slate-900 dark:text-white">Agregar {{ mb_strtolower($config['singular']) }}</h2>

                <form method="POST" action="{{ route('purchase_catalogs.store', $catalog) }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-bold text-slate-700 dark:text-slate-200">
                            Nombre <span class="text-rose-500">*</span>
                        </label>
                        <input id="name" name="name" value="{{ old('name') }}" required
                            class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            placeholder="{{ $isUnit ? 'Ej. Cajas' : 'Ej. Riego' }}">
                        @error('name') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    @if($isUnit)
                        <div>
                            <label for="code" class="block text-sm font-bold text-slate-700 dark:text-slate-200">
                                Abreviatura <span class="text-rose-500">*</span>
                            </label>
                            <input id="code" name="code" value="{{ old('code') }}" required maxlength="40"
                                class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                placeholder="Ej. caja">
                            @error('code') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <label class="flex min-h-11 items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                            <input type="checkbox" name="allows_decimals" value="1" @checked(old('allows_decimals', true))
                                class="h-4 w-4 rounded border-slate-400 text-blue-600 focus:ring-blue-500">
                            Admite decimales (por ejemplo 1,5)
                        </label>
                    @endif

                    <div>
                        <label for="sort_order" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Orden en la lista</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', 0) }}"
                            class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Menor número aparece primero. Con el mismo número se ordena alfabéticamente.</p>
                    </div>

                    <button type="submit"
                        class="min-h-11 w-full rounded-xl bg-blue-600 px-4 text-sm font-extrabold text-white hover:bg-blue-700">
                        Agregar
                    </button>
                </form>
            </section>

            {{-- Listado --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800">
                    <h2 class="font-extrabold text-slate-900 dark:text-white">{{ $config['plural'] }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Las entradas se desactivan, nunca se borran: las solicitudes ya emitidas conservan el nombre que tenían.
                    </p>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($entries as $entry)
                        <div x-data="{ editando: false }" class="p-4 {{ $entry->is_active ? '' : 'bg-slate-50 dark:bg-slate-950/40' }}">
                            <div x-show="!editando" class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                                        {{ $entry->name }}
                                        @if($isUnit)
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $entry->code }}</span>
                                        @endif
                                        @unless($entry->is_active)
                                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                                ⊘ Desactivada
                                            </span>
                                        @endunless
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        clave: {{ $entry->slug }} · orden {{ $entry->sort_order }}
                                        @if($isUnit) · {{ $entry->allows_decimals ? 'admite decimales' : 'sólo enteros' }} @endif
                                    </p>
                                </div>

                                <div class="flex shrink-0 gap-2">
                                    <button type="button" @click="editando = true"
                                        class="min-h-11 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">
                                        Editar
                                    </button>
                                    <form method="POST" action="{{ route('purchase_catalogs.toggle', [$catalog, $entry->id]) }}">
                                        @csrf
                                        <button type="submit"
                                            class="min-h-11 rounded-xl border px-3 text-xs font-bold {{ $entry->is_active
                                                ? 'border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300'
                                                : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300' }}">
                                            {{ $entry->is_active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <form x-show="editando" x-cloak method="POST"
                                action="{{ route('purchase_catalogs.update', [$catalog, $entry->id]) }}"
                                class="grid gap-2 sm:grid-cols-[1fr_auto_auto]">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $entry->name }}" required
                                    class="min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                @if($isUnit)
                                    <input name="code" value="{{ $entry->code }}" required
                                        class="min-h-11 w-28 rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                    <input type="hidden" name="allows_decimals" value="{{ $entry->allows_decimals ? 1 : 0 }}">
                                @endif
                                <input name="sort_order" type="number" min="0" value="{{ $entry->sort_order }}"
                                    class="min-h-11 w-24 rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <div class="flex gap-2 sm:col-span-full">
                                    <button type="submit" class="min-h-11 flex-1 rounded-xl bg-blue-600 px-3 text-xs font-extrabold text-white hover:bg-blue-700">Guardar</button>
                                    <button type="button" @click="editando = false" class="min-h-11 flex-1 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    @empty
                        <div class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
                            Todavía no hay entradas. Agrega la primera con el formulario de la izquierda.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
