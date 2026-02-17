<x-app-layout x-data="{
    open: false,
    deleteId: null,
    createOpen: false,
}" class="min-h-screen">

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 w-full">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-orange-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Productos</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Inventario de combustibles</p>
                </div>
            </div>

            <button @click="createOpen=true" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                   bg-emerald-600 hover:bg-emerald-700 active:scale-95
                   text-white transition shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                <span class="hidden sm:inline">Nuevo Producto</span>
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

        .prog-track { height:6px; background:#f1f5f9; border-radius:99px; overflow:hidden; min-width:80px }
        .dark .prog-track { background:#1e2a3b }
        .prog-fill { height:100%; border-radius:99px }

        .badge-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700 }
        .badge-ok { background:#dcfce7; color:#15803d } .dark .badge-ok { background:rgba(22,163,74,.15); color:#4ade80 }
        .badge-warn { background:#fef3c7; color:#92400e } .dark .badge-warn { background:rgba(234,179,8,.15); color:#fcd34d }
        .badge-crit { background:#fee2e2; color:#dc2626 } .dark .badge-crit { background:rgba(220,38,38,.15); color:#f87171 }
    </style>

    {{-- Modal crear --}}
    <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
        <div @click.outside="createOpen=false" class="bg-white dark:bg-gray-900 rounded-2xl p-6 w-full max-w-md shadow-2xl">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Nuevo Producto</h2>
            <form method="POST" action="{{ route('fuelcontrol.productos.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Producto</label>
                    <select name="nombre" required class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="">Selecciona un producto</option>
                        <option value="Di&eacute;sel">Di&eacute;sel</option>
                        <option value="Gasolina">Gasolina</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Cantidad (L)</label>
                    <input type="number" step="0.01" name="cantidad" required class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="createOpen=false" class="px-4 py-2 text-sm rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 text-sm rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 font-semibold">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal eliminar --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
        <div @click.outside="open=false" class="bg-white dark:bg-gray-900 rounded-2xl p-6 w-full max-w-sm shadow-2xl">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Confirmar eliminaci&oacute;n</h2>
            <p class="text-sm text-gray-500 mb-4">Esta acci&oacute;n no se puede deshacer.</p>
            <div class="flex justify-end gap-2">
                <button @click="open=false" class="px-4 py-2 text-sm rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">Cancelar</button>
                <button @click="document.getElementById('delete-form-' + deleteId)?.submit()" class="px-4 py-2 text-sm rounded-xl bg-red-600 text-white hover:bg-red-700 font-semibold">Eliminar</button>
            </div>
        </div>
    </div>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            {{-- Stat cards --}}
            @if($productos->isNotEmpty())
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 au d1">
                    @foreach ($productos as $p)
                        @php
                            $capacidad = strtolower($p->nombre) === 'diesel' ? 10000 : 100;
                            $pct = min(100, max(0, round(($p->cantidad / $capacidad) * 100)));
                        @endphp
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">{{ ucfirst($p->nombre) }}</p>
                                <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ number_format($p->cantidad, 2) }} <span class="text-xs text-gray-400">L</span></p>
                            </div>
                            <div class="w-9 h-9 rounded-xl {{ $pct < 20 ? 'bg-rose-50 dark:bg-rose-900/20' : ($pct < 50 ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-emerald-50 dark:bg-emerald-900/20') }} flex items-center justify-center">
                                <svg class="w-4 h-4 {{ $pct < 20 ? 'text-rose-500' : ($pct < 50 ? 'text-amber-500' : 'text-emerald-500') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                            </div>
                        </div>
                    @endforeach
                    <div class="stat-card">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Normal</p>
                            <p class="text-xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">{{ $productos->filter(fn($p) => $p->cantidad >= 50)->count() }}</p>
                        </div>
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Bajo</p>
                            <p class="text-xl font-black text-rose-600 dark:text-rose-400 tabular-nums">{{ $productos->filter(fn($p) => $p->cantidad < 20)->count() }}</p>
                        </div>
                        <div class="w-9 h-9 rounded-xl bg-rose-50 dark:bg-rose-900/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Desktop table --}}
            <div class="hidden sm:block panel au d2">
                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="r">Stock (L)</th>
                                <th>Nivel</th>
                                <th class="c">Estado</th>
                                <th class="c">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($productos as $p)
                                @php
                                    $capacidad = strtolower($p->nombre) === 'diesel' ? 10000 : 100;
                                    $pct = min(100, max(0, round(($p->cantidad / $capacidad) * 100)));
                                    [$barColor, $badgeClass, $estado] = match(true) {
                                        $pct < 20 => ['bg-rose-500', 'badge-crit', 'Cr&iacute;tico'],
                                        $pct < 50 => ['bg-amber-400', 'badge-warn', 'Bajo'],
                                        default   => ['bg-emerald-500', 'badge-ok', 'Normal'],
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-[10px] font-bold text-orange-600 dark:text-orange-400 shrink-0">
                                                {{ strtoupper(substr($p->nombre, 0, 1)) }}
                                            </div>
                                            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ ucfirst($p->nombre) }}</span>
                                        </div>
                                    </td>
                                    <td class="text-right font-bold tabular-nums text-gray-800 dark:text-gray-100">{{ number_format($p->cantidad, 2) }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="prog-track flex-1"><div class="{{ $barColor }} prog-fill" style="width:{{ $pct }}%"></div></div>
                                            <span class="text-[11px] font-bold text-gray-400 w-9 text-right tabular-nums">{{ $pct }}%</span>
                                        </div>
                                    </td>
                                    <td class="text-center"><span class="badge-pill {{ $badgeClass }}">{{ $estado }}</span></td>
                                    <td class="text-center">
                                        <div class="flex justify-center gap-1.5">
                                            <a href="{{ route('fuelcontrol.productos.edit', $p->id) }}"
                                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400 hover:bg-indigo-100 transition">Editar</a>
                                            <button @click="open=true; deleteId={{ $p->id }}"
                                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 hover:bg-red-100 transition">Eliminar</button>
                                            <form id="delete-form-{{ $p->id }}" action="{{ route('fuelcontrol.productos.destroy', $p->id) }}" method="POST" class="hidden">@csrf @method('DELETE')</form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-14 text-center text-sm text-gray-400">No hay productos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile cards --}}
            <div class="sm:hidden space-y-2 au d2">
                @forelse ($productos as $p)
                    @php
                        $capacidad = strtolower($p->nombre) === 'diesel' ? 10000 : 100;
                        $pct = min(100, max(0, round(($p->cantidad / $capacidad) * 100)));
                        [$barColor, $badgeClass, $estado] = match(true) {
                            $pct < 20 => ['bg-rose-500', 'badge-crit', 'Cr&iacute;tico'],
                            $pct < 50 => ['bg-amber-400', 'badge-warn', 'Bajo'],
                            default   => ['bg-emerald-500', 'badge-ok', 'Normal'],
                        };
                    @endphp
                    <div class="m-card">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-sm font-bold text-orange-600 dark:text-orange-400">
                                    {{ strtoupper(substr($p->nombre, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ ucfirst($p->nombre) }}</p>
                                    <span class="badge-pill {{ $badgeClass }} text-[10px]">{{ $estado }}</span>
                                </div>
                            </div>
                            <p class="text-lg font-black text-gray-900 dark:text-gray-100 tabular-nums">
                                {{ number_format($p->cantidad, 2) }}
                                <span class="text-xs text-gray-400">L</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 mb-3">
                            <div class="prog-track flex-1"><div class="{{ $barColor }} prog-fill" style="width:{{ $pct }}%"></div></div>
                            <span class="text-[11px] font-bold text-gray-400 tabular-nums">{{ $pct }}%</span>
                        </div>
                        <div class="flex items-center justify-end gap-2 border-t border-gray-100 dark:border-gray-800 pt-2.5">
                            <a href="{{ route('fuelcontrol.productos.edit', $p->id) }}"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400">Editar</a>
                            <button @click="open=true; deleteId={{ $p->id }}"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400">Eliminar</button>
                            <form id="delete-form-{{ $p->id }}" action="{{ route('fuelcontrol.productos.destroy', $p->id) }}" method="POST" class="hidden">@csrf @method('DELETE')</form>
                        </div>
                    </div>
                @empty
                    <div class="m-card text-center text-sm text-gray-400 py-12">No hay productos registrados.</div>
                @endforelse
            </div>

        </div>
    </div>

</x-app-layout>
