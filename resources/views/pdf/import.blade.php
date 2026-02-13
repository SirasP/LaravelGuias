<x-app-layout>
    {{-- ═══════════════════════════════════════════════════
    HEADER
    ═══════════════════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-2.5 min-w-0">
                <a href="{{ route('pdf.index') }}" class="hidden sm:inline-flex items-center gap-1 text-xs text-gray-400 hover:text-gray-700
                      dark:hover:text-gray-200 transition shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    DTE / Facturas
                </a>
                <span class="hidden sm:block text-gray-200 dark:text-gray-700 text-sm">/</span>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Importar documentos</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">PDF · Excel · XML SII</p>
                </div>
            </div>

            <div class="hidden md:flex items-center gap-1.5 flex-wrap">
            @foreach([
                    'QC' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                    'MP' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                    'SANCO' => 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
                    'RFP' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
                    'VT' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                    'LIQ' => 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
                    'GRR' => 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
                    'XML' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                ] as $t => $cls)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $cls }}">{{ $t }}</span>
            @endforeach
        </div>
    </div>
</x-slot>

<style>
    /* ── Prevent FOUC ── */
    [x-cloak] { display: none !important; }

    @keyframes fadeUp { from { opacity:0; transform:translateY(10px) } to { opacity:1; transform:translateY(0) } }
    .au { animation: fadeUp .4s cubic-bezier(.22,1,.36,1) both }
    .d1 { animation-delay:.04s } .d2 { animation-delay:.10s } .d3 { animation-delay:.16s }

    /* ── Page ── */
    .page-bg      { background:#f1f5f9; min-height:100% }
    .dark .page-bg { background:#0d1117 }

    /* ── Panel — flex col so submit sticks to bottom ── */
    .panel       { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden;
                   display:flex; flex-direction:column }
    .dark .panel { background:#161c2c; border-color:#1e2a3b }
    .panel-head  { padding:16px 20px; border-bottom:1px solid #f1f5f9;
                   display:flex; align-items:center; gap:10px; flex-shrink:0 }
    .dark .panel-head { border-bottom-color:#1e2a3b }
    /* panel-body fills remaining height and stacks children vertically */
    .panel-body  { padding:20px; flex:1; display:flex; flex-direction:column; gap:14px }

    /* ── Icon dot ── */
    .icon-dot { width:38px; height:38px; border-radius:10px;
                display:flex; align-items:center; justify-content:center; flex-shrink:0 }

    /* ── Dropzone — each panel uses a colour modifier ── */
    .dz { border:2px dashed #e2e8f0; border-radius:14px; padding:28px 20px;
          transition:border-color .2s, background .2s; cursor:pointer; text-align:center }
    .dark .dz { border-color:#1e2a3b }
    .dz-indigo:hover, .dz-indigo.is-drag  { border-color:#6366f1; background:#eef2ff }
    .dz-emerald:hover,.dz-emerald.is-drag { border-color:#10b981; background:#ecfdf5 }
    .dz-violet:hover, .dz-violet.is-drag  { border-color:#7c3aed; background:#f5f3ff }
    .dark .dz-indigo:hover, .dark .dz-indigo.is-drag  { background:rgba(99,102,241,.08) }
    .dark .dz-emerald:hover,.dark .dz-emerald.is-drag { background:rgba(16,185,129,.08) }
    .dark .dz-violet:hover, .dark .dz-violet.is-drag  { background:rgba(124,58,237,.08) }

    /* ── File list ── */
    .flist      { border:1px solid #f1f5f9; border-radius:12px; overflow:hidden }
    .dark .flist { border-color:#1e2a3b }
    .flist-head { display:flex; align-items:center; justify-content:space-between;
                  padding:8px 12px; background:#f8fafc; border-bottom:1px solid #f1f5f9 }
    .dark .flist-head { background:rgba(255,255,255,.03); border-bottom-color:#1e2a3b }
    .fitem       { display:flex; align-items:center; justify-content:space-between;
                   padding:9px 12px; border-bottom:1px solid #f8fafc; gap:10px }
    .dark .fitem { border-bottom-color:#1a2232 }
    .fitem:last-child { border-bottom:none }

    /* ── Segment control (full-width) ── */
    .seg     { display:flex; background:#f1f5f9; border-radius:8px; padding:3px; gap:2px }
    .dark .seg { background:rgba(255,255,255,.06) }
    .seg-btn { flex:1; padding:5px 0; border-radius:6px; font-size:12px; font-weight:700;
               cursor:pointer; transition:background .15s, color .15s; text-align:center;
               border:none; background:transparent; color:#64748b }
    .seg-btn.active      { background:#fff; color:#1e293b; box-shadow:0 1px 3px rgba(0,0,0,.12) }
    .dark .seg-btn.active { background:#1e293b; color:#f1f5f9 }

    /* ── Column chips ── */
    .col-chip { display:inline-flex; font-size:10px; font-weight:700;
                padding:2px 7px; border-radius:5px; margin:2px }

    /* ── Buttons ── */
    .btn-primary { display:inline-flex; align-items:center; justify-content:center; gap:6px;
                   padding:9px 18px; border-radius:10px; font-size:13px; font-weight:700;
                   width:100%; border:none; cursor:pointer;
                   transition:background .15s, transform .1s }
    .btn-primary:active:not(:disabled) { transform:scale(.97) }
    .btn-primary:disabled { opacity:.4; cursor:not-allowed }
    .btn-indigo  { background:#4f46e5; color:#fff } .btn-indigo:hover:not(:disabled)  { background:#4338ca }
    .btn-emerald { background:#059669; color:#fff } .btn-emerald:hover:not(:disabled) { background:#047857 }
    .btn-violet  { background:#7c3aed; color:#fff } .btn-violet:hover:not(:disabled)  { background:#6d28d9 }

    .btn-ghost { display:inline-flex; align-items:center; gap:4px; padding:5px 10px;
                 border-radius:7px; font-size:11px; font-weight:600; cursor:pointer;
                 border:none; background:#f1f5f9; color:#64748b; transition:background .15s }
    .btn-ghost:hover:not(:disabled) { background:#e2e8f0 }
    .btn-ghost:disabled { opacity:.4; cursor:not-allowed }
    .dark .btn-ghost { background:rgba(255,255,255,.06); color:#94a3b8 }
    .dark .btn-ghost:hover:not(:disabled) { background:rgba(255,255,255,.1) }

    /* ── Progress bar ── */
    .pbar      { height:4px; background:#e2e8f0; border-radius:99px; overflow:hidden; margin-top:10px }
    .dark .pbar { background:#1e2a3b }
    .pbar-fill { height:100%; border-radius:99px; transition:width .3s;
                 animation:pulse-bar 1.4s ease-in-out infinite }
    @keyframes pulse-bar { 0%,100% { opacity:1 } 50% { opacity:.55 } }

    /* ── Hint text ── */
    .hint { font-size:11px; color:#94a3b8; line-height:1.6 }
    .hint strong { color:#64748b; font-weight:600 }
    .dark .hint strong { color:#94a3b8 }

    /* ── Flash ── */
    .flash-ok  { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;
                 padding:12px 16px; font-size:13px; color:#15803d;
                 display:flex; align-items:center; gap:8px }
    .flash-err { background:#fef2f2; border:1px solid #fecaca; border-radius:12px;
                 padding:12px 16px; font-size:13px; color:#dc2626 }
    .dark .flash-ok  { background:rgba(16,185,129,.1); border-color:rgba(16,185,129,.25); color:#34d399 }
    .dark .flash-err { background:rgba(239,68,68,.1);  border-color:rgba(239,68,68,.2);  color:#f87171 }

    /* ── Section label ── */
    .s-label { font-size:10px; font-weight:700; letter-spacing:.09em; text-transform:uppercase;
               color:#94a3b8; margin-bottom:6px; display:block }
</style>

<div class="page-bg">
<div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-7 space-y-5">

    {{-- Flash --}}
    @if(session('ok'))
        <div class="flash-ok au d1">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('ok') }}
        </div>
    @endif
    @if($errors->any())
        <div class="flash-err au d1">
            <p class="font-bold mb-1">Errores:</p>
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ═══ GRID 3 paneles ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 lg:items-stretch">

        {{-- ─── 1 · PDF ─────────────────────────────── --}}
        <div class="panel au d1" x-data="pdfUploader()">
            <div class="panel-head">
                <div class="icon-dot bg-indigo-50 dark:bg-indigo-900/30">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Importar PDF</p>
                    <p class="text-xs text-gray-400">QC · MP · SANCO · LIQ · GRR</p>
                </div>
            </div>

            <form method="POST" action="{{ route('pdf.import') }}" enctype="multipart/form-data"
                  class="panel-body" @submit="onSubmit">
                @csrf

                {{-- Dropzone --}}
                <div class="dz dz-indigo" :class="{ 'is-drag': dragging }"
                     @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="onDrop"
                     @click.self="$refs.file.click()">
                    <input x-ref="file" type="file" name="pdfs[]"
                           accept="application/pdf" multiple required
                           class="hidden" @change="onPick">
                    <div x-show="!submitting" class="space-y-2 pointer-events-none">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center mx-auto">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Arrastra tus PDFs aquí</p>
                        <p class="text-xs text-gray-400">o haz clic · máx 10 MB c/u</p>
                    </div>
                    <div x-show="submitting" x-cloak class="space-y-1 pointer-events-none">
                        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                            Subiendo <span x-text="files.length"></span> archivo(s)…
                        </p>
                        <div class="pbar w-48 mx-auto">
                            <div class="pbar-fill" style="width:100%;background:#4f46e5"></div>
                        </div>
                    </div>
                </div>

                {{-- File list --}}
                <div x-show="files.length > 0" x-cloak class="flist">
                    <div class="flist-head">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                            <span x-text="files.length"></span> archivo(s)
                        </span>
                        <button type="button" class="btn-ghost" @click="clearAll" :disabled="submitting">Limpiar todo</button>
                    </div>
                    <template x-for="(f, idx) in files" :key="f._key">
                        <div class="fitem">
                            <div class="min-w-0">
                                <p class="text-[13px] font-medium text-gray-800 dark:text-gray-200 truncate" x-text="f.name"></p>
                                <p class="text-[11px] text-gray-400" x-text="fmt(f.size)"></p>
                            </div>
                            <button type="button" class="btn-ghost shrink-0"
                                    @click.stop="removeAt(idx)" :disabled="submitting">✕</button>
                        </div>
                    </template>
                </div>

                <p class="hint">
                    Modelo detectado automáticamente.
                    <strong>QC</strong> = Comfrut ·
                    <strong>MP</strong> = Río Futuro ·
                    <strong>SANCO</strong> = Granel
                </p>

                {{-- mt-auto empuja el botón al fondo --}}
                <button type="submit" class="btn-primary btn-indigo mt-auto"
                        :disabled="files.length === 0 || submitting">
                    <svg x-show="!submitting" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <span x-text="submitting ? 'Subiendo…' : 'Subir PDFs'"></span>
                </button>
            </form>
        </div>

        {{-- ─── 2 · Excel ───────────────────────────── --}}
        <div class="panel au d2" x-data="excelUploader()">
            <div class="panel-head">
                <div class="icon-dot bg-emerald-50 dark:bg-emerald-900/30">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 0v10m0-10a2 2 0 012 2h2a2 2 0 012-2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Importar Excel</p>
                    <p class="text-xs text-gray-400">VT (GDD) · RFP · AGRAK</p>
                </div>
            </div>

            <div class="panel-body">
                {{-- Tipo de Excel --}}
                <div>
                    <span class="s-label">Tipo de Excel</span>
                    <div class="seg">
                        <button type="button" class="seg-btn" :class="{ active: excelType === 'vt' }"
                                @click="excelType = 'vt'">VT (GDD)</button>
                        <button type="button" class="seg-btn" :class="{ active: excelType === 'rfp' }"
                                @click="excelType = 'rfp'">RFP</button>
                        <button type="button" class="seg-btn" :class="{ active: excelType === 'agrak' }"
                                @click="excelType = 'agrak'">AGRAK</button>
                    </div>
                </div>

                {{-- Columnas requeridas con chips --}}
                <div class="rounded-xl border border-gray-100 dark:border-gray-800
                            bg-gray-50 dark:bg-gray-900/30 px-3 py-2.5">
                    <template x-if="excelType === 'vt'">
                        <div>
                            <span class="s-label">Columnas · VitaFoods GDD</span>
                            <div class="flex flex-wrap -m-0.5">
                                <template x-for="c in ['GDD','Fecha Recepción','Razón Social Productor','Cantidad Recepcionada','Empresa','Desc. Sucursal']">
                                    <span class="col-chip bg-amber-50 text-amber-700 border border-amber-100
                                                 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-900/30"
                                          x-text="c"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="excelType === 'rfp'">
                        <div>
                            <span class="s-label">Columnas · RFP</span>
                            <div class="flex flex-wrap -m-0.5">
                                <template x-for="c in ['Guía Despacho','Fecha','Productor','Albarán','Bandejas','Kg Recepcionados','% IQF','% Block','Calidad']">
                                    <span class="col-chip bg-indigo-50 text-indigo-700 border border-indigo-100
                                                 dark:bg-indigo-900/20 dark:text-indigo-400 dark:border-indigo-900/30"
                                          x-text="c"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="excelType === 'agrak'">
                        <div>
                            <span class="s-label">Columnas · AGRAK</span>
                            <div class="flex flex-wrap -m-0.5">
                                <template x-for="c in ['Código bin','Fecha registro','Hora registro','Exportadora','Nº sello','Especie','Variedad','Cuartel']">
                                    <span class="col-chip bg-emerald-50 text-emerald-700 border border-emerald-100
                                                 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-900/30"
                                          x-text="c"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Form con :action dinámico --}}
                <form :action="formAction" method="POST" enctype="multipart/form-data"
                      class="contents" @submit="onSubmit">
                    @csrf

                    {{-- Dropzone --}}
                    <div class="dz dz-emerald" :class="{ 'is-drag': dragging }"
                         @dragover.prevent="dragging = true"
                         @dragleave.prevent="dragging = false"
                         @drop.prevent="onDrop"
                         @click.self="$refs.file.click()">
                        <input x-ref="file" type="file" name="excel"
                               accept=".xlsx,.xls" required class="hidden" @change="onPick">
                        <div x-show="!submitting" class="space-y-2 pointer-events-none">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center mx-auto">
                                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Arrastra tu Excel aquí</p>
                            <p class="text-xs text-gray-400">o haz clic · .xlsx / .xls · 1 archivo</p>
                        </div>
                        <div x-show="submitting" x-cloak class="space-y-1 pointer-events-none">
                            <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Procesando Excel…</p>
                            <div class="pbar w-48 mx-auto">
                                <div class="pbar-fill" style="width:100%;background:#10b981"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Archivo seleccionado --}}
                    <div x-show="file" x-cloak class="flist">
                        <div class="fitem">
                            <div class="min-w-0">
                                <p class="text-[13px] font-medium text-gray-800 dark:text-gray-200 truncate" x-text="file?.name"></p>
                                <p class="text-[11px] text-gray-400" x-text="fmt(file?.size ?? 0)"></p>
                            </div>
                            <button type="button" class="btn-ghost shrink-0"
                                    @click.stop="clear" :disabled="submitting">✕</button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary btn-emerald mt-auto"
                            :disabled="!file || submitting">
                        <svg x-show="!submitting" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <span x-text="submitting ? 'Importando…' : 'Importar ' + excelType.toUpperCase()"></span>
                    </button>
                </form>
            </div>
        </div>

        {{-- ─── 3 · XML SII ─────────────────────────── --}}
        <div class="panel au d3" x-data="xmlUploader()">
            <div class="panel-head">
                <div class="icon-dot bg-violet-50 dark:bg-violet-900/30">
                    <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Importar XML</p>
                    <p class="text-xs text-gray-400">SII Tipo 46 · varios a la vez</p>
                </div>
            </div>

            <form method="POST" action="{{ route('pdf.import.xml') }}" enctype="multipart/form-data"
                  class="panel-body" @submit="onSubmit">
                @csrf

                {{-- Dropzone --}}
                <div class="dz dz-violet" :class="{ 'is-drag': dragging }"
                     @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="onDrop"
                     @click.self="$refs.file.click()">
                    <input x-ref="file" type="file" name="xmls[]"
                           accept=".xml" multiple required class="hidden" @change="onPick">
                    <div x-show="!submitting" class="space-y-2 pointer-events-none">
                        <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center mx-auto">
                            <svg class="w-5 h-5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Arrastra tus XML aquí</p>
                        <p class="text-xs text-gray-400">Puedes subir varios a la vez · .xml</p>
                    </div>
                    <div x-show="submitting" x-cloak class="space-y-1 pointer-events-none">
                        <p class="text-sm font-semibold text-violet-600 dark:text-violet-400">
                            Importando <span x-text="files.length"></span> XML…
                        </p>
                        <div class="pbar w-48 mx-auto">
                            <div class="pbar-fill" style="width:100%;background:#7c3aed"></div>
                        </div>
                    </div>
                </div>

                {{-- File list --}}
                <div x-show="files.length > 0" x-cloak class="flist">
                    <div class="flist-head">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                            <span x-text="files.length"></span> archivo(s) · <span x-text="totalSize"></span>
                        </span>
                        <button type="button" class="btn-ghost" @click="clearAll" :disabled="submitting">Limpiar todo</button>
                    </div>
                    <div class="max-h-44 overflow-y-auto">
                        <template x-for="(f, idx) in files" :key="f._key">
                            <div class="fitem">
                                <div class="min-w-0 flex items-center gap-2">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded shrink-0
                                                 bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">XML</span>
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-medium text-gray-800 dark:text-gray-200 truncate" x-text="f.name"></p>
                                        <p class="text-[11px] text-gray-400" x-text="fmt(f.size)"></p>
                                    </div>
                                </div>
                                <button type="button" class="btn-ghost shrink-0"
                                        @click.stop="removeAt(idx)" :disabled="submitting">✕</button>
                            </div>
                        </template>
                    </div>
                </div>

                <p class="hint">
                    Duplicados se ignoran automáticamente.
                    Detecta <strong>Tipo 46</strong> del SII.
                </p>

                <button type="submit" class="btn-primary btn-violet mt-auto"
                        :disabled="files.length === 0 || submitting">
                    <svg x-show="!submitting" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <span x-text="submitting ? 'Importando…' : 'Importar XML (' + files.length + ')'"></span>
                </button>
            </form>
        </div>

    </div>{{-- /grid --}}

    {{-- ═══ GUÍA DE MODELOS ═══ --}}
    <div class="panel au d3">
        <div class="panel-head">
            <div class="icon-dot bg-gray-100 dark:bg-gray-800">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-widest">
                Modelos detectados automáticamente
            </p>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                @foreach([
                                ['QC', 'Control de Calidad', 'Comfrut', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'PDF'],
                                ['MP', 'Reporte MP', 'Río Futuro', 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'PDF'],
                                ['SANCO', 'Guía Rec. Granel', 'Sanco', 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400', 'PDF'],
                                ['RFP', 'Recepción Fruta Prod.', 'RFP', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400', 'Excel'],
                                ['VT', 'VitaFoods GDD', 'Excel', 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'Excel'],
                                ['LIQ', 'Liq. Productores', 'Compuagro', 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400', 'PDF'],
                                ['GRR', 'Guía Rec. Resumen', 'Interno', 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400', 'PDF'],
                                ['XML', 'Guía Despacho SII', 'SII Tipo 46', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400', 'XML'],
                            ] as [$key, $name, $source, $cls, $format])
                            <div class="flex items-start gap-2 p-3 rounded-xl
                                        bg-gray-50 dark:bg-gray-900/30
                                        border border-gray-100 dark:border-gray-800">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md {{ $cls }} shrink-0 mt-0.5">{{ $key }}</span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-700 dark:text-gray-300 leading-snug">{{ $name }}</p>
                                    <p class="text-gray-400 mt-0.5 truncate">{{ $source }}</p>
                                    <span class="inline-block mt-1.5 text-[10px] font-bold px-1.5 py-0.5 rounded
                                                 {{ $format === 'PDF' ? 'bg-indigo-50 text-indigo-500 dark:bg-indigo-900/20 dark:text-indigo-400'
                    : ($format === 'XML' ? 'bg-violet-50 text-violet-500 dark:bg-violet-900/20 dark:text-violet-400'
                        : 'bg-emerald-50 text-emerald-500 dark:bg-emerald-900/20 dark:text-emerald-400') }}">
                                        {{ $format }}
                                    </span>
                                </div>
                            </div>
                @endforeach
            </div>
        </div>
    </div>

</div>
</div>

{{-- ═══ SCRIPTS — un solo bloque ═══ --}}
<script>
/* ── Utilidades compartidas ── */
function fmt(bytes) {
    if (!bytes) return '0 B';
    const u = ['B','KB','MB','GB'];
    let i = 0, n = bytes;
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return `${n.toFixed(i === 0 ? 0 : 1)} ${u[i]}`;
}
function fileKey(f) { return `${f.name}__${f.size}__${f.lastModified}`; }
function mergeFiles(existing, incoming) {
    const seen = new Set(existing.map(fileKey));
    for (const f of incoming) {
        if (!seen.has(fileKey(f))) { f._key = fileKey(f); existing.push(f); seen.add(fileKey(f)); }
    }
    return existing;
}
function syncInput(ref, files) {
    const dt = new DataTransfer();
    files.forEach(f => dt.items.add(f));
    ref.files = dt.files;
}

/* ── 1 · PDF uploader ── */
function pdfUploader() {
    return {
        dragging: false, submitting: false, files: [],
        onPick(e) { this.files = mergeFiles(this.files, Array.from(e.target.files||[])); syncInput(this.$refs.file, this.files); },
        onDrop(e) {
            this.dragging = false;
            const f = Array.from(e.dataTransfer.files||[]).filter(f => f.type==='application/pdf' || f.name.toLowerCase().endsWith('.pdf'));
            this.files = mergeFiles(this.files, f); syncInput(this.$refs.file, this.files);
        },
        removeAt(i) { this.files.splice(i,1); syncInput(this.$refs.file, this.files); },
        clearAll()  { this.files = []; syncInput(this.$refs.file, this.files); },
        onSubmit(e) { if (this.submitting || !this.files.length) { e.preventDefault(); return; } this.submitting = true; },
        fmt,
    };
}

/* ── 2 · Excel uploader ── */
function excelUploader() {
    return {
        dragging: false, submitting: false, file: null, excelType: 'vt',
        get formAction() {
            return ({ vt:"{{ route('excel.import.qc') }}", rfp:"{{ route('excel.import.rfp') }}", agrak:"{{ route('agrak.import') }}" })[this.excelType]
                ?? "{{ route('excel.import.qc') }}";
        },
        onPick(e) { this.file = e.target.files[0] || null; },
        onDrop(e) {
            this.dragging = false;
            const f = Array.from(e.dataTransfer.files||[]).filter(f => /\.(xlsx|xls)$/i.test(f.name));
            if (!f.length) return;
            this.file = f[0]; const dt = new DataTransfer(); dt.items.add(this.file); this.$refs.file.files = dt.files;
        },
        clear()     { this.file = null; this.$refs.file.value = ''; },
        onSubmit(e) { if (this.submitting || !this.file) { e.preventDefault(); return; } this.submitting = true; },
        fmt,
    };
}

/* ── 3 · XML uploader ── */
function xmlUploader() {
    return {
        dragging: false, submitting: false, files: [],
        get totalSize() { return fmt(this.files.reduce((s,f) => s+(f.size||0), 0)); },
        onPick(e) { this.files = mergeFiles(this.files, Array.from(e.target.files||[])); syncInput(this.$refs.file, this.files); },
        onDrop(e) {
            this.dragging = false;
            const f = Array.from(e.dataTransfer.files||[]).filter(f => f.name.toLowerCase().endsWith('.xml'));
            this.files = mergeFiles(this.files, f); syncInput(this.$refs.file, this.files);
        },
        removeAt(i) { this.files.splice(i,1); syncInput(this.$refs.file, this.files); },
        clearAll()  { this.files = []; syncInput(this.$refs.file, this.files); },
        onSubmit(e) { if (this.submitting || !this.files.length) { e.preventDefault(); return; } this.submitting = true; },
        fmt,
    };
}
</script>

</x-app-layout>