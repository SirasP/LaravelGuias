<x-app-layout>

    {{-- ================= HEADER ================= --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Entrada de stock (manual / stock inicial)
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-500">
                    Crea un lote FIFO y un movimiento de inventario.
                </p>
            </div>

            <a href="{{ route('inventario.stock') }}"
               class="hidden md:inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold
                      bg-gray-200 text-gray-900 hover:opacity-90
                      dark:bg-gray-700 dark:text-gray-100 transition">
                ← Volver
            </a>
        </div>
    </x-slot>

    {{-- ================= CONTENIDO ================= --}}
    <div class="py-6">
        <div class="max-w-screen-2xl mx-auto px-4">

            {{-- Errores --}}
            @if ($errors->any())
                <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl p-4 text-red-800 dark:text-red-200">
                    <ul class="list-disc ml-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- GRID: IZQ FORM / DER TIPS (igual que usuarios) --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- ================= IZQUIERDA (FORM GRANDE) ================= --}}
                <div class="lg:col-span-10">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl overflow-hidden">
                        <div class="p-6 text-gray-900 dark:text-gray-100">

                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-base font-semibold">Ingresar stock</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Úsalo para stock inicial o ajustes.
                                    </p>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('inventario.stock.entrada.store') }}" class="space-y-6">
                                @csrf

                                {{-- Bloque 1: Producto / Bodega --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Producto
                                        </label>
                                        <select name="producto_id" required
                                            class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                            @foreach($productos as $p)
                                                <option value="{{ $p->id }}" @selected((string)old('producto_id')===(string)$p->id)>
                                                    {{ $p->nombre }} {{ $p->sku ? "({$p->sku})" : "" }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Bodega
                                        </label>
                                        <select name="bodega_id" required
                                            class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                            @foreach($bodegas as $b)
                                                <option value="{{ $b->id }}" @selected((string)old('bodega_id')===(string)$b->id)>
                                                    {{ $b->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                {{-- Bloque 2: Cantidad / Costo / Fecha (en desktop se ve súper bien en 3 columnas) --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Cantidad
                                        </label>
                                        <input name="cantidad" value="{{ old('cantidad') }}" required
                                            class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                            placeholder="Ej: 100">
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Decimales si aplica (ej: 100.5)
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Costo unitario
                                        </label>
                                        <input name="costo_unitario" value="{{ old('costo_unitario', 0) }}" required
                                            class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                            placeholder="Ej: 900">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Fecha ingreso
                                        </label>
                                        <input type="datetime-local" name="ingresado_el" value="{{ old('ingresado_el') }}"
                                            class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Opcional (si vacío, usa ahora)
                                        </p>
                                    </div>
                                </div>

                                {{-- Bloque 3: Lote / Vencimiento / Notas --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Código lote
                                        </label>
                                        <input name="codigo_lote" value="{{ old('codigo_lote') }}"
                                            class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                            placeholder="Opcional">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Vence el
                                        </label>
                                        <input type="date" name="vence_el" value="{{ old('vence_el') }}"
                                            class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                    </div>

                                    <div class="lg:col-span-1 md:col-span-2 lg:col-span-1">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Notas
                                        </label>
                                        <textarea name="notas" rows="2"
                                            class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                            placeholder="Ej: Stock inicial">{{ old('notas') }}</textarea>
                                    </div>
                                </div>

                                {{-- Acciones --}}
                                <div class="pt-3 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
                                    <a href="{{ route('inventario.stock') }}"
                                       class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        Cancelar
                                    </a>
                                    <button type="submit"
                                            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">
                                        Guardar entrada
                                    </button>
                                </div>

                            </form>

                        </div>
                    </div>
                </div>

                {{-- ================= DERECHA (PANEL CHICO, como usuarios) ================= --}}
                <div class="hidden lg:block lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl lg:sticky lg:top-6">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-base font-semibold">Tips</h3>

                            <ul class="mt-3 text-sm text-gray-600 dark:text-gray-400 list-disc ml-5 space-y-2">
                                <li>Stock inicial o ajustes.</li>
                                <li>Crea lote FIFO.</li>
                                <li>Queda en kardex.</li>
                            </ul>

                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <a href="{{ route('inventario.stock') }}"
                                   class="w-full inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold
                                          bg-gray-200 text-gray-900 hover:opacity-90
                                          dark:bg-gray-700 dark:text-gray-100 transition">
                                    Volver
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

</x-app-layout>
