<x-app-layout>

    <x-slot name="header">
        <p class="text-sm text-gray-600 dark:text-gray-400 text-left">
            Productos del inventario
        </p>

        <button type="button" class="lg:hidden fixed bottom-6 right-6 z-[10000]
               h-14 w-14 rounded-full bg-blue-600 text-white text-3xl
               flex items-center justify-center shadow-lg
               hover:bg-blue-700 transition" onclick="document.getElementById('modalProducto').showModal()"
            aria-label="Agregar producto">
            +
        </button>
    </x-slot>

    {{-- ================= CONTENIDO ================= --}}
    <div class="py-6">
        <div class="max-w-full mx-auto px-4">

            {{-- Flash success con SweetAlert (si lo usas) --}}
            @php($ok = session()->pull('ok'))
            @if ($ok && request()->isMethod('GET'))
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Listo!',
                                text: @json($ok),
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    });
                </script>
            @endif

            @if ($errors->any())
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo guardar el producto',
                                html: `
                    <div style="text-align:left; max-height: 200px; overflow:auto;">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                `,
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#2563eb', // blue-600
                                showCloseButton: true,
                                focusConfirm: true,
                                width: 500,
                                backdrop: true
                            });
                        });
                    </script>
            @endif



            {{-- GRID: TABLA + TARJETA DERECHA --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- ================= TARJETA IZQUIERDA (TABLA) ================= --}}
                <div class="lg:col-span-10">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl overflow-hidden">
                        <div class="p-6 text-gray-900 dark:text-gray-100">

                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-base font-semibold">Listado</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Busca por nombre o SKU.
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <form method="GET" action="{{ route('inventario.productos') }}" class="flex gap-4">
                                        <input name="q" value="{{ $q }}" placeholder="Buscar..." class="w-full md:w-[600px] rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900
           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
           dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                        <button type="submit"
                                            class="px-6 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:opacity-90 transition">
                                            Buscar
                                        </button>
                                    </form>

                                    {{-- botón abrir modal (desktop también, opcional) --}}
                                    <button type="button" onclick="document.getElementById('modalProducto').showModal()"
                                        class="hidden md:inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold
                                               bg-blue-600 text-white hover:bg-blue-700 transition">
                                        + Agregar
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-900/60 text-gray-700 dark:text-gray-200">
                                        <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:font-semibold [&>th]:text-left">
                                            <th>ID</th>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th class="text-right">Stock actual</th>
                                            <th class="text-right">Costo prom.</th>
                                            <th>Estado</th>
                                            <th>Creado</th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @forelse ($productos as $p)
                                            <tr class="hover:bg-emerald-50/60 dark:hover:bg-emerald-900/10 transition cursor-pointer"
                                                onclick="window.location='{{ route('inventario.productos.show', $p->id) }}'">

                                                <td class="px-4 py-3 text-gray-500">#{{ $p->id }}</td>

                                                <td class="px-4 py-3">
                                                    <span class="inline-flex rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-1 text-xs font-mono">
                                                        {{ $p->codigo ?: '—' }}
                                                    </span>
                                                </td>

                                                <td class="px-4 py-3 font-medium">
                                                    <div class="flex items-center gap-1.5">
                                                        {{ $p->nombre }}
                                                        <svg class="w-3 h-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                        </svg>
                                                    </div>
                                                </td>

                                                <td class="px-4 py-3 text-right tabular-nums font-bold
                                                    {{ (float)$p->stock_actual > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-400' }}">
                                                    {{ number_format((float)$p->stock_actual, 2, ',', '.') }}
                                                </td>

                                                <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                                    {{ (float)$p->costo_promedio > 0 ? '$'.number_format((float)$p->costo_promedio, 0, ',', '.') : '—' }}
                                                </td>

                                                <td class="px-4 py-3" onclick="event.stopPropagation()">
                                                    <button type="button" role="switch"
                                                        aria-checked="{{ $p->is_active ? 'true' : 'false' }}"
                                                        class="toggle-producto relative inline-flex h-6 w-11 items-center rounded-full transition-colors
                                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                                        data-url="{{ route('inventario.productos.toggle', $p->id) }}"
                                                        data-state="{{ $p->is_active ? '1' : '0' }}">
                                                        <span class="thumb inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                                    </button>
                                                </td>

                                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                                    {{ $p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('d-m-Y H:i') : '—' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-12 text-gray-500">
                                                    No hay productos para mostrar.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">
                                {{ $productos->onEachSide(1)->links() }}
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ================= TARJETA DERECHA (CREAR PRODUCTO) ================= --}}
                <div class="hidden lg:block lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl lg:sticky lg:top-6">
                        <div class="p-6 text-gray-900 dark:text-gray-100">

                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-base font-semibold">Crear producto</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Completa los datos para registrar
                                    </p>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('inventario.productos.store') }}" class="space-y-4">
                                @csrf

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Nombre
                                    </label>
                                    <input name="nombre" value="{{ old('nombre') }}" required class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                               dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                        placeholder="Ej: Diésel">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        SKU
                                    </label>
                                    <input name="sku" value="{{ old('sku') }}" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                               dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                        placeholder="Ej: DIESEL">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Descripción
                                    </label>
                                    <textarea name="descripcion" rows="2" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                               dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                        placeholder="Opcional">{{ old('descripcion') }}</textarea>
                                </div>

                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="activo" @checked(old('activo', true))
                                        class="rounded border-gray-300 dark:border-gray-700">
                                    Activo
                                </label>

                                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold
                                               bg-blue-600 text-white hover:bg-blue-700 transition">
                                        Guardar
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ================= MODAL (MOBILE/GENERAL) ================= --}}
        <dialog id="modalProducto" class="rounded-xl p-0 w-full max-w-2xl backdrop:bg-black/50">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Crear producto</h3>
                    <button type="button" onclick="document.getElementById('modalProducto').close()"
                        class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                <form method="POST" action="{{ route('inventario.productos.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Nombre
                        </label>
                        <input name="nombre" value="{{ old('nombre') }}" required class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            SKU
                        </label>
                        <input name="sku" value="{{ old('sku') }}" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Descripción
                        </label>
                        <textarea name="descripcion" rows="2"
                            class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">{{ old('descripcion') }}</textarea>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="activo" @checked(old('activo', true))
                            class="rounded border-gray-300 dark:border-gray-700">
                        Activo
                    </label>

                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
                        <button type="button" onclick="document.getElementById('modalProducto').close()"
                            class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            Cancelar
                        </button>

                        <button type="submit"
                            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </dialog>


    </div>




