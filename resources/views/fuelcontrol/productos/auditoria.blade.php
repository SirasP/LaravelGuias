<x-app-layout>
    <x-slot name="header">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-4">
                    <a href="{{ route('fuelcontrol.productos') }}" class="group flex items-center justify-center w-10 h-10 rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300">
                        <svg class="w-5 h-5 text-gray-500 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h2 class="text-xl font-black text-gray-900 dark:text-gray-100 tracking-tight leading-none">Auditoría de Flujo</h2>
                        <p class="text-xs font-semibold text-indigo-500 uppercase tracking-wider mt-1.5">{{ $producto->nombre }} / Control de Odómetros</p>
                    </div>
                </div>
                <a href="{{ route('fuelcontrol.productos.auditoria.export', $producto->id) }}" class="px-5 py-2.5 rounded-xl bg-black text-white text-[10px] font-black uppercase tracking-widest hover:scale-105 transition-all shadow-lg flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Exportar Reporte
                </a>
            </div>
    </x-slot>

    @php
        $totalCargas = $auditData->count();
        $totalLitros = abs($auditData->sum('cantidad'));
        $errores = $auditData->where('descuadrado', true)->count();
        $diferenciaTotal = abs($auditData->sum('diferencia'));
    @endphp

    <div class="py-6 sm:py-8 bg-[#f8fafc] dark:bg-[#0f172a] min-h-screen">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            {{-- Resumen de Auditoría --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700/50">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.1em] mb-2">Total Registros</p>
                    <div class="flex items-end gap-2">
                        <span class="text-3xl font-black text-gray-900 dark:text-white tabular-nums">{{ $totalCargas }}</span>
                        <span class="text-xs font-bold text-gray-400 mb-1.5">movimientos</span>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700/50">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.1em] mb-2">Volumen Total</p>
                    <div class="flex items-end gap-2">
                        <span class="text-3xl font-black text-gray-900 dark:text-white tabular-nums">{{ number_format($totalLitros, 0, ',', '.') }}</span>
                        <span class="text-xs font-bold text-gray-400 mb-1.5">Litros</span>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700/50">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.1em] mb-2">Estado de Secuencia</p>
                    <div class="flex items-center gap-3">
                        @if($errores > 0)
                            <div class="w-3 h-3 rounded-full bg-rose-500 animate-pulse"></div>
                            <span class="text-lg font-black text-rose-600 dark:text-rose-400 uppercase">{{ $errores }} Descuadres</span>
                        @else
                            <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                            <span class="text-lg font-black text-emerald-600 dark:text-emerald-400 uppercase">Sincronizado</span>
                        @endif
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700/50">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.1em] mb-2">Diferencia Acumulada</p>
                    <div class="flex items-end gap-2 text-rose-600 dark:text-rose-400">
                        <span class="text-3xl font-black tabular-nums">{{ number_format($diferenciaTotal, 1, ',', '.') }}</span>
                        <span class="text-xs font-bold mb-1.5">L Desviación</span>
                    </div>
                </div>
            </div>

            {{-- Tabla Profesional --}}
            <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] shadow-xl shadow-gray-200/50 dark:shadow-none border border-gray-100 dark:border-gray-700/50 overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-50 dark:border-gray-700/50 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white/50 dark:bg-gray-900/40">
                    <div>
                        <h3 class="text-lg font-black text-gray-900 dark:text-gray-100 tracking-tight">Registro Maestro de Flujo</h3>
                        <p class="text-xs text-gray-400 mt-1">Validación matemática de odómetro de bomba vs entregas manuales.</p>
                    </div>
                    <div class="flex items-center gap-2">
                         <div class="px-4 py-2 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-[11px] font-bold text-gray-500">
                            Filtro: Todo el historial
                         </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/30 dark:bg-gray-900/30">
                                <th class="pl-8 pr-4 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Sello / Fecha</th>
                                <th class="px-4 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Operación</th>
                                <th class="px-4 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Cantidad</th>
                                <th class="px-4 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Secuencia Bomba</th>
                                <th class="px-4 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Validación</th>
                                <th class="pl-4 pr-8 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Responsable</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
                            @forelse($auditData as $row)
                                <tr class="{{ $row['descuadrado'] ? 'bg-rose-50/30 dark:bg-rose-900/5' : '' }} group hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-all">
                                    <td class="pl-8 pr-4 py-5">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-black text-gray-900 dark:text-gray-200 tabular-nums">{{ \Carbon\Carbon::parse($row['fecha'])->format('d-m-Y') }}</span>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">{{ \Carbon\Carbon::parse($row['fecha'])->format('H:i') }} hrs</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full {{ $row['cantidad'] > 0 ? 'bg-emerald-500' : 'bg-orange-500' }}"></div>
                                            <span class="text-[11px] font-black uppercase text-gray-600 dark:text-gray-400 tracking-tight">{{ $row['tipo'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-5 text-right">
                                        <span class="text-sm font-black tabular-nums {{ $row['cantidad'] > 0 ? 'text-emerald-600' : 'text-gray-900 dark:text-white' }}">
                                            {{ number_format(abs($row['cantidad']), 1, ',', '.') }} 
                                            <span class="text-[10px] font-bold text-gray-400">L</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-5 text-right">
                                        <div class="flex flex-col items-end">
                                            <span class="text-sm font-black text-gray-900 dark:text-white tabular-nums leading-none">
                                                {{ number_format($row['odometro'], 1, ',', '.') }}
                                            </span>
                                            @if($row['prev_odo'])
                                                <span class="text-[9px] font-bold text-gray-400 mt-1 uppercase">Ant: {{ number_format($row['prev_odo'], 1, ',', '.') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-5 text-center">
                                        @if($row['descuadrado'])
                                            <div class="inline-flex shrink-0 px-3 py-1 rounded-full bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50">
                                                <div class="flex items-center gap-2">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    <span class="text-[10px] font-black uppercase tracking-tight">Gap: {{ number_format($row['diferencia'], 1, ',', '.') }} L</span>
                                                </div>
                                            </div>
                                        @elseif($row['odometro'] > 0)
                                            <div class="inline-flex shrink-0 px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">
                                                 <div class="flex items-center gap-2">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                                    <span class="text-[10px] font-black uppercase tracking-tight">Ok</span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-[10px] font-bold text-gray-300 italic">Sin registro</span>
                                        @endif
                                    </td>
                                    <td class="pl-4 pr-8 py-5 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <div class="flex flex-col text-right">
                                                <span class="text-xs font-black text-gray-700 dark:text-gray-300">{{ $row['usuario'] }}</span>
                                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tighter">ID #{{ $row['id'] }}</span>
                                            </div>
                                            <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-[10px] font-black text-gray-400 uppercase">
                                                {{ substr($row['usuario'], 0, 1) }}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-8 py-20 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="w-16 h-16 rounded-[2rem] bg-gray-50 dark:bg-gray-900 flex items-center justify-center mb-4">
                                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 00-2 2H6a2 2 0 00-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            </div>
                                            <p class="text-sm font-bold text-gray-400">No se encontraron movimientos registrados</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($auditData->where('descuadrado', true)->isNotEmpty())
                <div class="bg-rose-600 rounded-[2.5rem] p-8 shadow-2xl shadow-rose-200 dark:shadow-none relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mt-8 -mr-8 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                    <div class="relative flex flex-col md:flex-row items-center gap-8">
                        <div class="w-20 h-20 rounded-[2.5rem] bg-white/20 backdrop-blur-md flex items-center justify-center shrink-0 border border-white/30">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <div class="text-center md:text-left flex-1">
                            <h4 class="text-2xl font-black text-white tracking-tight uppercase">Inconsistencia Crítica</h4>
                            <p class="text-rose-100 font-medium mt-2 leading-relaxed">
                                Se han detectado saltos inexplicables en la lectura de la bomba. La diferencia acumulada es de <strong>{{ number_format($diferenciaTotal, 1, ',', '.') }} Litros</strong>. 
                                Favor de revisar si existieron cargas de emergencia no registradas o intervenciones en la bomba de despacho.
                            </p>
                        </div>
                        <a href="{{ route('fuelcontrol.productos.auditoria.export', $producto->id) }}" class="px-8 py-4 rounded-[1.5rem] bg-black text-white text-xs font-black uppercase tracking-widest hover:scale-105 transition-all shadow-xl flex items-center justify-center">
                            Exportar Reporte
                        </a>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
