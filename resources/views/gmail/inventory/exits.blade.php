<x-app-layout>
    <x-slot name="header">
        <div class="w-full grid grid-cols-1 lg:grid-cols-[auto,1fr,auto] items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Salidas de inventario</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">Historial FIFO</p>
                </div>
            </div>

            <form method="GET" class="hidden lg:block w-full lg:max-w-xl lg:justify-self-center">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" class="f-input"
                        placeholder="Buscar por destinatario...">
                    <button type="submit"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">Buscar</button>
                </div>
            </form>

            <div class="hidden lg:flex items-center justify-end gap-2">
                <a href="{{ route('gmail.inventory.exit.create') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                    </svg>
                    Nueva Salida
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .f-input {
            width:100%; border-radius:12px; border:1px solid #e2e8f0;
            background:#fff; padding:9px 12px; font-size:13px;
            color:#111827; outline:none;
        }
        .f-input:focus { border-color:#f43f5e; box-shadow:0 0 0 3px rgba(244,63,94,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            {{-- Mobile search --}}
            <form method="GET" class="lg:hidden">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" class="f-input"
                        placeholder="Buscar por destinatario...">
                    <button type="submit"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">Buscar</button>
                </div>
            </form>

            @if (session('success'))
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
                    <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
                </div>
            @endif

            @if ($movements->count() > 0)
                <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="text-left text-xs font-semibold text-gray-400 px-4 py-3">Fecha</th>
                                <th class="text-left text-xs font-semibold text-gray-400 px-4 py-3">Destinatario</th>
                                <th class="text-right text-xs font-semibold text-gray-400 px-4 py-3">Productos</th>
                                <th class="text-right text-xs font-semibold text-gray-400 px-4 py-3">Costo total</th>
                                <th class="text-left text-xs font-semibold text-gray-400 px-4 py-3 hidden md:table-cell">Registrado por</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($movements as $m)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            {{ \Carbon\Carbon::parse($m->created_at)->format('H:i') }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100 truncate max-w-[200px]">
                                            {{ $m->destinatario ?? '—' }}
                                        </p>
                                        @if ($m->notas)
                                            <p class="text-xs text-gray-400 truncate max-w-[200px]">{{ $m->notas }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                                            {{ $lineCounts[$m->id] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                        $ {{ number_format((float) $m->costo_total, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell">
                                        <p class="text-xs text-gray-400">
                                            @if ($m->usuario_id)
                                                {{ optional(\App\Models\User::find($m->usuario_id))->name ?? 'Usuario #' . $m->usuario_id }}
                                            @else
                                                —
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-xs text-gray-300 dark:text-gray-700">#{{ $m->id }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>{{ $movements->links() }}</div>
            @else
                <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center">
                    <svg class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No hay salidas registradas</p>
                    <p class="text-xs text-gray-400 mt-1 mb-4">Las salidas aparecerán aquí al registrar entregas de stock.</p>
                    <a href="{{ route('gmail.inventory.exit.create') }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">
                        Registrar primera salida
                    </a>
                </div>
            @endif

            {{-- FAB mobile --}}
            <a href="{{ route('gmail.inventory.exit.create') }}"
                class="fixed right-5 bottom-5 z-70 lg:hidden w-14 h-14 rounded-full inline-flex items-center justify-center
                       bg-rose-600 hover:bg-rose-700 text-white shadow-xl transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                </svg>
            </a>
        </div>
    </div>
</x-app-layout>