</x-app-layout>
<script>
    function paintBtn(btn) {
        const on = btn.dataset.state === '1';
        const thumb = btn.querySelector('.thumb');

        btn.setAttribute('aria-checked', on ? 'true' : 'false');

        // colores
        btn.classList.toggle('bg-green-600', on);
        btn.classList.toggle('bg-gray-300', !on);
        btn.classList.toggle('dark:bg-gray-700', !on);

        // posición thumb
        thumb.classList.toggle('translate-x-6', on);
        thumb.classList.toggle('translate-x-1', !on);
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.toggle-producto').forEach(btn => {
            paintBtn(btn);

            btn.addEventListener('click', async () => {
                const url = btn.dataset.url;
                const prev = btn.dataset.state; // '1' o '0'

                // cambio visual inmediato
                btn.dataset.state = (prev === '1') ? '0' : '1';
                paintBtn(btn);

                btn.disabled = true;
                btn.classList.add('opacity-70', 'cursor-not-allowed');

                try {
                    const res = await fetch(url, {
                        method: 'PATCH',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });

                    const ct = res.headers.get('content-type') || '';
                    if (!ct.includes('application/json')) {
                        const text = await res.text();
                        console.error('Respuesta NO JSON:', text);
                        throw new Error('Respuesta NO JSON');
                    }

                    const data = await res.json();
                    if (!res.ok || !data.success) throw data;

                    // sincroniza con backend (por si devuelve true/false)
                    btn.dataset.state = data.activo ? '1' : '0';
                    paintBtn(btn);

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Estado actualizado',
                            timer: 900,
                            showConfirmButton: false
                        });
                    }
                } catch (e) {
                    // revertir si falla
                    btn.dataset.state = prev;
                    paintBtn(btn);

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo actualizar el estado del producto'
                        });
                    } else {
                        alert('No se pudo actualizar el estado del producto');
                    }
                } finally {
                    btn.disabled = false;
                    btn.classList.remove('opacity-70', 'cursor-not-allowed');
                }
            });
        });
    });
</script>