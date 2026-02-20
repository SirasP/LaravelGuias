<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 min-w-0 text-xs">
                <a href="{{ route('purchase_orders.index') }}"
                    class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium truncate">
                    Cotizaciones
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300">Nueva cotización</span>
            </div>
        </div>
    </x-slot>

    <style>
        [x-cloak] { display:none !important; }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(8px) }
            to   { opacity:1; transform:translateY(0) }
        }
        .au { animation:fadeUp .35s ease both }
        .d1 { animation-delay:.06s }
        .d2 { animation-delay:.12s }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .f-label {
            display:block; font-size:11px; font-weight:700;
            text-transform:uppercase; letter-spacing:.05em;
            color:#64748b; margin-bottom:5px;
        }
        .dark .f-label { color:#94a3b8 }
        .f-input {
            width:100%; border-radius:10px; border:1px solid #e2e8f0; background:#fff;
            padding:8px 11px; font-size:13px; color:#111827; outline:none;
            transition:border-color .15s, box-shadow .15s;
        }
        .f-input:focus { border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
        .dark .f-input:focus { border-color:#10b981 }

        .f-cell {
            width:100%; border-radius:8px; border:1px solid #e2e8f0; background:#fff;
            padding:6px 9px; font-size:12px; color:#111827; outline:none;
            transition:border-color .15s;
        }
        .f-cell:focus { border-color:#10b981; box-shadow:0 0 0 2px rgba(16,185,129,.1) }
        .dark .f-cell { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }

        .dt { width:100%; border-collapse:collapse; font-size:13px; min-width:960px }
        .dt thead tr { background:#f8fafc }
        .dark .dt thead tr { background:#0f1623 }
        .dt th {
            padding:9px 12px; text-align:left; font-size:10px; font-weight:700;
            letter-spacing:.07em; text-transform:uppercase; color:#94a3b8; white-space:nowrap;
            box-shadow:inset 0 -2px 0 #e2e8f0;
        }
        .dark .dt th { box-shadow:inset 0 -2px 0 #1e2a3b }
        .dt td { padding:8px 10px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:hover td { background:#f5fffb }
        .amt-cell {
            background:rgba(16,185,129,.04); border-left:1px solid #ecfdf5;
            font-weight:700; font-size:13px; color:#065f46;
            text-align:right; padding-right:14px; white-space:nowrap;
        }
        .dark .amt-cell { background:rgba(16,185,129,.03); border-left-color:rgba(16,185,129,.08); color:#34d399 }
        .line-num {
            display:inline-flex; width:20px; height:20px; align-items:center; justify-content:center;
            border-radius:999px; background:#f1f5f9; color:#94a3b8; font-size:10px; font-weight:700;
        }

        /* ── Combobox ──────────────────────────────────────────────────────── */
        .combo-wrap { position:relative; }

        /* Tag-input: contenedor que muestra chips + campo de búsqueda inline  */
        .combo-tags-wrap {
            display:flex; flex-wrap:wrap; gap:5px; align-items:center;
            padding:4px 8px; border:1px solid #e2e8f0; border-radius:10px;
            background:#fff; min-height:38px; cursor:text;
            transition:border-color .15s, box-shadow .15s;
        }
        .combo-tags-wrap:focus-within {
            border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.12);
        }
        .dark .combo-tags-wrap { border-color:#1e2a3b; background:#0d1117; }
        .dark .combo-tags-wrap:focus-within { border-color:#10b981; }

        /* Chip individual de proveedor seleccionado */
        .sp-tag {
            display:inline-flex; align-items:center; gap:3px;
            padding:2px 4px 2px 8px; border-radius:999px;
            background:#ecfdf5; border:1.5px solid #a7f3d0;
            font-size:12px; font-weight:700; color:#065f46; white-space:nowrap;
        }
        .dark .sp-tag { background:rgba(16,185,129,.1); border-color:rgba(16,185,129,.3); color:#34d399; }

        /* Chip sin correo — naranja para llamar la atención */
        .sp-tag-warn {
            background:#fefce8; border-color:#fde047; color:#854d0e;
        }
        .dark .sp-tag-warn { background:rgba(234,179,8,.08); border-color:rgba(234,179,8,.4); color:#fbbf24; }

        /* Nombre del chip: botón clicable que abre el modal de edición */
        .sp-tag-name {
            background:none; border:none; padding:0; margin:0;
            font:inherit; color:inherit; font-weight:700; cursor:pointer;
            text-decoration:underline; text-underline-offset:2px;
            text-decoration-color:transparent; transition:.1s;
        }
        .sp-tag-name:hover { text-decoration-color:currentColor; }

        .sp-tag-x {
            display:inline-flex; align-items:center; justify-content:center;
            width:15px; height:15px; border-radius:999px; cursor:pointer;
            font-size:14px; line-height:1; color:#047857; background:none;
            border:none; padding:0; transition:.1s;
        }
        .sp-tag-x:hover { background:rgba(239,68,68,.15); color:#dc2626; }
        .dark .sp-tag-x { color:#6ee7b7; }

        /* Input desnudo dentro del tag-wrap */
        .tag-bare-input {
            flex:1; min-width:100px; border:none; outline:none;
            background:transparent; font-size:13px; color:#111827; padding:2px 4px;
        }
        .dark .tag-bare-input { color:#f1f5f9; }
        .tag-bare-input::placeholder { color:#9ca3af; }

        .combo-drop {
            position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:20;
            background:#fff; border:1px solid #e2e8f0; border-radius:12px;
            box-shadow:0 8px 32px rgba(0,0,0,.12); max-height:280px; overflow-y:auto;
        }
        .dark .combo-drop { background:#161c2c; border-color:#1e2a3b; box-shadow:0 8px 32px rgba(0,0,0,.4) }
        .combo-item {
            display:flex; align-items:center; justify-content:space-between;
            padding:9px 13px; cursor:pointer; font-size:13px; color:#334155;
            gap:8px;
        }
        .combo-item:hover { background:#f5fffb }
        .dark .combo-item { color:#cbd5e1 }
        .dark .combo-item:hover { background:rgba(16,185,129,.05) }
        .combo-item-sub { font-size:11px; color:#94a3b8; white-space:nowrap }
        .combo-empty  { padding:9px 13px; font-size:12px; color:#94a3b8; font-style:italic }
        .combo-create {
            display:flex; align-items:center; gap:7px;
            padding:10px 13px; cursor:pointer; font-size:12px; font-weight:700;
            color:#10b981; border-top:1px solid #f1f5f9;
        }
        .combo-create:hover { background:#f0fdf4 }
        .dark .combo-create { color:#34d399; border-top-color:#1a2232 }
        .dark .combo-create:hover { background:rgba(16,185,129,.08) }

        /* ── Tarjetas de destinatario ──────────────────────────────────────── */
        .dest-card {
            display:flex; flex-direction:column; gap:3px;
            padding:8px 11px; border-radius:12px;
            background:#ecfdf5; border:1.5px solid #a7f3d0;
            min-width:180px; max-width:260px;
        }
        .dark .dest-card { background:rgba(16,185,129,.07); border-color:rgba(16,185,129,.2) }
        .dest-card-head {
            display:flex; align-items:center; justify-content:space-between; gap:6px;
        }
        .dest-name {
            font-size:10px; font-weight:800; text-transform:uppercase;
            letter-spacing:.05em; color:#065f46; truncate:true;
        }
        .dark .dest-name { color:#34d399 }
        .dest-email { font-size:12px; font-weight:600; color:#047857 }
        .dark .dest-email { color:#6ee7b7 }
        .dest-no-email { font-size:11px; color:#94a3b8 }

        /* Input inline para escribir el correo directamente en la tarjeta */
        .dest-inline-email {
            width:100%; background:transparent; border:none;
            border-bottom:1.5px dashed #6ee7b7; outline:none;
            font-size:12px; font-style:italic; color:#047857;
            padding:1px 0; transition:border-color .15s;
        }
        .dest-inline-email:focus { border-bottom-color:#10b981; font-style:normal; }
        .dest-inline-email::placeholder { color:#94a3b8; }
        .dark .dest-inline-email { border-bottom-color:rgba(16,185,129,.3); color:#6ee7b7; }
        .dest-actions { display:flex; gap:3px; shrink:0 }
        .dest-btn {
            display:inline-flex; align-items:center; justify-content:center;
            width:18px; height:18px; border-radius:6px; cursor:pointer;
            background:rgba(16,185,129,.15); color:#065f46; font-size:12px;
            transition:.12s;
        }
        .dest-btn:hover { background:rgba(239,68,68,.15); color:#dc2626 }
        .dark .dest-btn { background:rgba(16,185,129,.15); color:#34d399 }
        .dest-btn-edit { background:rgba(99,102,241,.1); color:#4338ca }
        .dest-btn-edit:hover { background:rgba(99,102,241,.2); color:#4338ca }
        .dark .dest-btn-edit { color:#818cf8 }

        /* ── Combobox de producto por línea ──────────────────────────────── */
        .prod-wrap { position:relative; }
        .prod-input {
            width:100%; background:transparent; border:none; outline:none;
            font-size:13px; color:#111827; padding:4px 6px;
            border-bottom:1.5px solid #e2e8f0; transition:border-color .15s;
        }
        .prod-input:focus { border-bottom-color:#10b981; }
        .dark .prod-input { color:#f1f5f9; border-bottom-color:#374151; }
        .dark .prod-input:focus { border-bottom-color:#10b981; }
        .prod-input::placeholder { color:#9ca3af; }
        .prod-drop {
            position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:50;
            background:#fff; border:1px solid #e2e8f0; border-radius:10px;
            box-shadow:0 8px 24px rgba(0,0,0,.10); max-height:220px; overflow-y:auto;
        }
        .dark .prod-drop { background:#161c2c; border-color:#1e2a3b; }
        .prod-item {
            padding:8px 12px; cursor:pointer; font-size:12px; color:#334155;
            display:flex; align-items:center; justify-content:space-between; gap:6px;
        }
        .prod-item:hover { background:#f0fdf4; }
        .dark .prod-item { color:#cbd5e1; }
        .dark .prod-item:hover { background:rgba(16,185,129,.06); }
        .prod-item-sub { font-size:10px; color:#94a3b8; white-space:nowrap; }
        .prod-item-empty { padding:8px 12px; font-size:11px; color:#94a3b8; font-style:italic; }
    </style>

    <div class="page-bg" x-data="purchaseOrderForm(@js($products), @js($suppliers), @js($defaultNotesTemplate))">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-4">

            @if($errors->any())
                <div class="px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700
                        dark:bg-rose-900/20 dark:border-rose-800 dark:text-rose-400 text-sm au d1">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('purchase_orders.store') }}" x-ref="mainForm" @submit="beforeSubmit()">
                @csrf

                {{-- Hidden: supplier_id = primer destinatario agregado --}}
                <input type="hidden" name="supplier_id" :value="primarySupplierId()">

                {{-- ── Panel 1: Proveedores y destinatarios ────────────────────── --}}
                {{-- overflow:visible permite que el combo-drop se extienda fuera --}}
                <div class="panel au d1" style="overflow:visible">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Proveedores y destinatarios</h3>
                    </div>

                    <div class="px-5 py-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            {{-- ── Combobox tag-input: chips de proveedores + búsqueda inline ── --}}
                            <div>
                                <label class="f-label">Proveedores</label>
                                <div class="combo-wrap">

                                    {{-- Tag-wrap: chips de proveedores seleccionados + input de búsqueda --}}
                                    <div class="combo-tags-wrap" @mousedown="$event.target === $el && $refs.tagInput.focus()">

                                        {{-- Un chip por cada proveedor único en la lista --}}
                                        <template x-for="r in uniqueSelectedSuppliers()" :key="r.supplierId">
                                            <span class="sp-tag" :class="!r.hasEmail && 'sp-tag-warn'">
                                                {{-- Nombre clicable → abre modal de edición --}}
                                                <button type="button" class="sp-tag-name"
                                                    @mousedown.prevent="openSupplierModal(r.supplierId)"
                                                    :title="r.hasEmail ? 'Editar proveedor' : '⚠ Sin correo — clic para agregar'"
                                                    x-text="r.name"></button>
                                                <button type="button" class="sp-tag-x"
                                                    @mousedown.prevent="removeSupplierFromRecipients(r.supplierId)"
                                                    title="Quitar proveedor">&times;</button>
                                            </span>
                                        </template>

                                        {{-- Input de búsqueda inline --}}
                                        <input type="text"
                                            x-ref="tagInput"
                                            class="tag-bare-input"
                                            x-model="supplierSearch"
                                            @focus="supplierDropOpen=true"
                                            @click="supplierDropOpen=true"
                                            @input="supplierDropOpen=true"
                                            @blur="setTimeout(() => { supplierDropOpen=false }, 200)"
                                            @keydown.escape="supplierDropOpen=false"
                                            @keydown.enter.prevent="comboEnter()"
                                            :placeholder="uniqueSelectedSuppliers().length ? 'Agregar otro...' : 'Escribe para buscar...'"
                                            autocomplete="off">
                                    </div>

                                    {{-- Items usan @mousedown.prevent: previene que el input pierda foco --}}
                                    <div x-show="supplierDropOpen" x-cloak class="combo-drop">
                                        <template x-for="sp in filteredSuppliers()" :key="sp.id">
                                            <div class="combo-item" @mousedown.prevent="addSupplierToRecipients(sp)">
                                                <span class="font-semibold truncate" x-text="sp.name"></span>
                                                <span class="combo-item-sub" x-text="sp.rut || ''"></span>
                                            </div>
                                        </template>
                                        <div class="combo-empty"
                                            x-show="filteredSuppliers().length === 0 && (supplierSearch || '').trim()">
                                            Sin resultados — crea uno nuevo.
                                        </div>
                                        <div class="combo-create" @mousedown.prevent="openSupplierModal(null)">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Crear nuevo proveedor
                                        </div>
                                    </div>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">
                                    Puedes agregar múltiples proveedores como destinatarios.
                                </p>
                            </div>

                            {{-- ── Moneda ── --}}
                            <div>
                                <label class="f-label">Moneda</label>
                                <select name="currency" class="f-input" x-model="currency">
                                    @foreach(['CLP', 'USD', 'EUR'] as $cur)
                                        <option value="{{ $cur }}">{{ $cur }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- ── Destinatarios del envío ── --}}
                            <div class="md:col-span-2">
                                <label class="f-label">Destinatarios del envío</label>

                                {{-- Tarjetas de destinatarios --}}
                                <div x-show="recipientList.length" class="flex flex-wrap gap-2">
                                    <template x-for="(r, idx) in recipientList" :key="r.supplierId + '-' + r.email + '-' + idx">
                                        {{-- Un solo elemento raíz (Alpine v3 requiere exactamente uno en x-for) --}}
                                        <div class="dest-card">
                                            {{-- Hidden input DENTRO de la card para respetar el único root --}}
                                            <template x-if="r.email">
                                                <input type="hidden" name="recipient_emails[]" :value="r.email">
                                            </template>

                                            <div class="dest-card-head">
                                                <span class="dest-name truncate" x-text="r.name"></span>
                                                <div class="dest-actions">
                                                    {{-- Editar proveedor --}}
                                                    <button type="button"
                                                        class="dest-btn dest-btn-edit"
                                                        @click="openSupplierModal(r.supplierId)"
                                                        title="Editar proveedor">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 013.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                        </svg>
                                                    </button>
                                                    {{-- Quitar --}}
                                                    <button type="button"
                                                        class="dest-btn"
                                                        @click="removeRecipient(idx)"
                                                        title="Quitar destinatario">
                                                        &times;
                                                    </button>
                                                </div>
                                            </div>
                                            <span class="dest-email" x-show="r.email" x-text="r.email"></span>
                                            <div class="dest-no-email" x-show="!r.email">
                                                <input
                                                    type="email"
                                                    class="dest-inline-email"
                                                    placeholder="Sin correo — escribe aquí..."
                                                    @blur="setRecipientEmail(idx, $event.target.value)"
                                                    @keydown.enter.prevent="setRecipientEmail(idx, $event.target.value); $event.target.blur()">
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Estado vacío --}}
                                <p class="text-xs text-gray-400 mt-1" x-show="!recipientList.length">
                                    Busca y selecciona proveedores arriba para agregarlos como destinatarios.
                                </p>
                            </div>
                        </div>

                        {{-- Nota: las observaciones se editan en el modal de confirmación --}}
                    </div>
                </div>

                {{-- ── Panel 2: Líneas ──────────────────────────────────────── --}}
                <div class="panel au d2 mt-4">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Líneas de productos</h3>
                        <button type="button" @click="addLine()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl
                                   bg-emerald-600 hover:bg-emerald-700 text-white transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Agregar línea
                        </button>
                    </div>

                    <div style="overflow:visible">
                        <table class="dt" style="min-width:700px">
                            <thead>
                                <tr>
                                    <th class="w-10 text-center">#</th>
                                    <th class="min-w-[280px]">Producto</th>
                                    <th>UdM</th>
                                    <th class="text-right">Cantidad</th>
                                    <th class="text-right" x-text="'Precio unit. (' + currencySymbol() + ')'"></th>
                                    <th class="text-right pr-5" x-text="'Importe (' + currencySymbol() + ')'"></th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(line, i) in lines" :key="line.uid">
                                    <tr>
                                        <td class="text-center"><span class="line-num" x-text="i + 1"></span></td>
                                        {{-- Combobox de producto (reemplaza select + input nombre) --}}
                                        <td class="min-w-[280px]" style="position:relative; overflow:visible">
                                            {{-- Inputs ocultos para envío al servidor --}}
                                            <input type="hidden" :name="`items[${i}][inventory_product_id]`" :value="line.inventory_product_id">
                                            <input type="hidden" :name="`items[${i}][product_name]`" :value="line.product_name">
                                            <input type="hidden" :name="`items[${i}][save_as_inventory]`" :value="line.saveAsInventory ? '1' : ''">

                                            <div class="prod-wrap">
                                                <input type="text"
                                                    class="prod-input"
                                                    placeholder="Buscar o escribir producto..."
                                                    autocomplete="off"
                                                    x-model="line.productSearch"
                                                    @focus="line.productDropOpen = true"
                                                    @input="line.productDropOpen = true; if (!line.inventory_product_id) line.product_name = line.productSearch;"
                                                    @blur="setTimeout(() => { line.productDropOpen = false; if (!line.inventory_product_id) line.product_name = line.productSearch; }, 160)"
                                                    @keydown.escape="line.productDropOpen = false"
                                                    @keydown.enter.prevent="selectFirstProduct(line)">

                                                {{-- Dropdown de coincidencias --}}
                                                <div class="prod-drop" x-show="line.productDropOpen" x-cloak>
                                                    <template x-if="filteredProducts(line).length === 0">
                                                        <div class="prod-item-empty">Sin coincidencias — se guardará como manual</div>
                                                    </template>
                                                    <template x-for="p in filteredProducts(line)" :key="p.id">
                                                        <div class="prod-item"
                                                            @mousedown.prevent="selectProduct(line, p)">
                                                            <span x-text="p.nombre"></span>
                                                            <span class="prod-item-sub"
                                                                x-text="`${p.codigo ? p.codigo + ' · ' : ''}${p.unidad || ''}`"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>

                                            {{-- Badge: producto seleccionado del inventario --}}
                                            <div class="mt-1 flex items-center gap-1" x-show="line.inventory_product_id">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold
                                                             bg-emerald-50 text-emerald-700 border border-emerald-200
                                                             rounded-full px-2 py-0.5">
                                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Inventario
                                                </span>
                                                <button type="button"
                                                    class="text-[10px] text-gray-400 hover:text-rose-500 transition"
                                                    @click="line.inventory_product_id=''; line.productSearch=line.product_name;"
                                                    title="Desvincular del inventario">× desvincular</button>
                                            </div>
                                            {{-- Checkbox guardar en inventario (sólo si es manual) --}}
                                            <label class="mt-1 inline-flex items-center gap-1 text-[11px] text-gray-500 cursor-pointer"
                                                x-show="!line.inventory_product_id">
                                                <input type="checkbox" x-model="line.saveAsInventory"
                                                    class="rounded border-gray-300 dark:border-gray-700 w-3 h-3 text-emerald-600">
                                                <span>Guardar en inventario</span>
                                            </label>
                                        </td>
                                        <td class="min-w-[80px]">
                                            <input :name="`items[${i}][unit]`" x-model="line.unit" class="f-cell" placeholder="UN">
                                        </td>
                                        <td class="min-w-[100px]">
                                            <input type="number" step="0.0001" min="0" :name="`items[${i}][quantity]`"
                                                x-model.number="line.quantity" @input="recalc(line)" class="f-cell text-right">
                                        </td>
                                        <td class="min-w-[120px]">
                                            <input type="number" step="0.0001" min="0" :name="`items[${i}][unit_price]`"
                                                x-model.number="line.unit_price" @input="recalc(line)" class="f-cell text-right">
                                        </td>
                                        <td class="amt-cell min-w-[120px]" x-text="money(line.line_total)"></td>
                                        <td class="text-center px-2">
                                            <button type="button" @click="removeLine(i)" x-show="lines.length > 1"
                                                class="w-7 h-7 inline-flex items-center justify-center rounded-lg
                                                       bg-rose-50 hover:bg-rose-100 text-rose-500 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t-2 border-gray-100 dark:border-gray-800 px-5 py-4
                                bg-gray-50/60 dark:bg-gray-900/20 flex items-center justify-between gap-4 flex-wrap">
                        <p class="text-xs text-gray-400">
                            <span x-text="lines.length"></span> línea<span x-show="lines.length !== 1">s</span>
                        </p>
                        <div class="flex items-center gap-6">
                            <div class="text-right">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Total</p>
                                <p class="text-2xl font-black tabular-nums text-emerald-600 dark:text-emerald-400">
                                    <span x-text="currencySymbol()"></span>&nbsp;<span x-text="money(grandTotal())"></span>
                                </p>
                            </div>
                            <button type="button" @click="openPreview()"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-bold rounded-xl
                                       bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Revisar y enviar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- ── Modal Preview / Confirmación de envío ──────────────────────── --}}
        <div x-show="previewOpen" x-cloak
             class="fixed inset-0 flex items-center justify-center p-4"
             style="z-index:250">
            <div class="absolute inset-0 bg-black/60" @click="previewOpen=false"></div>
            <div class="relative w-full max-w-2xl max-h-[90vh] flex flex-col"
                 style="background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden;">

                {{-- Header --}}
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </span>
                        <h3 class="text-sm font-bold text-gray-900">Confirmar envío de cotización</h3>
                    </div>
                    <button type="button" @click="previewOpen=false"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100
                               hover:bg-gray-200 text-gray-500 text-xl leading-none transition">&times;</button>
                </div>

                <div class="overflow-y-auto flex-1 px-5 py-4 space-y-4">

                    {{-- Destinatarios --}}
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                            Destinatarios
                            <span class="ml-1 font-normal normal-case text-gray-300"
                                x-text="'(' + recipientList.filter(r => r.email).length + ' con correo)'"></span>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="r in recipientList" :key="r.supplierId + r.email">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                                     :class="r.email
                                         ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                         : 'bg-amber-50 text-amber-700 border border-amber-200'">
                                    <span x-text="r.name"></span>
                                    <span class="font-normal opacity-70" x-show="r.email" x-text="'— ' + r.email"></span>
                                    <span class="font-normal" x-show="!r.email">— sin correo (no se enviará)</span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Mensaje / observaciones --}}
                    <div>
                        <div class="flex items-start gap-2 mb-2">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">
                                    Cuerpo del correo
                                </p>
                                <p class="text-[11px] text-gray-400 mt-0.5 leading-relaxed">
                                    Este es el texto que recibirá cada proveedor. Puedes editarlo ahora.
                                    El texto <span class="font-mono bg-gray-100 text-gray-600 px-1 rounded">{PROVEEDOR}</span>
                                    se reemplaza automáticamente por el nombre de cada destinatario al momento del envío.
                                </p>
                            </div>
                        </div>
                        <textarea x-model="notes" rows="8"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-700
                                   px-3 py-2 outline-none focus:border-emerald-400 focus:bg-white transition"
                            style="resize:vertical; font-family:inherit; line-height:1.6"></textarea>
                    </div>

                    {{-- Líneas de producto (resumen) --}}
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                            Productos (<span x-text="lines.filter(l => l.product_name).length"></span>)
                        </p>
                        <div class="rounded-xl border border-gray-100 divide-y divide-gray-50 overflow-hidden">
                            <template x-for="(l, i) in lines.filter(l => l.product_name)" :key="l.uid">
                                <div class="px-3 py-2 flex items-center justify-between gap-3 text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="w-5 h-5 rounded-md bg-gray-100 text-gray-500 flex items-center justify-center font-bold text-[10px]"
                                            x-text="i + 1"></span>
                                        <span class="font-medium text-gray-800" x-text="l.product_name"></span>
                                        <span class="text-gray-400" x-text="l.unit"></span>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0 text-gray-500">
                                        <span x-text="l.quantity + ' × ' + currencySymbol() + money(l.unit_price)"></span>
                                        <span class="font-bold text-gray-700"
                                            x-text="currencySymbol() + ' ' + money(l.line_total)"></span>
                                    </div>
                                </div>
                            </template>
                            <div class="px-3 py-2 bg-emerald-50 flex justify-end">
                                <span class="text-sm font-black text-emerald-700"
                                    x-text="'Total: ' + currencySymbol() + ' ' + money(grandTotal())"></span>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Footer con acciones --}}
                <div class="px-5 py-3.5 border-t border-gray-100 flex items-center justify-end gap-3 shrink-0">
                    <button type="button" @click="previewOpen=false"
                        class="px-4 py-2 text-sm font-semibold rounded-xl border border-gray-200
                               text-gray-600 hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                    <button type="button" @click="previewOpen=false; $nextTick(() => $refs.mainForm.submit())"
                        class="inline-flex items-center gap-2 px-5 py-2 text-sm font-bold rounded-xl
                               bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Crear y enviar cotización
                    </button>
                </div>

            </div>
        </div>

        {{-- ── Modal Proveedor ─────────────────────────────────────────────── --}}
        {{-- z-index 200 supera cualquier elemento del formulario               --}}
        <div x-show="supplierModalOpen" x-cloak
             class="fixed inset-0 flex items-center justify-center p-4"
             style="z-index:200">
            <div class="absolute inset-0 bg-black/50" @click="supplierModalOpen=false"></div>
            <div class="relative w-full max-w-5xl max-h-[90vh]"
                 style="background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow-y:auto;">
                {{-- dark-mode inline style no disponible, usar clase condicional --}}

                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100"
                        x-text="supplierForm.id ? 'Actualizar proveedor' : 'Crear proveedor'"></h3>
                    <button type="button" @click="supplierModalOpen=false"
                        class="w-7 h-7 flex items-center justify-center rounded-lg
                               bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700
                               text-gray-500 text-xl leading-none transition">&times;</button>
                </div>

                <div class="px-5 py-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div class="xl:col-span-2">
                        <label class="f-label">Nombre proveedor *</label>
                        <input class="f-input" x-model="supplierForm.name"
                            @input="updateNotesBySupplier()" placeholder="Comercial Harcha SPA">
                    </div>
                    <div>
                        <label class="f-label">RUT</label>
                        <input class="f-input" x-model="supplierForm.rut" placeholder="77071100-2">
                    </div>
                    <div>
                        <label class="f-label">Tipo contribuyente</label>
                        <input class="f-input" x-model="supplierForm.taxpayer_type"
                            list="taxpayer-types-list" placeholder="IVA afecto 1ª categoría" autocomplete="off">
                    </div>

                    <div class="xl:col-span-2">
                        <label class="f-label">Dirección</label>
                        <input class="f-input" x-model="supplierForm.address_line_1" placeholder="Calle y número">
                    </div>
                    <div class="xl:col-span-2">
                        <label class="f-label">Datos adicionales</label>
                        <input class="f-input" x-model="supplierForm.address_line_2" placeholder="Oficina, referencia">
                    </div>

                    <div>
                        <label class="f-label">Región</label>
                        <select class="f-input" x-model="supplierForm.region">
                            <option value="">Seleccionar región</option>
                            <option>Arica y Parinacota</option>
                            <option>Tarapacá</option>
                            <option>Antofagasta</option>
                            <option>Atacama</option>
                            <option>Coquimbo</option>
                            <option>Valparaíso</option>
                            <option>Metropolitana de Santiago</option>
                            <option>Libertador General Bernardo O'Higgins</option>
                            <option>Maule</option>
                            <option>Ñuble</option>
                            <option>Biobío</option>
                            <option>La Araucanía</option>
                            <option>Los Ríos</option>
                            <option>Los Lagos</option>
                            <option>Aysén del General Carlos Ibáñez del Campo</option>
                            <option>Magallanes y de la Antártica Chilena</option>
                        </select>
                    </div>
                    <div>
                        <label class="f-label">Comuna</label>
                        <input class="f-input" x-model="supplierForm.comuna"
                            list="comunas-list" placeholder="Santiago" autocomplete="off">
                    </div>
                    <div>
                        <label class="f-label">Código postal</label>
                        <input class="f-input" x-model="supplierForm.postal_code">
                    </div>
                    <div>
                        <label class="f-label">País</label>
                        <select class="f-input" x-model="supplierForm.country">
                            <option value="Chile">Chile</option>
                            <option value="Argentina">Argentina</option>
                            <option value="Bolivia">Bolivia</option>
                            <option value="Perú">Perú</option>
                            <option value="Colombia">Colombia</option>
                            <option value="Ecuador">Ecuador</option>
                            <option value="Brasil">Brasil</option>
                            <option value="Uruguay">Uruguay</option>
                            <option value="México">México</option>
                            <option value="Estados Unidos">Estados Unidos</option>
                            <option value="Canadá">Canadá</option>
                            <option value="España">España</option>
                            <option value="Alemania">Alemania</option>
                            <option value="Francia">Francia</option>
                            <option value="Italia">Italia</option>
                            <option value="Reino Unido">Reino Unido</option>
                            <option value="China">China</option>
                            <option value="Japón">Japón</option>
                            <option value="Australia">Australia</option>
                        </select>
                    </div>

                    <div>
                        <label class="f-label">Teléfono</label>
                        <input class="f-input" x-model="supplierForm.phone">
                    </div>
                    <div>
                        <label class="f-label">Celular</label>
                        <input class="f-input" x-model="supplierForm.mobile">
                    </div>
                    <div>
                        <label class="f-label">Sitio web</label>
                        <input class="f-input" x-model="supplierForm.website" placeholder="https://...">
                    </div>
                    <div>
                        <label class="f-label">Idioma</label>
                        <input class="f-input" x-model="supplierForm.language" placeholder="es_CL">
                    </div>

                    <div class="xl:col-span-4">
                        <label class="f-label">Descripción de actividad</label>
                        <input class="f-input" x-model="supplierForm.activity_description">
                    </div>

                    <div class="xl:col-span-4">
                        <label class="f-label">Correos del proveedor</label>
                        <input class="f-input" x-model="supplierForm.emails"
                            placeholder="compras@proveedor.cl; facturacion@proveedor.cl">
                        <p class="text-[11px] text-gray-400 mt-1">Separa múltiples correos con punto y coma ( ; )</p>
                    </div>
                </div>

                <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2">
                    <button type="button" @click="supplierModalOpen=false"
                        class="px-4 py-2 text-xs font-bold rounded-xl bg-gray-100 text-gray-700
                               hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                        Cancelar
                    </button>
                    <button type="button" @click="saveSupplierFromModal()" :disabled="savingSupplier"
                        class="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 text-white
                               hover:bg-emerald-700 transition disabled:opacity-60"
                        x-text="savingSupplier ? 'Guardando...' : (supplierForm.id ? 'Actualizar proveedor' : 'Crear proveedor')">
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Datalists ─────────────────────────────────────────────────────── --}}
    <datalist id="taxpayer-types-list">
        <option value="IVA afecto 1ª categoría">
        <option value="IVA exento 1ª categoría">
        <option value="2ª categoría (honorarios)">
        <option value="Persona Natural con Giro">
        <option value="EIRL">
        <option value="Sociedad de Responsabilidad Limitada (LTDA)">
        <option value="Sociedad Anónima Cerrada (S.A.C.)">
        <option value="Sociedad Anónima Abierta (S.A.A.)">
        <option value="SpA (Sociedad por Acciones)">
        <option value="Cooperativa">
        <option value="Corporación / Fundación">
        <option value="Microempresa (Pro Pyme Transparente)">
        <option value="Pequeña empresa (Pro Pyme General)">
        <option value="Gran empresa (Semi Integrado)">
        <option value="Exportador">
        <option value="Entidad pública">
    </datalist>

    <datalist id="comunas-list">
        <option value="Arica"><option value="Iquique"><option value="Alto Hospicio">
        <option value="Antofagasta"><option value="Calama"><option value="San Pedro de Atacama">
        <option value="Copiapó"><option value="Vallenar"><option value="Caldera">
        <option value="La Serena"><option value="Coquimbo"><option value="Ovalle"><option value="Illapel"><option value="Los Vilos">
        <option value="Valparaíso"><option value="Viña del Mar"><option value="Quilpué"><option value="Villa Alemana">
        <option value="Concón"><option value="San Antonio"><option value="Los Andes"><option value="San Felipe">
        <option value="Quillota"><option value="La Calera"><option value="Limache">
        <option value="Santiago"><option value="Las Condes"><option value="Providencia"><option value="Ñuñoa">
        <option value="La Florida"><option value="Maipú"><option value="Puente Alto"><option value="San Bernardo">
        <option value="Lo Barnechea"><option value="Vitacura"><option value="La Reina"><option value="San Miguel">
        <option value="Peñalolén"><option value="Conchalí"><option value="Recoleta"><option value="Quilicura">
        <option value="Independencia"><option value="El Bosque"><option value="La Cisterna">
        <option value="Pudahuel"><option value="Quinta Normal"><option value="Renca"><option value="Huechuraba">
        <option value="Estación Central"><option value="Colina"><option value="Lampa"><option value="Melipilla">
        <option value="Talagante"><option value="Buin"><option value="Paine">
        <option value="Rancagua"><option value="San Fernando"><option value="Pichilemu"><option value="Santa Cruz">
        <option value="Talca"><option value="Curicó"><option value="Linares"><option value="Cauquenes">
        <option value="Chillán"><option value="San Carlos">
        <option value="Concepción"><option value="Talcahuano"><option value="San Pedro de la Paz"><option value="Coronel">
        <option value="Lota"><option value="Tomé"><option value="Hualpén"><option value="Los Ángeles">
        <option value="Temuco"><option value="Villarrica"><option value="Pucón"><option value="Angol">
        <option value="Valdivia"><option value="La Unión"><option value="Panguipulli">
        <option value="Puerto Montt"><option value="Osorno"><option value="Castro"><option value="Puerto Varas"><option value="Ancud">
        <option value="Coyhaique"><option value="Puerto Aysén">
        <option value="Punta Arenas"><option value="Puerto Natales">
    </datalist>

    <script>
        function purchaseOrderForm(products, suppliers, notesTemplate) {
            return {
                products,
                suppliers,

                // Lista de destinatarios: [{supplierId, name, email}]
                // Puede tener múltiples entradas del mismo proveedor (un por email)
                recipientList: [],

                // Combobox
                supplierSearch: '',
                supplierDropOpen: false,

                currency: 'CLP',
                notesTemplate,
                notesTouched: false,
                notes: '',

                supplierModalOpen: false,
                savingSupplier: false,
                supplierForm: {
                    id: null, name: '', rut: '', taxpayer_type: '', activity_description: '',
                    address_line_1: '', address_line_2: '', comuna: '', region: '', postal_code: '',
                    country: 'Chile', phone: '', mobile: '', website: '', language: 'es_CL', emails: ''
                },

                lines: [{
                    uid: Date.now(),
                    inventory_product_id: '', product_name: '',
                    productSearch: '', productDropOpen: false,
                    saveAsInventory: false,
                    unit: 'UN', quantity: 1, unit_price: 0, line_total: 0
                }],

                previewOpen: false,

                init() {
                    // NO auto-selecciona ningún proveedor — empieza vacío
                    // Si no hay ningún proveedor registrado, abre el modal para crear el primero
                    if (this.suppliers.length === 0) {
                        this.openSupplierModal(null);
                    }
                    this.updateNotesBySupplier();
                },

                // ── Combobox ─────────────────────────────────────────────────────
                filteredSuppliers() {
                    const q = (this.supplierSearch || '').toLowerCase().trim();
                    if (!q) return this.suppliers;
                    return this.suppliers.filter(s =>
                        (s.name || '').toLowerCase().includes(q) ||
                        (s.rut  || '').toLowerCase().includes(q)
                    );
                },

                comboEnter() {
                    const list = this.filteredSuppliers();
                    if (list.length === 1) this.addSupplierToRecipients(list[0]);
                },

                // ── Gestión de destinatarios ──────────────────────────────────────
                addSupplierToRecipients(sp) {
                    const emails = Array.isArray(sp.emails) ? sp.emails : [];

                    if (emails.length === 0) {
                        // Sin correos: agregar igual (para poder editar después)
                        const already = this.recipientList.find(
                            r => r.supplierId === String(sp.id) && !r.email
                        );
                        if (!already) {
                            this.recipientList.push({ supplierId: String(sp.id), name: sp.name, email: '' });
                        }
                    } else {
                        emails.forEach(em => {
                            const addr = em.email || em;
                            if (!addr) return;
                            const already = this.recipientList.find(
                                r => r.supplierId === String(sp.id) && r.email === addr
                            );
                            if (!already) {
                                this.recipientList.push({ supplierId: String(sp.id), name: sp.name, email: addr });
                            }
                        });
                    }

                    this.supplierSearch   = '';
                    this.supplierDropOpen = false;
                    this.updateNotesBySupplier();
                },

                removeRecipient(idx) {
                    this.recipientList.splice(idx, 1);
                    this.updateNotesBySupplier();
                },

                // Proveedores únicos seleccionados (para los chips del tag-input)
                // Incluye hasEmail para mostrar chip naranja cuando falta correo
                uniqueSelectedSuppliers() {
                    const seen = new Set();
                    const result = [];
                    this.recipientList.forEach(r => {
                        if (!seen.has(r.supplierId)) {
                            seen.add(r.supplierId);
                            const hasEmail = this.recipientList.some(
                                x => x.supplierId === r.supplierId && x.email
                            );
                            result.push({ supplierId: r.supplierId, name: r.name, hasEmail });
                        }
                    });
                    return result;
                },

                // Quita TODOS los emails de un proveedor de la lista (al clickar × en el chip)
                removeSupplierFromRecipients(supplierId) {
                    this.recipientList = this.recipientList.filter(
                        r => r.supplierId !== String(supplierId)
                    );
                    this.updateNotesBySupplier();
                },

                // Asigna el correo a un destinatario sin email (edición inline en la tarjeta)
                setRecipientEmail(idx, email) {
                    const trimmed = (email || '').trim().toLowerCase();
                    if (!trimmed) return;
                    this.recipientList[idx] = { ...this.recipientList[idx], email: trimmed };
                },

                // Primer supplier_id de la lista (para el campo hidden del formulario)
                primarySupplierId() {
                    return this.recipientList.length > 0
                        ? (this.recipientList[0].supplierId || '')
                        : '';
                },

                // ── Moneda ────────────────────────────────────────────────────────
                currencySymbol() {
                    return { CLP: '$', USD: 'US$', EUR: '€' }[this.currency] || this.currency;
                },

                // ── Notas ─────────────────────────────────────────────────────────
                updateNotesBySupplier() {
                    if (this.notesTouched) return;
                    // Nombres únicos de todos los proveedores en la lista
                    const names = [...new Set(
                        this.recipientList.map(r => r.name).filter(Boolean)
                    )];
                    const mainName = names[0] || this.supplierForm.name || 'Proveedor';
                    const destStr  = names.length > 0 ? names.join(', ') : mainName;

                    let text = this.notesTemplate.replaceAll('{PROVEEDOR}', mainName);
                    text     = text.replaceAll('{DESTINATARIOS}', destStr);
                    this.notes = text;
                },

                // ── Modal proveedor ───────────────────────────────────────────────
                openSupplierModal(supplierId) {
                    const sp = supplierId
                        ? this.suppliers.find(s => String(s.id) === String(supplierId))
                        : null;

                    if (sp) {
                        this.supplierForm = {
                            id: sp.id,
                            name: sp.name || '',
                            rut:  sp.rut  || '',
                            taxpayer_type:        sp.taxpayer_type        || '',
                            activity_description: sp.activity_description || '',
                            address_line_1: sp.address_line_1 || '',
                            address_line_2: sp.address_line_2 || '',
                            comuna:      sp.comuna      || '',
                            region:      sp.region      || '',
                            postal_code: sp.postal_code || '',
                            country:  sp.country  || 'Chile',
                            phone:    sp.phone    || '',
                            mobile:   sp.mobile   || '',
                            website:  sp.website  || '',
                            language: sp.language || 'es_CL',
                            emails: (sp.emails || []).map(e => e.email || e).join('; ')
                        };
                    } else {
                        // Nuevo proveedor: usar lo que el usuario escribió en el combobox
                        this.supplierForm = {
                            id: null,
                            name: (this.supplierSearch || '').split('(')[0].trim(),
                            rut: '', taxpayer_type: '', activity_description: '',
                            address_line_1: '', address_line_2: '', comuna: '', region: '',
                            postal_code: '', country: 'Chile', phone: '', mobile: '',
                            website: '', language: 'es_CL', emails: ''
                        };
                    }
                    this.supplierDropOpen  = false;
                    this.supplierModalOpen = true;
                },

                async saveSupplierFromModal() {
                    if (!(this.supplierForm.name || '').trim()) {
                        alert('Debes ingresar el nombre del proveedor.');
                        return;
                    }
                    this.savingSupplier = true;
                    try {
                        // El servidor espera "supplier_id" para saber si actualizar o crear
                        const payload = { ...this.supplierForm, supplier_id: this.supplierForm.id || null };
                        const res = await fetch('{{ route('purchase_orders.suppliers.upsert') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });
                        const data = await res.json();
                        if (!res.ok || !data.ok) throw new Error(data.message || 'No se pudo guardar.');

                        const saved = data.supplier;
                        const idx   = this.suppliers.findIndex(s => String(s.id) === String(saved.id));

                        if (idx >= 0) {
                            // Actualizar proveedor existente en el array de suppliers
                            this.suppliers[idx] = saved;

                            // Reconstruir entradas de recipientList para este proveedor
                            // (puede haber cambiado nombre y/o correos)
                            const wasInList = this.recipientList.some(
                                r => r.supplierId === String(saved.id)
                            );
                            if (wasInList) {
                                // Quitar entradas viejas y volver a agregar con datos frescos
                                this.recipientList = this.recipientList.filter(
                                    r => r.supplierId !== String(saved.id)
                                );
                                this.addSupplierToRecipients(saved);
                            }
                            this.updateNotesBySupplier();
                        } else {
                            // Nuevo proveedor: agregar al array y a destinatarios
                            this.suppliers.push(saved);
                            this.suppliers.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                            this.addSupplierToRecipients(saved);
                        }

                        this.supplierSearch   = '';
                        this.supplierModalOpen = false;
                    } catch (err) {
                        alert(err.message || 'Error guardando proveedor.');
                    } finally {
                        this.savingSupplier = false;
                    }
                },

                // ── Líneas ────────────────────────────────────────────────────────
                addLine() {
                    this.lines.push({
                        uid: Date.now() + Math.random(),
                        inventory_product_id: '', product_name: '',
                        productSearch: '', productDropOpen: false,
                        saveAsInventory: false,
                        unit: 'UN', quantity: 1, unit_price: 0, line_total: 0
                    });
                },

                removeLine(index) {
                    if (this.lines.length === 1) return;
                    this.lines.splice(index, 1);
                },

                // ── Combobox de producto ──────────────────────────────────────────
                filteredProducts(line) {
                    const q = (line.productSearch || '').toLowerCase().trim();
                    if (!q) return this.products.slice(0, 25);
                    return this.products.filter(p =>
                        (p.nombre || '').toLowerCase().includes(q) ||
                        (p.codigo || '').toLowerCase().includes(q)
                    ).slice(0, 30);
                },

                selectProduct(line, product) {
                    line.inventory_product_id = String(product.id);
                    line.product_name  = product.nombre || '';
                    line.productSearch = product.nombre || '';
                    line.unit = product.unidad || line.unit || 'UN';
                    if ((!line.unit_price || Number(line.unit_price) === 0) &&
                        Number(product.costo_promedio || 0) > 0) {
                        line.unit_price = Number(product.costo_promedio);
                    }
                    line.productDropOpen = false;
                    this.recalc(line);
                },

                selectFirstProduct(line) {
                    const list = this.filteredProducts(line);
                    if (list.length > 0) this.selectProduct(line, list[0]);
                },

                fillFromInventory(line) {
                    if (!line.inventory_product_id) return;
                    const p = this.products.find(x => String(x.id) === String(line.inventory_product_id));
                    if (!p) return;
                    line.product_name = p.nombre || line.product_name;
                    line.unit         = p.unidad || line.unit || 'UN';
                    if ((!line.unit_price || Number(line.unit_price) === 0) && Number(p.costo_promedio || 0) > 0) {
                        line.unit_price = Number(p.costo_promedio);
                    }
                    this.recalc(line);
                },

                // ── Preview / confirmación ────────────────────────────────────────
                openPreview() {
                    this.beforeSubmit();
                    const hasRecipients = this.recipientList.some(r => r.email);
                    if (!hasRecipients) {
                        alert('Agrega al menos un destinatario con correo electrónico antes de continuar.');
                        return;
                    }
                    const hasLines = this.lines.some(l => (l.product_name || '').trim());
                    if (!hasLines) {
                        alert('Agrega al menos una línea de producto.');
                        return;
                    }
                    this.previewOpen = true;
                },

                recalc(line) {
                    line.line_total = Number(line.quantity || 0) * Number(line.unit_price || 0);
                },

                grandTotal() {
                    return this.lines.reduce((acc, l) => acc + Number(l.line_total || 0), 0);
                },

                money(v) {
                    return new Intl.NumberFormat('es-CL', {
                        minimumFractionDigits: 2, maximumFractionDigits: 2
                    }).format(Number(v || 0));
                },

                beforeSubmit() {
                    this.lines.forEach(l => this.recalc(l));
                    this.updateNotesBySupplier();
                }
            }
        }
    </script>
</x-app-layout>
