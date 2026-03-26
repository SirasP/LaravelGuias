<x-app-layout>

    {{-- ═══════════════════════════════════════
    HEADER
    ═══════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-orange-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0l-4-4m4 4l-4 4M5 17H1m0 0l4 4m-4-4l4-4" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Ingresos de Combustible</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Control de entradas a estanque</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
            
            {{-- KPIs de Totales de Ingreso --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Total Gasolina --}}
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center gap-5 transition-all hover:shadow-md group">
                    <div class="w-14 h-14 rounded-2xl bg-red-50 dark:bg-red-900/20 flex items-center justify-center text-red-600 dark:text-red-400 shrink-0 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest text">Total Ingreso Gasolina</p>
                        <h3 class="text-2xl font-black text-gray-900 dark:text-white mt-1">
                            {{ number_format($total_gasolina, 1) }} <span class="text-sm font-bold text-gray-400">L</span>
                        </h3>
                    </div>
                </div>

                {{-- Total Diesel --}}
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center gap-5 transition-all hover:shadow-md group">
                    <div class="w-14 h-14 rounded-2xl bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center text-yellow-600 dark:text-yellow-400 shrink-0 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Total Ingreso Diesel</p>
                        <h3 class="text-2xl font-black text-gray-900 dark:text-white mt-1">
                            {{ number_format($total_diesel, 1) }} <span class="text-sm font-bold text-gray-400">L</span>
                        </h3>
                    </div>
                </div>
            </div>

            {{-- Tabla de Ingresos --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Historial de Entradas</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-widest font-bold">
                                <th class="px-6 py-4">Fecha y Hora</th>
                                <th class="px-6 py-4">Combustible</th>
                                <th class="px-6 py-4 text-right">Cantidad Ingresada</th>
                                <th class="px-6 py-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($ingresos ?? [] as $ingreso)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors group">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="text-gray-900 dark:text-gray-100 font-semibold">{{ \Carbon\Carbon::parse($ingreso->fecha_movimiento)->format('d/m/Y') }}</span>
                                            <span class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($ingreso->fecha_movimiento)->format('H:i') }} hrs</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full {{ strtolower($ingreso->producto_nombre) === 'gasolina' ? 'bg-red-500' : 'bg-yellow-500' }}"></span>
                                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ ucfirst($ingreso->producto_nombre) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <span class="text-base font-black text-indigo-600 dark:text-indigo-400">
                                            {{ number_format($ingreso->cantidad, 1) }}
                                            <span class="text-[10px] font-normal text-gray-400 ml-0.5">L</span>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-2">
                                            {{-- XML --}}
                                            @if($ingreso->xml_path)
                                                <button onclick="abrirMovimiento('{{ route('fuelcontrol.xml.show', $ingreso->id) }}')" 
                                                        class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition-all shadow-sm group"
                                                        title="Ver XML">
                                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center opacity-40">
                                            <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0l-8 8-8-8" />
                                            </svg>
                                            <p class="text-sm italic font-medium">No se encontraron registros de entrada.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($ingresos->hasPages())
                    <div class="px-6 py-4 bg-gray-50/30 dark:bg-gray-900/30 border-t border-gray-100 dark:border-gray-800">
                        {{ $ingresos->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.abrirMovimiento = async function (url) {
            try {
                const response = await fetch(url);
                const html = await response.text();
                await Swal.fire({
                    width: '85%',
                    showCloseButton: true,
                    showConfirmButton: false,
                    html: html,
                    background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#f9fafb',
                    customClass: {
                        container: 'xml-modal'
                    }
                });
            } catch (error) {
                console.error('XML Error:', error);
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el XML.' });
            }
        };

        window.switchTab = function (tab) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                b.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            });
            
            const content = document.getElementById('content-' + tab);
            if (content) content.classList.remove('hidden');
            
            const btn = document.getElementById('tab-' + tab);
            if (btn) {
                btn.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                btn.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            }
        };
    </script>
</x-app-layout>
