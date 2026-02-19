<x-app-layout>

    {{-- ═══════════════════════════════════════════════════
    HEADER
    ═══════════════════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Usuarios</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Gestión de accesos al sistema</p>
                </div>
            </div>
            <div class="hidden sm:flex items-center gap-1.5 text-xs text-gray-400">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                En línea · {{ now()->format('d M Y, H:i') }}
            </div>
        </div>
    </x-slot>

    {{-- ═══════════════════════════════════════════════════
    INICIALIZAR STORE Alpine.js
    DEBE ir ANTES de cualquier uso de $store.ui
    ═══════════════════════════════════════════════════ --}}



    {{-- SweetAlert2 success flash --}}
    @php($success = session()->pull('success'))
    @if($success && request()->isMethod('GET'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: 'success', title: '¡Listo!',
                    text: @json($success),
                    timer: 2000, showConfirmButton: false
                });
            });
        </script>
    @endif

    <style>
        [x-cloak] {
            display: none !important;
        }

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
            animation-delay: .09s
        }

        .d3 {
            animation-delay: .14s
        }

        .page-bg {
            background: #f1f5f9;
            min-height: 100%
        }

        .dark .page-bg {
            background: #0d1117
        }

        /* Panel */
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

        .panel-head {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px
        }

        .dark .panel-head {
            border-bottom-color: #1e2a3b
        }

        /* Icon dot */
        .icon-dot {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center
        }

        /* Table */
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

        /* Mobile card */
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

        /* Status badge */
        .badge-active {
            background: #dcfce7;
            color: #15803d;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px
        }

        .badge-inactive {
            background: #f1f5f9;
            color: #64748b;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center
        }

        .dark .badge-active {
            background: rgba(22, 163, 74, .15);
            color: #4ade80
        }

        .dark .badge-inactive {
            background: rgba(255, 255, 255, .06);
            color: #64748b
        }

        /* ID badge */
        .id-badge {
            font-family: monospace;
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 5px
        }

        .dark .id-badge {
            background: rgba(255, 255, 255, .06);
            color: #475569
        }

        /* Email chip */
        .email-chip {
            font-size: 12px;
            color: #6366f1;
            font-weight: 600
        }

        .dark .email-chip {
            color: #a5b4fc
        }

        /* Action buttons */
        .act-btn {
            padding: 6px;
            border-radius: 8px;
            transition: background .15s, color .15s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            cursor: pointer
        }

        .act-view {
            color: #64748b
        }

        .act-view:hover {
            background: #eef2ff;
            color: #4f46e5
        }

        .dark .act-view {
            color: #475569
        }

        .dark .act-view:hover {
            background: rgba(99, 102, 241, .15);
            color: #a5b4fc
        }

        .act-del {
            color: #94a3b8
        }

        .act-del:hover {
            background: #fee2e2;
            color: #dc2626
        }

        .dark .act-del {
            color: #475569
        }

        .dark .act-del:hover {
            background: rgba(220, 38, 38, .15);
            color: #f87171
        }

        /* Form inputs */
        .f-input {
            width: 100%;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 8px 12px;
            font-size: 13px;
            color: #1e293b;
            transition: border-color .15s, box-shadow .15s;
            outline: none
        }

        .f-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .12)
        }

        .dark .f-input {
            border-color: #1e2a3b;
            background: #0d1117;
            color: #f1f5f9
        }

        .dark .f-input:focus {
            border-color: #6366f1
        }

        .f-input::placeholder {
            color: #94a3b8
        }

        .f-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 5px
        }

        .dark .f-label {
            color: #475569
        }

        .f-error {
            font-size: 11px;
            color: #dc2626;
            margin-top: 4px
        }

        .dark .f-error {
            color: #f87171
        }

        /* Submit button */
        .btn-submit {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            background: #4f46e5;
            color: #fff;
            border: none;
            cursor: pointer;
            transition: background .15s
        }

        .btn-submit:hover {
            background: #4338ca
        }

        .btn-submit:active {
            transform: scale(.97)
        }

        /* FAB mobile */
        .fab {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: #4f46e5;
            color: #fff;
            border: none;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(99, 102, 241, .4);
            transition: background .15s, transform .1s
        }

        .fab:hover {
            background: #4338ca
        }

        .fab:active {
            transform: scale(.93)
        }

        /* Section label */
        .s-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: #94a3b8
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

        .section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #94a3b8
        }

        .search-wrap {
            position: relative
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            color: #9ca3af;
            pointer-events: none
        }

        .search-input {
            width: 100%;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 10px 12px 10px 35px;
            font-size: 13px;
            color: #111827;
            outline: none;
            transition: border-color .15s, box-shadow .15s
        }

        .search-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .12)
        }

        .dark .search-input {
            border-color: #1e2a3b;
            background: #0d1117;
            color: #f1f5f9
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase
        }

        .role-admin {
            background: #fee2e2;
            color: #b91c1c
        }

        .role-viewer {
            background: #e0e7ff;
            color: #4338ca
        }

        .dark .role-admin {
            background: rgba(220, 38, 38, .15);
            color: #f87171
        }

        .dark .role-viewer {
            background: rgba(99, 102, 241, .15);
            color: #a5b4fc
        }

        .switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 42px;
            height: 24px;
            border-radius: 999px;
            transition: background-color .15s
        }

        .switch-dot {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .18);
            transition: transform .15s
        }

        /* Empty state */
        .empty-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px
        }

        .dark .empty-icon {
            background: rgba(255, 255, 255, .06)
        }
    </style>

    {{-- FAB móvil --}}
    <button class="fab flex lg:hidden"
        @click="$store.ui.open = true; $store.ui.openView = false; $store.ui.selectedUser = null"
        aria-label="Nuevo usuario">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
    </button>

    <div class="page-bg" x-data="usersDashboard(@js($movimientos))">

        {{-- Modales --}}
        @include('users.partials.modals')
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

            <p class="section-label mb-3 au d1">Resumen</p>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                <div class="stat-card au d1">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Total</p>
                        <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums" x-text="users.length"></p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </div>

                <div class="stat-card au d2">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Activos</p>
                        <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums" x-text="activeCount"></p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>

                <div class="stat-card au d3 col-span-2 sm:col-span-1">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Inactivos</p>
                        <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums" x-text="inactiveCount"></p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

                {{-- ── TABLA DESKTOP ─────────────────────── --}}
                <div class="lg:col-span-8 xl:col-span-9">
                    <div class="hidden sm:block panel au d1">
                        <div class="panel-head">
                            <div class="flex items-center gap-2.5">
                                <div class="icon-dot bg-indigo-50 dark:bg-indigo-900/30">
                                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Usuarios registrados</p>
                                    <p class="text-xs text-gray-400">
                                        <span x-text="filteredUsers.length"></span> visibles
                                        <span class="mx-1">/</span>
                                        <span x-text="users.length"></span> en el sistema
                                    </p>
                                </div>
                            </div>

                            <div class="w-full max-w-sm hidden md:block">
                                <div class="search-wrap">
                                    <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                                    </svg>
                                    <input x-model="q" type="text" class="search-input"
                                        placeholder="Buscar por nombre, email o rol...">
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="dt">
                                <thead>
                                    <tr>
                                        <th class="w-12">ID</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th class="r w-24">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="user in filteredUsers" :key="user.id">
                                        <tr>
                                            <td><span class="id-badge" x-text="'#' + user.id"></span></td>

                                            <td>
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-900/40
                                                        flex items-center justify-center text-[10px] font-bold
                                                        text-indigo-600 dark:text-indigo-400 shrink-0"
                                                        x-text="user.name.charAt(0).toUpperCase()"></div>
                                                    <span class="font-semibold text-gray-800 dark:text-gray-100"
                                                        x-text="user.name"></span>
                                                </div>
                                            </td>

                                            <td><span class="email-chip" x-text="user.email"></span></td>

                                            <td>
                                                <span class="role-badge"
                                                    :class="(user.role || 'viewer') === 'admin' ? 'role-admin' : 'role-viewer'"
                                                    x-text="(user.role || 'viewer')"></span>
                                            </td>

                                            <td>
                                                <div class="inline-flex items-center gap-2">
                                                    <button type="button" role="switch" :aria-checked="user.is_active"
                                                        class="switch focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                        :class="user.is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-700'"
                                                        @click="toggleActive(user)">
                                                        <span class="switch-dot"
                                                            :class="user.is_active ? 'translate-x-[18px]' : 'translate-x-0'"></span>
                                                    </button>
                                                    <span :class="user.is_active ? 'badge-active' : 'badge-inactive'"
                                                        x-text="user.is_active ? 'Activo' : 'Inactivo'"></span>
                                                </div>
                                            </td>

                                            <td class="text-right">
                                                <div class="inline-flex items-center gap-1 justify-end">

                                                    {{-- Ver → abre modal de detalle --}}
                                                    <button type="button" class="act-btn act-view" title="Ver usuario"
                                                        @click="
   $store.ui.selectedUser = user;
    $store.ui.open = false;
    $store.ui.openView = true;
