
   <x-app-layout x-data="{
    openCreate: false,
    openEdit: false,
    openDelete: false,
    openShow: false,
    deleteId: null,
    editVehiculo: {},
    showVehiculo: {}
}">

        <x-slot name="header">
            <div class="flex items-center justify-between gap-4 w-full">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-orange-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Vehículos</h2>
                        <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Gestión de flota</p>
                    </div>
                </div>

                <button @click="openCreate = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                           bg-emerald-600 hover:bg-emerald-700 active:scale-95
                           text-white transition shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="hidden sm:inline">Nuevo Vehículo</span>
                </button>
    

            </div>
        </x-slot>

        <style>
            @keyframes fadeUp { from { opacity:0; transform:translateY(8px) } to { opacity:1; transform:translateY(0) } }
            .au { animation: fadeUp .4s cubic-bezier(.22,1,.36,1) both }
            .d1 { animation-delay:.04s } .d2 { animation-delay:.08s } .d3 { animation-delay:.12s }

            .page-bg { background:#f1f5f9; min-height:100% }
            .dark .page-bg { background:#0d1117 }

            .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
            .dark .panel { background:#161c2c; border-color:#1e2a3b }

            .stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:16px 18px; display:flex; align-items:center; justify-content:space-between }
            .dark .stat-card { background:#161c2c; border-color:#1e2a3b }

            .dt { width:100%; border-collapse:collapse; font-size:13px }
            .dt thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9 }
            .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
            .dt th { padding:10px 16px; text-align:left; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; white-space:nowrap }
            .dt th.r { text-align:right } .dt th.c { text-align:center }
            .dt td { padding:12px 16px; border-bottom:1px solid #f8fafc; color:#334155; vertical-align:middle }
            .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
            .dt tbody tr:last-child td { border-bottom:none }
            .dt tbody tr:hover td { background:#f8fafc }
            .dark .dt tbody tr:hover td { background:#1a2436 }

            .m-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:14px 16px }
            .dark .m-card { background:#161c2c; border-color:#1e2a3b }

            .badge-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700 }
            .badge-blue { background:#dbeafe; color:#1d4ed8 } .dark .badge-blue { background:rgba(59,130,246,.15); color:#60a5fa }
            .badge-green { background:#dcfce7; color:#15803d } .dark .badge-green { background:rgba(22,163,74,.15); color:#4ade80 }
            .badge-purple { background:#f3e8ff; color:#7c3aed } .dark .badge-purple { background:rgba(124,58,237,.15); color:#a78bfa }
            .badge-orange { background:#ffedd5; color:#c2410c } .dark .badge-orange { background:rgba(234,88,12,.15); color:#fb923c }
            .badge-gray { background:#f1f5f9; color:#475569 } .dark .badge-gray { background:rgba(100,116,139,.15); color:#94a3b8 }
        </style>

        <div class="page-bg">
            <div class="max-w-8xl mx-auto px-4 sm:px-6 py-6 space-y-5">

                {{-- Stats --}}
                @if($vehiculos->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 au d1">
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total</p>
                                <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $stats->total }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <svg class="w-4.5 h-4.5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Camiones</p>
                                <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $stats->camiones }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                                <svg class="w-4.5 h-4.5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Motos</p>
                                <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $stats->motos }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center">
                                <svg class="w-4.5 h-4.5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Camionetas</p>
                                <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $stats->camionetas }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center">
                                <svg class="w-4.5 h-4.5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 au d1">
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Maquinaria</p>
                                <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $stats->maquinaria }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center">
                                <svg class="w-4.5 h-4.5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Propio</p>
                                <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $stats->propios }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <svg class="w-4.5 h-4.5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Arrendado</p>
                                <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $stats->arrendados }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                                <svg class="w-4.5 h-4.5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h5l5 5v9a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Prestado</p>
                                <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $stats->prestados }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-gray-50 dark:bg-gray-700/30 flex items-center justify-center">
                                <svg class="w-4.5 h-4.5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12l-4-4m4 4l-4 4M16 17H4l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Search bar --}}
                <div class="panel au d1">
                    <div class="p-4">
                        <form method="GET" action="{{ route('fuelcontrol.vehiculos.index') }}" class="flex flex-col sm:flex-row gap-2">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" name="search" value="{{ request('search') }}"
                                    placeholder="Buscar por patente o descripción…"
                                    class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl
                                           border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800
                                           text-gray-900 dark:text-white placeholder-gray-400
                                           focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none transition">
                            </div>

                            <div class="flex gap-2">
                                <button type="submit"
                                    class="inline-flex items-center gap-1.5 px-4 py-2.5 text-xs font-semibold rounded-xl
                                           bg-orange-600 hover:bg-orange-700 text-white transition shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    Buscar
                                </button>

                                @if(request('search') || request('tipo'))
                                    <a href="{{ route('fuelcontrol.vehiculos.index') }}"
                                        class="inline-flex items-center gap-1.5 px-4 py-2.5 text-xs font-semibold rounded-xl
                                               bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300
                                               hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Limpiar
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Desktop table --}}
                <div class="panel hidden lg:block au d2">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Vehículo</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Registro</th>
                                <th>Usuario</th>
                                <th class="c">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vehiculos as $v)
                                @php
                                    $desc = strtolower($v->descripcion ?? '');
                                    if (str_contains($desc, 'tractor') || str_contains($desc, 'excavadora') || str_contains($desc, 'pala') || str_contains($desc, 'fumigador')) {
                                        $badgeClass = 'badge-orange';
                                        $iconColor = 'text-orange-500';
                                    } elseif (str_contains($desc, 'camion') || str_contains($desc, 'camioneta') || str_contains($desc, 'minibus')) {
                                        $badgeClass = 'badge-blue';
                                        $iconColor = 'text-blue-500';
                                    } elseif (str_contains($desc, 'moto')) {
                                        $badgeClass = 'badge-purple';
                                        $iconColor = 'text-purple-500';
                                    } else {
                                        $badgeClass = 'badge-gray';
                                        $iconColor = 'text-gray-400';
                                    }

                                    $tipoClass = match(strtolower($v->tipo ?? '')) {
                                        'propio' => 'badge-green',
                                        'arrendado' => 'badge-blue',
                                        'prestado' => 'badge-gray',
                                        default => 'badge-gray',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center {{ $iconColor }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                </svg>
                                            </div>
                                            <div>
                                                <span class="font-semibold text-gray-900 dark:text-white">{{ $v->patente }}</span>
                                                <span class="block text-[11px] text-gray-400">#{{ $v->id }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge-pill {{ $tipoClass }}">{{ ucfirst($v->tipo) }}</span></td>
                                    <td class="max-w-[200px] truncate">{{ $v->descripcion ?? 'Sin descripción' }}</td>
                                    <td>
                                        <span class="text-gray-900 dark:text-white text-sm">{{ \Carbon\Carbon::parse($v->fecha_registro)->format('d/m/Y') }}</span>
                                        <span class="block text-[11px] text-gray-400">{{ \Carbon\Carbon::parse($v->fecha_registro)->diffForHumans() }}</span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <span class="text-[10px] font-bold text-gray-500">{{ strtoupper(substr($v->usuario, 0, 2)) }}</span>
                                            </div>
                                            <span class="text-sm">{{ $v->usuario }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" @click="
                                                openShow = true;
                                                showVehiculo = {
                                                    id: {{ $v->id }},
                                                    patente: @js($v->patente),
                                                    descripcion: @js($v->descripcion),
                                                    tipo: @js($v->tipo),
                                                    usuario: @js($v->usuario),
                                                    fecha: @js(\Carbon\Carbon::parse($v->fecha_registro)->format('d/m/Y')),
                                                };
                                            " class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition" title="Ver">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>

                                            <button type="button" @click="
                                                openEdit = true;
                                                editVehiculo = {
                                                    id: {{ $v->id }},
                                                    patente: @js($v->patente),
                                                    descripcion: @js($v->descripcion),
                                                    tipo: @js($v->tipo),
                                                };
                                            " class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition" title="Editar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>

                                            <button type="button" @click="openDelete = true; deleteId = {{ $v->id }}"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition" title="Eliminar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-12">
                                        <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                        <p class="text-sm font-medium text-gray-400">
                                            @if(request('search') || request('tipo'))
                                                No se encontraron vehículos
                                            @else
                                                No hay vehículos registrados
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile cards --}}
                <div class="lg:hidden space-y-2 au d2">
                    @forelse ($vehiculos as $v)
                        @php
                            $desc = strtolower($v->descripcion ?? '');
                            if (str_contains($desc, 'tractor') || str_contains($desc, 'excavadora') || str_contains($desc, 'pala') || str_contains($desc, 'fumigador')) {
                                $badgeClass = 'badge-orange';
                                $iconColor = 'text-orange-500';
                            } elseif (str_contains($desc, 'camion') || str_contains($desc, 'camioneta') || str_contains($desc, 'minibus')) {
                                $badgeClass = 'badge-blue';
                                $iconColor = 'text-blue-500';
                            } elseif (str_contains($desc, 'moto')) {
                                $badgeClass = 'badge-purple';
                                $iconColor = 'text-purple-500';
                            } else {
                                $badgeClass = 'badge-gray';
                                $iconColor = 'text-gray-400';
                            }

                            $tipoClass = match(strtolower($v->tipo ?? '')) {
                                'propio' => 'badge-green',
                                'arrendado' => 'badge-blue',
                                'prestado' => 'badge-gray',
                                default => 'badge-gray',
                            };
                        @endphp
                        <div class="m-card">
                            {{-- Header row --}}
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center shrink-0 {{ $iconColor }}">
                                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $v->patente }}</p>
                                        <p class="text-[11px] text-gray-400 truncate">{{ $v->descripcion ?? 'Sin descripción' }}</p>
                                    </div>
                                </div>
                                <span class="badge-pill {{ $tipoClass }} shrink-0">{{ ucfirst($v->tipo) }}</span>
                            </div>

                            {{-- Details --}}
                            <div class="mt-3 flex items-center gap-4 text-[11px] text-gray-400">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ \Carbon\Carbon::parse($v->fecha_registro)->format('d/m/Y') }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    {{ $v->usuario }}
                                </span>
                            </div>

                            {{-- Actions --}}
                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700/50 flex items-center gap-2">
                                <button type="button" @click="
                                    openShow = true;
                                    showVehiculo = {
                                        id: {{ $v->id }},
                                        patente: @js($v->patente),
                                        descripcion: @js($v->descripcion),
                                        tipo: @js($v->tipo),
                                        usuario: @js($v->usuario),
                                        fecha: @js(\Carbon\Carbon::parse($v->fecha_registro)->format('d/m/Y')),
                                    };
                                " class="flex-1 text-center py-2 text-xs font-semibold rounded-lg bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                    Ver
                                </button>

                                <button type="button" @click="
                                    openEdit = true;
                                    editVehiculo = {
                                        id: {{ $v->id }},
                                        patente: @js($v->patente),
                                        descripcion: @js($v->descripcion),
                                        tipo: @js($v->tipo),
                                    };
                                " class="flex-1 text-center py-2 text-xs font-semibold rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition">
                                    Editar
                                </button>

                                <button type="button" @click="openDelete = true; deleteId = {{ $v->id }}"
                                    class="flex-1 text-center py-2 text-xs font-semibold rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 transition">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="m-card text-center py-8">
                            <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            <p class="text-sm font-medium text-gray-400">
                                @if(request('search') || request('tipo'))
                                    No se encontraron vehículos
                                @else
                                    No hay vehículos registrados
                                @endif
                            </p>
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                @if($vehiculos->hasPages())
                    <div class="flex justify-end au d3">
                        {{ $vehiculos->links() }}
                    </div>
                @endif

            </div>
        </div>

        {{-- Modal: Crear --}}
        <div x-show="openCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
            <div @click.outside="openCreate = false" x-transition class="bg-white dark:bg-gray-900 rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Nuevo Vehículo</h2>
                <form method="POST" action="{{ route('fuelcontrol.vehiculos.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Modelo Vehículo</label>
                        <input type="text" name="patente" required
                            class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800
                                   text-gray-900 dark:text-white px-3 py-2.5 text-sm
                                   focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Tipo Vehículo</label>
                        <input type="text" name="descripcion"
                            class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800
                                   text-gray-900 dark:text-white px-3 py-2.5 text-sm
                                   focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Propio / Arrendado / Prestado</label>
                        <select name="tipo" required
                            class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800
                                   text-gray-900 dark:text-white px-3 py-2.5 text-sm
                                   focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none transition">
                            <option value="">Seleccionar</option>
                            <option value="Propio">Propio</option>
                            <option value="Arrendado">Arrendado</option>
                            <option value="Prestado">Prestado</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="openCreate = false"
                            class="px-4 py-2 text-sm font-medium rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 transition shadow-sm">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal: Eliminar --}}
        <div x-show="openDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
            <div @click.outside="openDelete = false" x-transition class="bg-white dark:bg-gray-900 rounded-2xl p-6 w-full max-w-sm shadow-2xl">
                <div class="text-center">
                    <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Eliminar vehículo</h3>
                    <p class="text-sm text-gray-500 mt-1">Esta acción no se puede deshacer.</p>
                </div>
                <div class="flex gap-2 mt-5">
                    <button type="button" @click="openDelete = false"
                        class="flex-1 px-4 py-2.5 text-sm font-medium rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition">
                        Cancelar
                    </button>
                    <form :action="`{{ route('fuelcontrol.vehiculos.destroy', '__id__') }}`.replace('__id__', deleteId)" method="POST" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="w-full px-4 py-2.5 text-sm font-medium rounded-xl bg-red-600 text-white hover:bg-red-700 transition shadow-sm">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal: Editar --}}
        <div x-show="openEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
            <div @click.outside="openEdit = false" x-transition class="bg-white dark:bg-gray-900 rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Editar Vehículo</h2>
                <form method="POST"
                    :action="`{{ route('fuelcontrol.vehiculos.update', '__id__') }}`.replace('__id__', editVehiculo.id)"
                    class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Patente</label>
                        <input type="text" name="patente" x-model="editVehiculo.patente" required
                            class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800
                                   text-gray-900 dark:text-white px-3 py-2.5 text-sm
                                   focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Descripción</label>
                        <input type="text" name="descripcion" x-model="editVehiculo.descripcion"
                            class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800
                                   text-gray-900 dark:text-white px-3 py-2.5 text-sm
                                   focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Tipo</label>
                        <select name="tipo" x-model="editVehiculo.tipo"
                            class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800
                                   text-gray-900 dark:text-white px-3 py-2.5 text-sm
                                   focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none transition">
                            <option value="Propio">Propio</option>
                            <option value="Arrendado">Arrendado</option>
                            <option value="Prestado">Prestado</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="openEdit = false"
                            class="px-4 py-2 text-sm font-medium rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal: Ver detalle --}}
        <div x-show="openShow" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
            <div @click.outside="openShow = false" x-transition class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Detalle del Vehículo</h2>
                        <p class="text-xs text-gray-400">Información registrada</p>
                    </div>
                    <button @click="openShow = false" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4 text-sm">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Patente</span>
                        <div class="mt-1 text-lg font-bold text-gray-900 dark:text-white" x-text="showVehiculo.patente"></div>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Descripción</span>
                        <div class="mt-1 text-gray-900 dark:text-white" x-text="showVehiculo.descripcion || 'Sin descripción'"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Tipo</span>
                            <div class="mt-1">
                                <span class="badge-pill badge-blue" x-text="showVehiculo.tipo"></span>
                            </div>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Fecha registro</span>
                            <div class="mt-1 text-gray-900 dark:text-white" x-text="showVehiculo.fecha"></div>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Usuario</span>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                <span class="text-[10px] font-bold text-gray-500" x-text="showVehiculo.usuario?.substring(0,2).toUpperCase()"></span>
                            </div>
                            <span class="text-gray-900 dark:text-white" x-text="showVehiculo.usuario"></span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
                    <button @click="openShow = false"
                        class="flex-1 px-4 py-2.5 text-sm font-medium rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition">
                        Cerrar
                    </button>
                    <button @click="openShow = false; openEdit = true; editVehiculo = showVehiculo;"
                        class="flex-1 px-4 py-2.5 text-sm font-medium rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm">
                        Editar
                    </button>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
