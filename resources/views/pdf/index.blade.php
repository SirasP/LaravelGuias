<x-app-layout>

    {{-- ═══════════════════════════════════════════════════
    HEADER — buscador centrado + botón Excel
    ═══════════════════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center gap-3 w-full" x-data="pdfHeader()">

            {{-- Título (solo desktop) --}}
            <div class="hidden sm:block shrink-0">
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">DTE / Facturas</h2>
                <p class="text-xs text-gray-400 mt-0.5">PDFs importados</p>
            </div>
            <div class="hidden sm:block h-5 w-px bg-gray-200 dark:bg-gray-700 shrink-0"></div>

            {{-- Buscador centrado --}}
            <div class="flex-1 flex justify-center">
                <div class="relative w-full max-w-md">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                    </svg>
                    <input x-model="q" type="text" autocomplete="off" placeholder="Archivo, guía, ID, fecha…" class="w-full pl-9 pr-8 py-2 text-sm rounded-xl
                              border border-gray-200 dark:border-gray-700
                              bg-gray-50 dark:bg-gray-900
                              text-gray-900 dark:text-gray-100
                              focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                              outline-none transition placeholder-gray-400">
                    <button x-show="q" @click="q = ''"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Exportar Excel --}}
            <a href="{{ route('pdf.export.xlsx') }}" class="shrink-0 inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl
                  bg-emerald-600 hover:bg-emerald-700 active:scale-95
                  text-white transition-all shadow-sm shadow-emerald-200 dark:shadow-emerald-900">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span class="hidden sm:inline">Exportar Excel</span>
            </a>
        </div>
    </x-slot>

    @php
        /* ── Datos ── */
        $rowsJson = $imports->getCollection()->map(fn($i) => [
            'id' => $i->id,
            'guia' => $i->guia_no,
            'name' => $i->original_name,
            'template' => $i->template ?? '—',
            'doc_fecha' => $i->doc_fecha
                ? \Carbon\Carbon::parse($i->doc_fecha)->format('d-m-Y')
                : null,
            'created_at' => $i->created_at->format('d-m-Y H:i'),
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);

        $isPdfDate = ($orderBy ?? 'doc_fecha') === 'doc_fecha';
        $nextDir = ($dir ?? 'desc') === 'desc' ? 'asc' : 'desc';

        /* ── 8 templates del controller ── */
        $allTemplates = [
            'QC' => 'QC',
            'MP' => 'MP',
            'SANCO' => 'SANCO',
            'RFP' => 'RFP',
            'VT' => 'VT',
            'LIQ_COMPUAGRO' => 'Liq.',
            'GUIA_RECEPCION_RESUMEN' => 'G. Res.',
            'XML_SII_46' => 'XML',
        ];
    @endphp

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

        .t-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden
        }

        .dark .t-card {
            background: #161c2c;
            border-color: #1e2a3b
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
            padding: 11px 16px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #94a3b8;
            white-space: nowrap
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

        /* Botones acción */
        .btn-sm {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            transition: background .15s
        }

        .btn-indigo {
            background: #eef2ff;
            color: #4f46e5
        }

        .btn-indigo:hover {
            background: #e0e7ff
        }

        .btn-gray {
            background: #f1f5f9;
            color: #475569
        }

        .btn-gray:hover {
            background: #e2e8f0
        }

        .dark .btn-indigo {
            background: rgba(99, 102, 241, .15);
            color: #a5b4fc
        }

        .dark .btn-indigo:hover {
            background: rgba(99, 102, 241, .25)
        }

        .dark .btn-gray {
            background: rgba(255, 255, 255, .06);
            color: #94a3b8
        }

        .dark .btn-gray:hover {
            background: rgba(255, 255, 255, .1)
        }

        /* Template pills */
        .tpl {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .04em;
            white-space: nowrap
        }

        .tpl-QC {
            background: #dcfce7;
            color: #15803d
        }

        .dark .tpl-QC {
            background: rgba(16, 185, 129, .15);
            color: #34d399
        }

        .tpl-MP {
            background: #dbeafe;
            color: #1d4ed8
        }

        .dark .tpl-MP {
            background: rgba(59, 130, 246, .15);
            color: #93c5fd
        }

        .tpl-SANCO {
            background: #f3e8ff;
            color: #7c3aed
        }

        .dark .tpl-SANCO {
            background: rgba(139, 92, 246, .15);
            color: #c4b5fd
        }

        .tpl-RFP {
            background: #e0e7ff;
            color: #4338ca
        }

        .dark .tpl-RFP {
            background: rgba(99, 102, 241, .15);
            color: #a5b4fc
        }

        .tpl-VT {
            background: #fef3c7;
            color: #b45309
        }

        .dark .tpl-VT {
            background: rgba(245, 158, 11, .15);
            color: #fcd34d
        }

        .tpl-LIQ {
            background: #ffe4e6;
            color: #be123c
        }

        .dark .tpl-LIQ {
            background: rgba(244, 63, 94, .15);
            color: #fda4af
        }

        .tpl-GRR {
            background: #e0f2fe;
            color: #0369a1
        }

        .dark .tpl-GRR {
            background: rgba(14, 165, 233, .15);
            color: #7dd3fc
        }

        .tpl-XML {
            background: #f1f5f9;
            color: #475569
        }

        .dark .tpl-XML {
            background: rgba(255, 255, 255, .06);
            color: #94a3b8
        }

        .tpl-unknown {
            background: #f8fafc;
            color: #94a3b8
        }
    </style>

    <div class="page-bg" x-data="pdfIndex({{ $rowsJson }})">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-7 space-y-5">

            {{-- Buscador móvil --}}
            <div class="sm:hidden au d1">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                    </svg>
                    <input x-model="q" type="text" inputmode="search" enterkeyhint="search"
                        placeholder="Archivo, guía, ID…" class="w-full pl-9 pr-4 py-2.5 text-sm rounded-xl
                          border border-gray-200 dark:border-gray-700
                          bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100
                          focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none">
                </div>
            </div>

            {{-- Filtros de template (server-side, pill chips) --}}
            <div class="au d1 flex items-center gap-2 flex-wrap">
                <a href="{{ route('pdf.index', array_merge(request()->except('model', 'page'))) }}"
                    class="text-xs font-semibold px-3 py-1.5 rounded-full transition
                  {{ ($model ?? '') === ''
    ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
    : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-400' }}">
                    Todos
                </a>
                @foreach($allTemplates as $tplKey => $tplLabel)
                            <a href="{{ route('pdf.index', array_merge(request()->except('model', 'page'), ['model' => $tplKey])) }}"
                                class="text-xs font-semibold px-3 py-1.5 rounded-full border transition
                                              {{ ($model ?? '') === $tplKey
                    ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900 border-transparent'
                    : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-400' }}">
                                {{ $tplLabel }}
                            </a>
                @endforeach
            </div>

            {{-- Flash ok --}}
            @if(session('ok'))
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800
                            px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300 au d1">
                    {{ session('ok') }}
                </div>
            @endif

            {{-- Contador + limpiar --}}
            <div class="flex items-center justify-between au d1">
                <p class="text-xs text-gray-400">
                    <span x-text="filtered.length"></span>
                    <span x-text="filtered.length === 1 ? 'documento' : 'documentos'"></span>
                    <template x-if="q">
                        <span> · "<strong x-text="q" class="text-gray-600 dark:text-gray-300"></strong>"</span>
                    </template>
                </p>
                <template x-if="q">
                    <button @click="q = ''" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                        Limpiar ×
                    </button>
                </template>
            </div>

            {{-- ── TABLA DESKTOP ──────────────────────────── --}}
            <div class="hidden lg:block t-card au d2">
                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th class="w-14">ID</th>
                                <th class="w-28">Guía</th>
                                <th>Archivo</th>
                                <th class="w-28">Modelo</th>
                                <th class="w-32">
                                    <a href="{{ request()->fullUrlWithQuery(['order_by' => 'doc_fecha', 'dir' => $isPdfDate ? $nextDir : 'desc']) }}"
                                        class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 transition cursor-pointer">
                                        Fecha PDF
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            @if($isPdfDate && ($dir ?? 'desc') === 'asc')
                                                <path
                                                    d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" />
                                            @else
                                                <path
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                            @endif
                                        </svg>
                                    </a>
                                </th>
                                <th class="w-36">Importado</th>
                                <th class="w-40 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="r in filtered" :key="r.id">
                                <tr>
                                    <td class="text-gray-400 text-xs font-mono" x-text="r.id"></td>
                                    <td>
                                        <span class="font-bold font-mono text-indigo-600 dark:text-indigo-400"
                                            x-text="r.guia ?? '—'"></span>
                                    </td>
                                    <td>
                                        <span
                                            class="block max-w-sm truncate font-medium text-gray-800 dark:text-gray-200"
                                            :title="r.name" x-text="r.name"></span>
                                    </td>
                                    <td>
                                        <span class="tpl" :class="tplClass(r.template)" x-text="r.template"></span>
                                    </td>
                                    <td class="text-gray-500 dark:text-gray-400 text-sm" x-text="r.doc_fecha ?? '—'">
                                    </td>
                                    <td class="text-gray-400 text-xs" x-text="r.created_at"></td>
                                    <td class="text-right">
                                        <div class="inline-flex items-center gap-1.5">

                                            {{-- Detalle --}}
                                            <a :href="`{{ route('pdf.import.ver', '__ID__') }}`.replace('__ID__', r.id)"
                                                class="btn-sm btn-indigo">
                                                Detalle
                                            </a>

                                            {{-- PDF --}}
                                            <a :href="`{{ route('pdf.import.archivo', '__ID__') }}`.replace('__ID__', r.id)"
                                                target="_blank" class="btn-sm btn-gray">
                                                PDF
                                            </a>

                                            {{-- JSON --}}
                                            <a :href="`{{ route('pdf.import.json', '__ID__') }}`.replace('__ID__', r.id)"
                                                target="_blank" class="btn-sm btn-gray">
                                                JSON
                                            </a>

                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filtered.length === 0">
                                <td colspan="7" class="px-4 py-14 text-center text-sm text-gray-400">
                                    No hay documentos que coincidan con "<span x-text="q"></span>".
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Paginación desktop --}}
            <div class="hidden lg:block au d3" data-turbo="false">{{ $imports->links() }}</div>

            {{-- ── CARDS MÓVIL ─────────────────────────────── --}}
            <div class="lg:hidden space-y-2 au d2">
                <template x-for="r in filtered" :key="r.id">
                    <div class="m-card">
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="min-w-0">
                                <p class="font-bold font-mono text-indigo-600 dark:text-indigo-400 text-sm"
                                    x-text="r.guia ?? '—'"></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5" :title="r.name"
                                    x-text="r.name"></p>
                            </div>
                            <span class="tpl shrink-0" :class="tplClass(r.template)" x-text="r.template"></span>
                        </div>

                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs mb-3">
                            <div>
                                <p class="text-gray-400 dark:text-gray-600 mb-0.5">Fecha PDF</p>
                                <p class="font-semibold text-gray-700 dark:text-gray-300" x-text="r.doc_fecha ?? '—'">
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-400 dark:text-gray-600 mb-0.5">Importado</p>
                                <p class="text-gray-600 dark:text-gray-400" x-text="r.created_at"></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 border-t border-gray-100 dark:border-gray-800 pt-2.5">

                            {{-- Detalle --}}
                            <a :href="`{{ route('pdf.import.ver', '__ID__') }}`.replace('__ID__', r.id)"
                                class="btn-sm btn-indigo">
                                Detalle
                            </a>

                            {{-- PDF --}}
                            <a :href="`{{ route('pdf.import.archivo', '__ID__') }}`.replace('__ID__', r.id)"
                                target="_blank" class="btn-sm btn-gray">
                                PDF
                            </a>

                            {{-- JSON --}}
                            <a :href="`{{ route('pdf.import.json', '__ID__') }}`.replace('__ID__', r.id)"
                                target="_blank" class="btn-sm btn-gray">
                                JSON
                            </a>

                        </div>

                    </div>
                </template>
                <div x-show="filtered.length === 0" class="m-card text-center text-sm text-gray-400 py-12">
                    No hay documentos que coincidan.
                </div>
            </div>

            {{-- Paginación móvil --}}
            <div class="lg:hidden au d3" data-turbo="false">{{ $imports->links() }}</div>

            {{-- ── REPORTE IMPORTACIÓN ─────────────────────── --}}
            @if(session('import_report'))
                <div class="t-card au d4">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Detalle de importación</p>
                        <span class="text-xs text-gray-400">{{ count(session('import_report')) }} registros</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Archivo</th>
                                    <th>Estado</th>
                                    <th>Modelo</th>
                                    <th>Guía</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(session('import_report') as $rep)
                                    @php
                                        $st = $rep['status'] ?? '';
                                        $stCls = match ($st) {
                                            'imported' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                            'duplicate' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                            default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                                        };
                                        $repTpl = $rep['template'] ?? '';
                                        $tplCss = match ($repTpl) {
                                            'QC' => 'tpl tpl-QC',
                                            'MP' => 'tpl tpl-MP',
                                            'SANCO' => 'tpl tpl-SANCO',
                                            'RFP' => 'tpl tpl-RFP',
                                            'VT' => 'tpl tpl-VT',
                                            'LIQ_COMPUAGRO' => 'tpl tpl-LIQ',
                                            'GUIA_RECEPCION_RESUMEN' => 'tpl tpl-GRR',
                                            'XML_SII_46' => 'tpl tpl-XML',
                                            default => 'tpl tpl-unknown',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="text-xs text-gray-500 max-w-xs truncate">{{ $rep['file'] ?? '—' }}</td>
                                        <td>
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold {{ $stCls }}">
                                                {{ $st }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($repTpl)
                                                <span class="{{ $tplCss }}">{{ $repTpl }}</span>
                                            @else
                                                <span class="text-gray-300 dark:text-gray-700">—</span>
                                            @endif
                                        </td>
                                        <td class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                            {{ $rep['guia'] ?? '—' }}
                                        </td>
                                        <td class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $rep['reason'] ?? '' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('pdf', { q: '{{ request('q') }}' });
        });

        /* ── index body ── */
        function pdfIndex(rows) {
            return {
                rows,
                get q() { return Alpine.store('pdf').q; },
                set q(val) { Alpine.store('pdf').q = val; },

                get filtered() {
                    const q = (Alpine.store('pdf').q || '').trim().toLowerCase();
                    if (!q) return this.rows;
                    return this.rows.filter(r =>
                        String(r.id).includes(q) ||
                        String(r.guia ?? '').toLowerCase().includes(q) ||
                        (r.name || '').toLowerCase().includes(q) ||
                        (r.doc_fecha || '').toLowerCase().includes(q) ||
                        (r.created_at || '').toLowerCase().includes(q) ||
                        (r.template || '').toLowerCase().includes(q)
                    );
                },

                /* Clase CSS por template — sincronizada con las 8 del controller */
                tplClass(tpl) {
                    return {
                        'tpl-QC': tpl === 'QC',
                        'tpl-MP': tpl === 'MP',
                        'tpl-SANCO': tpl === 'SANCO',
                        'tpl-RFP': tpl === 'RFP',
                        'tpl-VT': tpl === 'VT',
                        'tpl-LIQ': tpl === 'LIQ_COMPUAGRO',
                        'tpl-GRR': tpl === 'GUIA_RECEPCION_RESUMEN',
                        'tpl-XML': tpl === 'XML_SII_46',
                        'tpl-unknown': !['QC', 'MP', 'SANCO', 'RFP', 'VT',
                            'LIQ_COMPUAGRO', 'GUIA_RECEPCION_RESUMEN',
                            'XML_SII_46'].includes(tpl),
                    };
                },
            };
        }

        /* ── header — mismo store ── */
        function pdfHeader() {
            return {
                get q() { return Alpine.store('pdf').q; },
                set q(val) { Alpine.store('pdf').q = val; },
            };
        }
    </script>

</x-app-layout>