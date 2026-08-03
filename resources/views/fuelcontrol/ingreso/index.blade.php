<x-app-layout>
    {{-- Google Font: Outfit --}}
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @php
        // Obtener todas las facturas de combustible de gmail_dte_documents
        $facturas_combustible = DB::connection('fuelcontrol')
            ->table('gmail_dte_documents as d')
            ->select('d.*')
            ->where('d.inventory_status', '=', 'combustible')
            ->orWhereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('gmail_dte_document_lines as l')
                  ->whereColumn('l.document_id', 'd.id')
                  ->where(function ($sub) {
                      $sub->where('l.descripcion', 'LIKE', '%DIESEL%')
                          ->orWhere('l.descripcion', 'LIKE', '%GASOLINA%');
                  });
            })
            ->orderByDesc('d.fecha_factura')
            ->orderByDesc('d.id')
            ->get();

        // Obtener las líneas de esas facturas para extraer litros y tipo
        $dteIds = $facturas_combustible->pluck('id');
        $lineas_combustible = DB::connection('fuelcontrol')
            ->table('gmail_dte_document_lines')
            ->whereIn('document_id', $dteIds)
            ->get()
            ->groupBy('document_id');

        // Mapear qué facturas ya tienen un movimiento de stock en movimientos
        $movimientos_xml = DB::connection('fuelcontrol')
            ->table('movimientos')
            ->whereNotNull('xml_path')
            ->select('xml_path', 'estado', 'id')
            ->get()
            ->keyBy('xml_path')
            ->toArray();

        // Construir JSON mapeado para filtros Alpine.js
        $jsFacturas = $facturas_combustible->map(function ($fac) use ($lineas_combustible, $movimientos_xml) {
            $lines = $lineas_combustible->get($fac->id, collect())->filter(function ($line) {
                $desc = strtoupper($line->descripcion);
                
                // Excluir herramientas, repuestos, aceites y bidones que no son combustible real
                $exclusiones = ['FILTRO', 'JUEGO', 'JGO', 'COMPRESIMETRO', 'ACEITE', 'ADITIVO', 'BOMBA', 'MANGUERA', 'BIDON', 'LIMPIADOR', 'INYECTOR', 'TAPA', 'SERVICIO', 'FLETE', 'HERRAMIENTA', 'EQUIPO'];
                foreach ($exclusiones as $ex) {
                    if (str_contains($desc, $ex)) {
                        return false;
                    }
                }
                
                return str_contains($desc, 'DIESEL') || str_contains($desc, 'GASOLINA');
            });
            
            if ($lines->isEmpty()) {
                return null;
            }
            
            $total_lts = $lines->sum('cantidad');
            $comb = $lines->map(fn($l) => str_contains(strtoupper($l->descripcion), 'DIESEL') ? 'Diesel' : 'Gasolina')->unique()->values()->all();
            
            $matched = $movimientos_xml[$fac->xml_filename] ?? null;
            $status = 'pendiente';
            $movId = null;
            if ($matched) {
                $status = match ($matched->estado) {
                    'aprobado' => 'aprobado',
                    'rechazado' => 'rechazado',
                    default => 'pendiente_revision',
                };
                $movId = $matched->id;
            }
            
            return [
                'id' => $fac->id,
                'folio' => $fac->folio,
                'proveedor' => $fac->proveedor_nombre,
                'rut' => $fac->proveedor_rut,
                'fecha' => \Carbon\Carbon::parse($fac->fecha_factura)->format('d/m/Y'),
                'fecha_raw' => $fac->fecha_factura,
                'total' => (float)$fac->monto_total,
                'litros' => round($total_lts, 1),
                'combustibles' => implode(', ', $comb),
                'status' => $status,
                'movimiento_id' => $movId,
                'xml_filename' => $fac->xml_filename,
            ];
        })->filter()->values();
    @endphp

    {{-- ═══════════════════════════════════════
    HEADER
    ═══════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between w-full font-outfit">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-600 to-orange-500 flex items-center justify-center shrink-0 shadow-md shadow-orange-500/10">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0l-4-4m4 4l-4 4M5 17H1m0 0l4 4m-4-4l4-4" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-black text-gray-900 dark:text-white leading-none">Ingresos de Combustible</h2>
                    <p class="text-xs text-gray-400 mt-1">Control de entradas y conciliación de facturas XML</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="page-bg font-outfit min-h-screen pb-12"
         x-data="{
             activeTab: 'entradas',
             searchInvoice: '',
             statusFilter: 'todos',
             facturas: {{ json_encode($jsFacturas) }},
             get filteredFacturas() {
                 return this.facturas.filter(f => {
                     const matchSearch = f.folio.toString().includes(this.searchInvoice) ||
                                         f.proveedor.toLowerCase().includes(this.searchInvoice.toLowerCase()) ||
                                         f.rut.toLowerCase().includes(this.searchInvoice.toLowerCase());
                     
                     if (this.statusFilter === 'todos') return matchSearch;
                     if (this.statusFilter === 'ingresados') return matchSearch && f.status === 'aprobado';
                     if (this.statusFilter === 'revision') return matchSearch && f.status === 'pendiente_revision';
                     if (this.statusFilter === 'pendientes') return matchSearch && f.status === 'pendiente';
                     if (this.statusFilter === 'rechazados') return matchSearch && f.status === 'rechazado';
                     return matchSearch;
                 });
             }
         }">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
            
            {{-- KPIs de Totales de Ingreso --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Total Gasolina --}}
                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-md p-6 rounded-3xl shadow-sm border border-gray-100/50 dark:border-gray-800/50 flex items-center gap-5 transition-all hover:shadow-md hover:scale-[1.01] duration-300 group">
                    <div class="w-14 h-14 rounded-2xl bg-red-50 dark:bg-red-950/20 flex items-center justify-center text-red-600 dark:text-red-400 shrink-0 group-hover:scale-110 transition-transform shadow-inner">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Ingreso Gasolina</p>
                        <h3 class="text-3xl font-black text-gray-900 dark:text-white mt-1 leading-none">
                            {{ number_format($total_gasolina, 1) }} <span class="text-sm font-bold text-gray-400">L</span>
                        </h3>
                    </div>
                </div>

                {{-- Total Diesel --}}
                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-md p-6 rounded-3xl shadow-sm border border-gray-100/50 dark:border-gray-800/50 flex items-center gap-5 transition-all hover:shadow-md hover:scale-[1.01] duration-300 group">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-950/20 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0 group-hover:scale-110 transition-transform shadow-inner">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Ingreso Diesel</p>
                        <h3 class="text-3xl font-black text-gray-900 dark:text-white mt-1 leading-none">
                            {{ number_format($total_diesel, 1) }} <span class="text-sm font-bold text-gray-400">L</span>
                        </h3>
                    </div>
                </div>
            </div>

            {{-- sliding pill tab selector --}}
            <div class="flex justify-center my-4">
                <div class="bg-gray-100/80 dark:bg-gray-900/50 p-1 rounded-2xl flex gap-1 border border-gray-250/20 dark:border-gray-850/20 shadow-inner backdrop-blur-md">
                    <button @click="activeTab = 'entradas'"
                            :class="activeTab === 'entradas' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-300'"
                            class="px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-300 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Historial de Entradas
                    </button>
                    <button @click="activeTab = 'facturas'"
                            :class="activeTab === 'facturas' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-300'"
                            class="px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-300 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Facturas XML Combustible
                        <span class="ml-1 bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 px-2 py-0.5 rounded-full text-[9px] font-black tracking-normal">
                            {{ count($facturas_combustible) }}
                        </span>
                    </button>
                </div>
            </div>

            {{-- ═══════════════════════════════════════
            TAB 1: HISTORIAL DE ENTRADAS
            ═══════════════════════════════════════ --}}
            <div x-show="activeTab === 'entradas'" x-transition class="bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-sm font-black text-gray-900 dark:text-gray-100 uppercase tracking-wider">Historial de Entradas a Estanque</h3>
                    <span class="text-xs text-gray-400">Total registros: {{ $ingresos->total() }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-widest font-black border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-4">Fecha y Hora</th>
                                <th class="px-6 py-4">Combustible</th>
                                <th class="px-6 py-4 text-right">Cantidad Ingresada</th>
                                <th class="px-6 py-4 text-center">Detalle XML</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($ingresos ?? [] as $ingreso)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors group">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="text-gray-900 dark:text-gray-100 font-semibold">{{ \Carbon\Carbon::parse($ingreso->fecha_movimiento)->format('d/m/Y') }}</span>
                                            <span class="text-[10px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($ingreso->fecha_movimiento)->format('H:i') }} hrs</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full {{ strtolower($ingreso->producto_nombre) === 'gasolina' ? 'bg-red-500 shadow-md shadow-red-500/20' : 'bg-amber-500 shadow-md shadow-amber-500/20' }}"></span>
                                            <span class="font-bold text-gray-700 dark:text-gray-300">{{ ucfirst($ingreso->producto_nombre) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <span class="text-base font-black text-indigo-600 dark:text-indigo-400">
                                            {{ number_format($ingreso->cantidad, 1) }}
                                            <span class="text-[10px] font-bold text-gray-400 ml-0.5">L</span>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-2">
                                            @if($ingreso->xml_path)
                                                <button onclick="abrirMovimiento('{{ route('fuelcontrol.xml.show', $ingreso->id) }}')" 
                                                        class="w-9 h-9 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center hover:bg-emerald-100 dark:hover:bg-emerald-950/40 transition-all shadow-sm hover:scale-105 active:scale-95 group"
                                                        title="Ver XML Factura">
                                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                </button>
                                            @else
                                                <span class="text-[10px] text-gray-400 italic">Manual</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center opacity-40">
                                            <svg class="w-12 h-12 mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0l-8 8-8-8" />
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

            {{-- ═══════════════════════════════════════
            TAB 2: FACTURAS DE COMBUSTIBLE (XML)
            ═══════════════════════════════════════ --}}
            <div x-show="activeTab === 'facturas'" x-transition class="space-y-6">
                
                {{-- Controles de búsqueda y filtros --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm">
                    {{-- Input de búsqueda --}}
                    <div class="relative sm:col-span-2">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text"
                               x-model="searchInvoice"
                               placeholder="Buscar por Folio, Proveedor o RUT..."
                               class="w-full pl-9 pr-4 py-2.5 rounded-2xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all" />
                    </div>

                    {{-- Selector de Estado --}}
                    <div>
                        <select x-model="statusFilter"
                                class="w-full px-4 py-2.5 rounded-2xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 text-sm text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all font-semibold">
                            <option value="todos">Todos los Estados</option>
                            <option value="ingresados">Ingresados en Stock</option>
                            <option value="revision">En Revisión</option>
                            <option value="pendientes">Pendientes de Ingreso</option>
                            <option value="rechazados">Rechazados</option>
                        </select>
                    </div>
                </div>

                {{-- Tabla de Facturas --}}
                <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-50 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-sm font-black text-gray-900 dark:text-gray-100 uppercase tracking-wider">Facturas XML Registradas</h3>
                        <span class="text-xs text-gray-400">Filtradas: <span class="font-bold text-gray-700 dark:text-gray-350" x-text="filteredFacturas.length"></span></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-widest font-black border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-6 py-4">Fecha Emisión</th>
                                    <th class="px-6 py-4">Documento</th>
                                    <th class="px-6 py-4">Proveedor</th>
                                    <th class="px-6 py-4">Detalle Combustible</th>
                                    <th class="px-6 py-4 text-right">Monto Total</th>
                                    <th class="px-6 py-4 text-center">Estado Stock</th>
                                    <th class="px-6 py-4 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-for="fac in filteredFacturas" :key="fac.id">
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors group">
                                        {{-- Fecha --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-gray-100 font-semibold" x-text="fac.fecha"></td>
                                        
                                        {{-- Documento --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-900 dark:text-white" x-text="`FACTURA N° ${fac.folio}`"></span>
                                                <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 mt-0.5">Electrónica</span>
                                            </div>
                                        </td>

                                        {{-- Proveedor --}}
                                        <td class="px-6 py-4 max-w-xs truncate">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 dark:text-gray-200" x-text="fac.proveedor"></span>
                                                <span class="text-[10px] text-gray-400 mt-0.5" x-text="fac.rut"></span>
                                            </div>
                                        </td>

                                        {{-- Detalle Combustible --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-col">
                                                <span class="font-extrabold text-gray-950 dark:text-gray-100 text-sm" x-text="`${fac.litros} L`"></span>
                                                <span class="text-[10px] text-gray-400 mt-0.5" x-text="fac.combustibles"></span>
                                            </div>
                                        </td>

                                        {{-- Monto --}}
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <span class="font-mono font-black text-gray-900 dark:text-white" x-text="`$${new Intl.NumberFormat('es-CL').format(fac.total)}`"></span>
                                        </td>

                                        {{-- Estado Stock --}}
                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <span :class="{
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border border-emerald-100/50 dark:border-emerald-900/30': fac.status === 'aprobado',
                                                'bg-yellow-50 text-yellow-700 dark:bg-yellow-950/20 dark:text-yellow-400 border border-yellow-100/50 dark:border-yellow-900/30': fac.status === 'pendiente_revision',
                                                'bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-500 border border-amber-100/50 dark:border-amber-900/30': fac.status === 'pendiente',
                                                'bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400 border border-red-100/50 dark:border-red-900/30': fac.status === 'rechazado'
                                            }" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider"
                                                  x-text="fac.status === 'aprobado' ? 'Ingresado' : (fac.status === 'pendiente_revision' ? 'En Revisión' : (fac.status === 'rechazado' ? 'Rechazado' : 'Pendiente'))">
                                            </span>
                                        </td>

                                        {{-- Acciones --}}
                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <div class="flex items-center justify-center gap-2">
                                                <button @click="fac.status === 'pendiente' ? abrirDteXml('{{ route('fuelcontrol.xml.dte.show', '__id__') }}'.replace('__id__', fac.id)) : abrirMovimiento('{{ route('fuelcontrol.xml.show', '__id__') }}'.replace('__id__', fac.movimiento_id))"
                                                        :class="fac.status === 'pendiente' ? 'bg-amber-50 dark:bg-amber-950/20 text-amber-600 dark:text-amber-450 hover:bg-amber-100' : (fac.status === 'rechazado' ? 'bg-red-50 dark:bg-red-950/20 text-red-600 dark:text-red-400 hover:bg-red-100' : 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100')"
                                                        class="w-9 h-9 rounded-2xl flex items-center justify-center transition-all shadow-sm hover:scale-105 active:scale-95 group"
                                                        title="Ver XML e Ingreso">
                                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                
                                {{-- Empty state en caso de filtros --}}
                                <tr x-show="filteredFacturas.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center opacity-40">
                                            <svg class="w-12 h-12 mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                            <p class="text-sm italic font-medium" x-text="`No se encontraron facturas con los filtros seleccionados.`"></p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
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

        window.abrirDteXml = async function (url) {
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
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el XML de la factura.' });
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
