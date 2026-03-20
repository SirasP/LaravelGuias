<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <a href="{{ route('fuelcontrol.productos') }}" class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                    <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Auditoría de Bomba</h2>
                    <p class="text-[11px] text-gray-400 mt-0.5">Producto: {{ ucfirst($producto->nombre) }}</p>
                </div>
            </div>
            @if($isDiesel)
                <div class="flex items-center gap-2">
                    <span class="px-2 py-1 rounded-lg bg-orange-50 dark:bg-orange-900/30 text-[10px] font-bold text-orange-600 dark:text-orange-400 uppercase">
                        Sincronización Diesel
                    </span>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700/50 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 dark:border-gray-700/50 flex items-center justify-between bg-gray-50/50 dark:bg-gray-900/20">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight">Registro de Odómetros</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Validación de secuencia: Odo Actual = Odo Anterior + Despacho</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-right">Cantidad</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-right">Odo Bomba</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-right">Odo Anterior</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-center">Estado</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Usuario</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            @forelse($auditData as $row)
                                <tr class="{{ $row['descuadrado'] ? 'bg-rose-50/50 dark:bg-rose-900/10' : '' }} hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
                                    <td class="px-6 py-4 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap tabular-nums">
                                        {{ \Carbon\Carbon::parse($row['fecha'])->format('d-m-Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold uppercase {{ $row['cantidad'] > 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30' : 'bg-orange-50 text-orange-600 dark:bg-orange-900/30' }}">
                                            {{ $row['tipo'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right tabular-nums text-xs font-bold {{ $row['cantidad'] > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ number_format($row['cantidad'], 2, ',', '.') }} L
                                    </td>
                                    <td class="px-6 py-4 text-right tabular-nums text-xs font-black text-gray-900 dark:text-gray-100">
                                        {{ number_format($row['odometro'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4 text-right tabular-nums text-xs text-gray-400">
                                        {{ $row['prev_odo'] ? number_format($row['prev_odo'], 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @if($row['descuadrado'])
                                            <div class="inline-flex flex-col items-center">
                                                <span class="px-2 py-0.5 rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400 text-[10px] font-black uppercase">Descuadrado</span>
                                                <span class="text-[9px] text-rose-500 font-bold mt-1">Error: {{ number_format($row['diferencia'], 2, ',', '.') }} L</span>
                                            </div>
                                        @elseif($row['odometro'] > 0)
                                            <span class="px-2 py-0.5 rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 text-[10px] font-black uppercase">Correcto</span>
                                        @else
                                            <span class="text-[10px] text-gray-300 italic">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['usuario'] }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-400 italic">No hay registros para este producto</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($auditData->where('descuadrado', true)->isNotEmpty())
                <div class="bg-rose-50 border border-rose-100 dark:bg-rose-900/20 dark:border-rose-900/30 rounded-3xl p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-2xl bg-rose-600 flex items-center justify-center shrink-0 shadow-lg shadow-rose-200 dark:shadow-none">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-rose-900 dark:text-rose-100 uppercase tracking-tight">Inconsistencias Detectadas</h4>
                            <p class="text-xs text-rose-700 dark:text-rose-400 mt-1">Se han detectado saltos o desajustes en la secuencia del odómetro de la bomba. Esto puede deberse a cargas no registradas, errores manuales o intervenciones en la bomba.</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
