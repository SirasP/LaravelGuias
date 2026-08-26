<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Proveedores</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Identificados por su RUT</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-8xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav', ['status' => null])

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

        <p class="text-sm text-slate-600 dark:text-slate-400">{{ $catalogs['proveedores']['hint'] }}</p>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if($sinNombre > 0)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-900/60 dark:bg-amber-950/40">
                <p class="text-sm font-extrabold text-amber-900 dark:text-amber-100">
                    ⚠ {{ $sinNombre }} {{ \Illuminate\Support\Str::plural('proveedor', $sinNombre) }} sin nombre
                </p>
                <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                    Aparecieron al leer un documento, pero su nombre estaba dentro del logo y no se pudo leer.
                    Complétalos y las próximas cotizaciones los reconocerán solas.
                </p>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-1">
                <h2 class="font-extrabold text-slate-900 dark:text-white">Agregar proveedor</h2>

                <form method="POST" action="{{ route('purchase_catalogs.suppliers.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label for="tax_id" class="block text-sm font-bold text-slate-700 dark:text-slate-200">
                            RUT <span class="text-rose-500">*</span>
                        </label>
                        <input id="tax_id" name="tax_id" value="{{ old('tax_id') }}" required placeholder="77.045.469-7"
                            class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Se comprueba el dígito verificador. Con o sin puntos, da igual.</p>
                        @error('tax_id') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-bold text-slate-700 dark:text-slate-200">
                            Razón social <span class="text-rose-500">*</span>
                        </label>
                        <input id="name" name="name" value="{{ old('name') }}" required placeholder="RODASERVIC SPA"
                            class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @error('name') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="trade_name" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Nombre de fantasía</label>
                        <input id="trade_name" name="trade_name" value="{{ old('trade_name') }}" placeholder="Opcional"
                            class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Si lo pones, es el que se muestra en las solicitudes.</p>
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Correo</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="Opcional"
                            class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @error('email') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="min-h-11 w-full rounded-xl bg-blue-600 px-4 text-sm font-extrabold text-white hover:bg-blue-700">
                        Agregar
                    </button>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800">
                    <h2 class="font-extrabold text-slate-900 dark:text-white">Proveedores registrados</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        El RUT no se edita: es la identidad del proveedor. Si está errado, desactiva y crea el correcto.
                    </p>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($suppliers as $s)
                        <div x-data="{ editando: false }" class="p-4 {{ $s->is_active ? '' : 'bg-slate-50 dark:bg-slate-950/40' }}">
                            <div x-show="!editando" class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                                        @if($s->needsName())
                                            <span class="text-amber-700 dark:text-amber-300">Sin nombre</span>
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">completar</span>
                                        @else
                                            {{ $s->displayName() }}
                                        @endif
                                        @unless($s->is_active)
                                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-200">⊘ Desactivado</span>
                                        @endunless
                                    </p>
                                    <p class="mt-0.5 font-mono text-xs text-slate-600 dark:text-slate-400">{{ $s->formattedTaxId() }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        @if($s->trade_name && $s->name) razón social: {{ $s->name }} · @endif
                                        @if($s->email) {{ $s->email }} · @endif
                                        {{ $s->source === 'documento' ? 'detectado en un documento' : 'cargado a mano' }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button type="button" @click="editando = true"
                                        class="min-h-11 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">Editar</button>
                                    <form method="POST" action="{{ route('purchase_catalogs.suppliers.toggle', $s->id) }}">
                                        @csrf
                                        <button type="submit" class="min-h-11 rounded-xl border px-3 text-xs font-bold {{ $s->is_active
                                            ? 'border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300'
                                            : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300' }}">
                                            {{ $s->is_active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <form x-show="editando" x-cloak method="POST" action="{{ route('purchase_catalogs.suppliers.update', $s->id) }}" class="space-y-2">
                                @csrf @method('PUT')
                                <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $s->formattedTaxId() }}</p>
                                <input name="name" value="{{ $s->name }}" required placeholder="Razón social"
                                    class="min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <input name="trade_name" value="{{ $s->trade_name }}" placeholder="Nombre de fantasía (opcional)"
                                    class="min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <input name="email" type="email" value="{{ $s->email }}" placeholder="Correo (opcional)"
                                    class="min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <div class="flex gap-2">
                                    <button type="submit" class="min-h-11 flex-1 rounded-xl bg-blue-600 text-xs font-extrabold text-white hover:bg-blue-700">Guardar</button>
                                    <button type="button" @click="editando = false" class="min-h-11 flex-1 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    @empty
                        <div class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                            Todavía no hay proveedores. Se van agregando solos cuando el asistente lee una cotización,
                            o puedes cargarlos aquí.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
