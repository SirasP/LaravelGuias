<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Panel – Reclamos y Sugerencias
            </h2>
            <a href="{{ route('sugerencias.index') }}"
               class="inline-flex items-center gap-1.5 text-sm text-green-600 hover:text-green-800 dark:text-green-400 font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Ir al formulario
            </a>
        </div>
    </x-slot>

    <div class="py-10 px-4">
        <div class="max-w-5xl mx-auto space-y-6">

            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 text-center">
                    <div class="text-4xl font-extrabold text-gray-700 dark:text-gray-200">{{ $total }}</div>
                    <div class="text-xs text-gray-400 mt-1 uppercase tracking-wide">Total</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-blue-100 dark:border-blue-900 p-5 text-center">
                    <div class="text-4xl font-extrabold text-blue-600 dark:text-blue-400">{{ $sugerencias }}</div>
                    <div class="text-xs text-gray-400 mt-1 uppercase tracking-wide">Sugerencias</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-red-100 dark:border-red-900 p-5 text-center">
                    <div class="text-4xl font-extrabold text-red-600 dark:text-red-400">{{ $reclamos }}</div>
                    <div class="text-xs text-gray-400 mt-1 uppercase tracking-wide">Reclamos</div>
                </div>
            </div>

            {{-- Filtros --}}
            <div class="flex gap-2" id="filtros">
                <button data-filter="todos"
                    class="filter-btn active px-4 py-1.5 rounded-full text-xs font-semibold border transition">
                    Todos
                </button>
                <button data-filter="sugerencia"
                    class="filter-btn px-4 py-1.5 rounded-full text-xs font-semibold border transition">
                    💡 Sugerencias
                </button>
                <button data-filter="reclamo"
                    class="filter-btn px-4 py-1.5 rounded-full text-xs font-semibold border transition">
                    📢 Reclamos
                </button>
            </div>

            {{-- Tabla --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                @if($comentarios->isEmpty())
                    <div class="text-center py-20 text-gray-400 dark:text-gray-500 text-sm">
                        No hay registros aún.
                    </div>
                @else
                    <table class="w-full text-sm" id="tablaComentarios">
                        <thead>
                            <tr class="bg-green-700 text-white text-xs uppercase tracking-wide">
                                <th class="px-4 py-3 text-left w-10">#</th>
                                <th class="px-4 py-3 text-left w-32">Tipo</th>
                                <th class="px-4 py-3 text-left">Comentario</th>
                                <th class="px-4 py-3 text-left w-36">Fecha</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($comentarios as $c)
                            <tr class="fila hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors" data-tipo="{{ $c->tipo }}">
                                <td class="px-4 py-3 text-gray-400 text-xs">{{ $c->id }}</td>
                                <td class="px-4 py-3">
                                    @if($c->tipo === 'sugerencia')
                                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                            💡 Sugerencia
                                        </span>
                                    @else
                                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                            📢 Reclamo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ $c->comentario }}</td>
                                <td class="px-4 py-3 text-gray-400 text-xs">
                                    {{ $c->created_at->format('d/m/Y') }}<br>
                                    <span class="text-gray-300 dark:text-gray-500">{{ $c->created_at->format('H:i') }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>
    </div>

    <style>
        .filter-btn {
            background: white;
            color: #6b7280;
            border-color: #e5e7eb;
        }
        .dark .filter-btn {
            background: #1f2937;
            color: #9ca3af;
            border-color: #374151;
        }
        .filter-btn.active {
            background: #166534;
            color: white;
            border-color: #166534;
        }
    </style>

    <script>
        const btns = document.querySelectorAll('.filter-btn');
        const filas = document.querySelectorAll('.fila');

        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                btns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const f = btn.dataset.filter;
                filas.forEach(fila => {
                    fila.style.display = (f === 'todos' || fila.dataset.tipo === f) ? '' : 'none';
                });
            });
        });
    </script>
</x-app-layout>
