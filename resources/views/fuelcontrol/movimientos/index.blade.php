<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-orange-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Movimientos</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Historial de ingresos y salidas</p>
                </div>
            </div>
        </div>
    </x-slot>

    <style>
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(8px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .au {
            animation: fadeUp .4s cubic-bezier(.22, 1, .36, 1) both
        }

        .d1 {
            animation-delay: .04s
        }

        .d2 {
            animation-delay: .08s
        }

        .d3 {
            animation-delay: .12s
        }

        .d4 {
            animation-delay: .16s
        }

        .page-bg {
            background: #f1f5f9;
            min-height: 100%
        }

        .dark .page-bg {
            background: #0d1117
        }

        .panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            overflow: hidden
        }

        .dark .panel {
            background: #161c2c;
            border-color: #1e2a3b
        }

        .dt {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px
        }

        .dt thead tr {
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9
        }

        .dark .dt thead tr {
            background: #111827;
            border-bottom-color: #1e2a3b
        }

        .dt th {
            padding: 10px 16px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #94a3b8;
            white-space: nowrap
        }

        .dt th.r {
            text-align: right
        }

        .dt th.c {
            text-align: center
        }

        .dt td {
            padding: 12px 16px;
            border-bottom: 1px solid #f8fafc;
            color: #334155;
            vertical-align: middle
        }

        .dark .dt td {
            border-bottom-color: #1a2232;
            color: #cbd5e1
        }

        .dt tbody tr:last-child td {
            border-bottom: none
        }

        .dt tbody tr:hover td {
            background: #f8fafc
        }

        .dark .dt tbody tr:hover td {
            background: #1a2436
        }

        .m-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 16px
        }

        .dark .m-card {
            background: #161c2c;
            border-color: #1e2a3b
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .dark .stat-card {
            background: #161c2c;
            border-color: #1e2a3b
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700
        }

        .badge-pendiente {
            background: #fef3c7;
            color: #92400e
        }

        .badge-aprobado {
            background: #dcfce7;
            color: #15803d
        }

        .badge-rechazado {
            background: #fee2e2;
            color: #dc2626
        }

        .dark .badge-pendiente {
            background: rgba(234, 179, 8, .15);
            color: #fcd34d
        }

        .dark .badge-aprobado {
            background: rgba(22, 163, 74, .15);
            color: #4ade80
        }

        .dark .badge-rechazado {
            background: rgba(220, 38, 38, .15);
            color: #f87171
        }

        .tipo-entrada {
            background: #dcfce7;
            color: #15803d
        }

        .tipo-salida {
            background: #fee2e2;
            color: #dc2626
        }

        .dark .tipo-entrada {
            background: rgba(22, 163, 74, .15);
            color: #4ade80
        }

        .dark .tipo-salida {
            background: rgba(220, 38, 38, .15);
            color: #f87171
        }

        .flt-select {
            padding: 6px 26px 6px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            outline: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
        }

        .flt-select:focus {
            border-color: #6366f1
        }

        .dark .flt-select {
            background-color: #161c2c;
            border-color: #1e2a3b;
            color: #94a3b8;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E")
        }

        .btn-xml {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            background: #eef2ff;
            color: #4f46e5;
            transition: background .15s
        }

        .btn-xml:hover {
            background: #e0e7ff
        }

        .dark .btn-xml {
            background: rgba(99, 102, 241, .15);
            color: #a5b4fc
        }

        .dark .btn-xml:hover {
            background: rgba(99, 102, 241, .25)
        }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            {{-- ── STAT CARDS ─────────────────────────────── --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 au d1">
                <div class="stat-card">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Total</p>
                        <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums">
                            {{ $movimientos->total() }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Pendientes</p>
                        <p class="text-xl font-black text-amber-600 dark:text-amber-400 tabular-nums">
                            {{ $movimientos->where('estado', 'pendiente')->count() }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Aprobados</p>
                        <p class="text-xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                            {{ $movimientos->where('estado', 'aprobado')->count() }}</p>
                    </div>
                    <div
                        class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Rechazados</p>
                        <p class="text-xl font-black text-rose-600 dark:text-rose-400 tabular-nums">
                            {{ $movimientos->where('estado', 'rechazado')->count() }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-rose-50 dark:bg-rose-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- ── FILTROS ────────────────────────────────── --}}
            <form id="formFiltros" method="GET" action="{{ route('fuelcontrol.movimientos') }}" class="panel au d1">
                <div class="px-4 py-3 flex flex-wrap items-center gap-2">
                    <select name="estado" onchange="this.form.submit()" class="flt-select">
                        <option value="">Estado — todos</option>
                        <option value="pendiente" {{ request('estado') == 'pendiente' ? 'selected' : '' }}>Pendiente
                        </option>
                        <option value="aprobado" {{ request('estado') == 'aprobado' ? 'selected' : '' }}>Aprobado</option>
                        <option value="rechazado" {{ request('estado') == 'rechazado' ? 'selected' : '' }}>Rechazado
                        </option>
                    </select>
                    <select name="tipo" onchange="this.form.submit()" class="flt-select">
                        <option value="">Tipo — todos</option>
                        <option value="entrada" {{ request('tipo') == 'entrada' ? 'selected' : '' }}>Entrada</option>
                        <option value="salida" {{ request('tipo') == 'salida' ? 'selected' : '' }}>Salida</option>
                    </select>
                    <select name="producto_id" onchange="this.form.submit()" class="flt-select">
                        <option value="">Producto — todos</option>
                        @foreach($productos ?? [] as $producto)
                            <option value="{{ $producto->id }}" {{ request('producto_id') == $producto->id ? 'selected' : '' }}>
                                {{ ucfirst($producto->nombre) }}
                            </option>
                        @endforeach
                    </select>
                    <select name="fecha" onchange="this.form.submit()" class="flt-select">
                        <option value="">Fecha — todas</option>
                        <option value="hoy" {{ request('fecha') == 'hoy' ? 'selected' : '' }}>Hoy</option>
                        <option value="semana" {{ request('fecha') == 'semana' ? 'selected' : '' }}>Esta semana</option>
                        <option value="mes" {{ request('fecha') == 'mes' ? 'selected' : '' }}>Este mes</option>
                        <option value="trimestre" {{ request('fecha') == 'trimestre' ? 'selected' : '' }}>Trimestre
                        </option>
                    </select>

                    @if(request('estado') || request('tipo') || request('producto_id') || request('fecha'))
                        <a href="{{ route('fuelcontrol.movimientos') }}"
                            class="text-xs font-semibold text-gray-500 hover:text-red-600 transition px-2 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                            Limpiar
                        </a>
                    @endif

                    <div class="ml-auto text-xs text-gray-400 hidden sm:block">
                        {{ $movimientos->total() }} registros
                    </div>
                </div>
            </form>

            {{-- ── TABLA DESKTOP ──────────────────────────── --}}
            <div class="hidden lg:block panel au d2">
                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th class="r">Cantidad</th>
                                <th class="c">Estado</th>
                                <th class="c">Fecha</th>
                                <th class="c">Usuario</th>
                                <th class="c">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($movimientos as $m)
                                @php
                                    $isEntrada = strtolower(trim($m->tipo)) === 'entrada';
                                    $nombreUsuario = $m->usuario;
                                    if (in_array($nombreUsuario, ['gmail', 'gmail_historico']))
                                        $nombreUsuario = 'Carga Autom.';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="w-7 h-7 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-[10px] font-bold text-orange-600 dark:text-orange-400 shrink-0">
                                                {{ strtoupper(substr($m->producto_nombre ?? 'N', 0, 1)) }}
                                            </div>
                                            <span
                                                class="font-semibold text-gray-800 dark:text-gray-100">{{ ucfirst($m->producto_nombre ?? 'N/A') }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-pill {{ $isEntrada ? 'tipo-entrada' : 'tipo-salida' }}">
                                            {{ $isEntrada ? 'Ingreso' : 'Salida' }}
                                        </span>
                                    </td>
                                    <td
                                        class="text-right font-bold tabular-nums {{ $isEntrada ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                        {{ $isEntrada ? '+' : '-' }}{{ number_format(abs($m->cantidad), 2) }}
                                        <span class="text-xs text-gray-400 ml-0.5">L</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-pill badge-{{ $m->estado }}">
                                            {{ ucfirst($m->estado) }}
                                        </span>
                                    </td>
                                    <td class="text-center text-sm text-gray-600 dark:text-gray-400">
                                        {{ \Carbon\Carbon::parse($m->fecha_movimiento)->format('d/m/Y') }}
                                    </td>
                                    <td class="text-center">
                                        @if($m->usuario)
                                            <div class="flex items-center justify-center gap-2">
                                                <div
                                                    class="w-6 h-6 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-[9px] font-bold text-indigo-600 dark:text-indigo-400">
                                                    {{ strtoupper(substr($m->usuario, 0, 1)) }}
                                                </div>
                                                <span
                                                    class="text-xs text-gray-600 dark:text-gray-400">{{ $nombreUsuario }}</span>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-300 dark:text-gray-700">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($m->xml_path)
                                            <button onclick="abrirMovimiento('{{ route('fuelcontrol.xml.show', $m->id) }}')"
                                                class="btn-xml">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                XML
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-300 dark:text-gray-700">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-14 text-center text-sm text-gray-400">
                                        No hay movimientos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Paginación desktop --}}
            @if($movimientos->hasPages())
                <div class="hidden lg:block au d3">{{ $movimientos->links() }}</div>
            @endif

            {{-- ── CARDS MÓVIL ────────────────────────────── --}}
            <div class="lg:hidden space-y-2 au d2">
                @forelse ($movimientos as $m)
                    @php
                        $isEntrada = strtolower(trim($m->tipo)) === 'entrada';
                        $nombreUsuario = $m->usuario;
                        if (in_array($nombreUsuario, ['gmail', 'gmail_historico']))
                            $nombreUsuario = 'Carga Autom.';
                    @endphp
                    <div class="m-card">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div
                                    class="w-9 h-9 rounded-xl {{ $isEntrada ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-rose-50 dark:bg-rose-900/20' }} flex items-center justify-center shrink-0">
                                    @if($isEntrada)
                                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">
                                        {{ ucfirst($m->producto_nombre ?? 'N/A') }}</p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span
                                            class="badge-pill text-[10px] {{ $isEntrada ? 'tipo-entrada' : 'tipo-salida' }}">{{ $isEntrada ? 'Ingreso' : 'Salida' }}</span>
                                        <span
                                            class="text-[11px] text-gray-400">{{ \Carbon\Carbon::parse($m->fecha_movimiento)->format('d/m/Y') }}</span>
                                    </div>
                                </div>
                            </div>
                            <p
                                class="text-sm font-black tabular-nums shrink-0 {{ $isEntrada ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $isEntrada ? '+' : '-' }}{{ number_format(abs($m->cantidad), 2) }} L
                            </p>
                        </div>

                        <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-2.5">
                            <div class="flex items-center gap-2">
                                <span class="badge-pill badge-{{ $m->estado }} text-[10px]">{{ ucfirst($m->estado) }}</span>
                                @if($m->usuario)
                                    <span class="text-[11px] text-gray-400">{{ $nombreUsuario }}</span>
                                @endif
                            </div>
                            @if($m->xml_path)
                                <button onclick="abrirMovimiento('{{ route('fuelcontrol.xml.show', $m->id) }}')"
                                    class="btn-xml">XML</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="m-card text-center text-sm text-gray-400 py-12">
                        No hay movimientos registrados.
                    </div>
                @endforelse
            </div>

            {{-- Paginación móvil --}}
            @if($movimientos->hasPages())
                <div class="lg:hidden au d3">{{ $movimientos->links() }}</div>
            @endif

        </div>
    </div>

    <script>
        window.abrirMovimiento = async function (url) {
            try {
                const html = await (await fetch(url)).text();
                await Swal.fire({ width: '85%', showCloseButton: true, showConfirmButton: false, html, background: '#f9fafb' });
            } catch {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el XML.' });
            }
        };
    </script>
    <script>
        window.switchTab = function (tab) {

            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            const content = document.getElementById('content-' + tab);
            if (content) content.classList.remove('hidden');

            const activeTab = document.getElementById('tab-' + tab);
            if (activeTab) {
                activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
                activeTab.classList.remove('border-transparent', 'text-gray-500');
            }
        };
    </script>
</x-app-layout>