">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </button>

                                                    {{-- Eliminar --}}
                                                    <form method="POST" :action="`/users/${user.id}`" class="contents">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="act-btn act-del"
                                                            title="Eliminar usuario" @click="
                                                            const form = $el.closest('form');
                                                            Swal.fire({
                                                                title: '¿Eliminar usuario?',
                                                                text: 'Esta acción no se puede deshacer.',
                                                                icon: 'warning',
                                                                showCancelButton: true,
                                                                confirmButtonColor: '#dc2626',
                                                                confirmButtonText: 'Sí, eliminar',
                                                                cancelButtonText: 'Cancelar'
                                                            }).then(r => { if (r.isConfirmed) form.submit(); });
                                                        ">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <tr x-show="users.length === 0">
                                        <td colspan="6" class="py-14 text-center">
                                            <div class="empty-icon">
                                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="1.5"
                                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </div>
                                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">Sin
                                                usuarios</p>
                                            <p class="text-xs text-gray-400 mt-1">Crea el primero con el formulario.</p>
                                        </td>
                                    </tr>

                                    <tr x-show="users.length > 0 && filteredUsers.length === 0">
                                        <td colspan="6" class="py-14 text-center text-sm text-gray-400">
                                            No se encontraron usuarios para "<span x-text="q"></span>".
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ── CARDS MÓVIL ──────────────────────── --}}
                    <div class="sm:hidden space-y-2 au d1">
                        <div class="mb-2">
                            <div class="search-wrap">
                                <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                                </svg>
                                <input x-model="q" type="text" class="search-input"
                                    placeholder="Buscar por nombre, email o rol...">
                            </div>
                        </div>
                        <div class="flex items-center justify-between mb-1 px-1">
                            <span class="s-label"><span x-text="filteredUsers.length"></span> usuario(s)</span>
                        </div>

                        <template x-for="user in filteredUsers" :key="user.id">
                            <div class="m-card">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-900/40
                                        flex items-center justify-center text-sm font-bold
                                        text-indigo-600 dark:text-indigo-400 shrink-0"
                                        x-text="user.name.charAt(0).toUpperCase()"></div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate"
                                            x-text="user.name"></p>
                                        <p class="text-xs email-chip truncate" x-text="user.email"></p>
                                        <span class="role-badge mt-1"
                                            :class="(user.role || 'viewer') === 'admin' ? 'role-admin' : 'role-viewer'"
                                            x-text="(user.role || 'viewer')"></span>
                                    </div>
                                    <span :class="user.is_active ? 'badge-active' : 'badge-inactive'">
                                        <button type="button" role="switch" :aria-checked="user.is_active"
                                            class="switch mr-2 align-middle focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            :class="user.is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-700'"
                                            @click="toggleActive(user)">
                                            <span class="switch-dot"
                                                :class="user.is_active ? 'translate-x-[18px]' : 'translate-x-0'"></span>
                                        </button>
                                        <span x-text="user.is_active ? 'Activo' : 'Inactivo'"></span>
                                    </span>
                                </div>

                                <div
                                    class="flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-2.5">
                                    <span class="id-badge" x-text="'#' + user.id"></span>
                                    <div class="flex items-center gap-1">
                                        <button type="button" class="act-btn act-view" @click="
                                            $store.ui.selectedUser = user;
                                            $store.ui.open = false;
                                            $store.ui.openView = true;
                                        ">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <form method="POST" :action="`/users/${user.id}`" class="contents">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="act-btn act-del" @click="
                                                const form = $el.closest('form');
                                                Swal.fire({
                                                    title: '¿Eliminar usuario?',
                                                    text: 'Esta acción no se puede deshacer.',
                                                    icon: 'warning',
                                                    showCancelButton: true,
                                                    confirmButtonColor: '#dc2626',
                                                    confirmButtonText: 'Eliminar',
                                                    cancelButtonText: 'Cancelar'
                                                }).then(r => { if (r.isConfirmed) form.submit(); });
                                            ">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div x-show="users.length === 0" class="m-card text-center text-sm text-gray-400 py-12">
                            No hay usuarios registrados.
                        </div>
                        <div x-show="users.length > 0 && filteredUsers.length === 0"
                            class="m-card text-center text-sm text-gray-400 py-12">
                            No hay resultados para "<span x-text="q"></span>".
                        </div>
                    </div>
                </div>

                {{-- ── FORM LATERAL (sticky) ─────────────── --}}
                <div id="nuevo-usuario" class="hidden lg:flex lg:col-span-4 xl:col-span-3 flex-col gap-4 sticky top-6 au d2">
                    <div class="panel">
                        <div class="panel-head">
                            <div class="icon-dot bg-indigo-50 dark:bg-indigo-900/30">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Nuevo usuario</p>
                                <p class="text-xs text-gray-400">Completa para registrar</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('users.store') }}" class="p-5 space-y-4">
                            @csrf

                            <div>
                                <label class="f-label" for="u-name">Nombre</label>
                                <input id="u-name" name="name" value="{{ old('name') }}" required class="f-input"
                                    placeholder="Ej: Juan Pérez">
                                @error('name')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="f-label" for="u-email">Email</label>
                                <input id="u-email" type="email" name="email" value="{{ old('email') }}" required
                                    class="f-input" placeholder="correo@empresa.cl">
                                @error('email')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="f-label" for="u-pass">Contraseña</label>
                                <input id="u-pass" type="password" name="password" required class="f-input"
                                    placeholder="••••••••">
                                @error('password')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="pt-1 border-t border-gray-100 dark:border-gray-800">
                                <button type="submit" class="btn-submit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    Guardar usuario
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-800
                        px-4 py-3 text-xs text-gray-400 leading-relaxed">
                        Los usuarios nuevos recibirán acceso al sistema con el email y contraseña indicados.
                        Puedes editar o desactivarlos desde la vista de detalle.
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function usersDashboard(initialUsers) {
            return {
                q: '',
                users: Array.isArray(initialUsers) ? initialUsers : [],
                get filteredUsers() {
                    const term = (this.q || '').toLowerCase().trim();
                    if (!term) return this.users;
                    return this.users.filter((u) => {
                        const name = (u.name || '').toLowerCase();
                        const email = (u.email || '').toLowerCase();
                        const role = (u.role || 'viewer').toLowerCase();
                        return name.includes(term) || email.includes(term) || role.includes(term);
                    });
                },
                get activeCount() {
                    return this.users.filter((u) => Boolean(u.is_active)).length;
                },
                get inactiveCount() {
                    return this.users.length - this.activeCount;
                },
                async toggleActive(user) {
                    const prev = Boolean(user.is_active);
                    user.is_active = !prev;
                    const url = '{{ route('users.toggleActive', '__ID__') }}'.replace('__ID__', user.id);
                    try {
                        const res = await fetch(url, {
                            method: 'PATCH',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ is_active: user.is_active })
                        });
                        const data = await res.json();
                        if (!res.ok || !data.ok) throw new Error('update-failed');
                        user.is_active = data.is_active;
                        Swal.fire({ icon:'success', title:'Estado actualizado', text:data.message, timer:1200, showConfirmButton:false });
                    } catch (e) {
                        user.is_active = prev;
                        Swal.fire({ icon:'error', title:'Error', text:'No se pudo actualizar el estado.' });
                    }
                },
                async updateRole(user, role) {
                    const prev = (user.role || 'viewer');
                    user.role = role;
                    const url = '{{ route('users.updateRole', '__ID__') }}'.replace('__ID__', user.id);
                    try {
                        const res = await fetch(url, {
                            method: 'PATCH',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ role })
                        });
                        const data = await res.json();
                        if (!res.ok || !data.ok) throw new Error('update-failed');
                        user.role = data.role;
                        Swal.fire({ icon:'success', title:'Rol actualizado', text:data.message, timer:1200, showConfirmButton:false });
                    } catch (e) {
                        user.role = prev;
                        Swal.fire({ icon:'error', title:'Error', text:'No se pudo actualizar el rol.' });
                    }
                }
            };
        }
    </script>

</x-app-layout>
