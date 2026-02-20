<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 min-w-0 text-xs">
                <a href="{{ route('purchase_orders.index') }}"
                    class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium truncate">
                    Órdenes de compra
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300">Nueva orden</span>
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

        /* Combobox — z-index DEBE ser menor que el modal (z-50 = 50) */
        .combo-wrap { position:relative; }
        .combo-drop {
            position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:20;
            background:#fff; border:1px solid #e2e8f0; border-radius:12px;
            box-shadow:0 8px 32px rgba(0,0,0,.12); max-height:260px; overflow-y:auto;
        }
        .dark .combo-drop { background:#161c2c; border-color:#1e2a3b; box-shadow:0 8px 32px rgba(0,0,0,.4) }
        .combo-item {
            display:flex; align-items:center; justify-content:space-between;
            padding:9px 12px; cursor:pointer; font-size:13px; color:#334155;
        }
        .combo-item:hover { background:#f5fffb }
        .dark .combo-item { color:#cbd5e1 }
        .dark .combo-item:hover { background:rgba(16,185,129,.05) }
        .combo-empty { padding:9px 12px; font-size:12px; color:#94a3b8; font-style:italic }
        .combo-create {
            display:flex; align-items:center; gap:6px;
            padding:9px 12px; cursor:pointer; font-size:12px;
            color:#10b981; font-weight:700; border-top:1px solid #f1f5f9;
        }
        .combo-create:hover { background:#f0fdf4 }
        .dark .combo-create { color:#34d399; border-top-color:#1a2232 }
        .dark .combo-create:hover { background:rgba(16,185,129,.08) }

        /* Chips de destinatarios */
        .dest-chip {
            display:inline-flex; align-items:center; gap:6px;
            padding:5px 10px; border-radius:999px; cursor:pointer;
            font-size:11px; font-weight:700; border:1.5px solid;
            transition:.15s; user-select:none;
        }
        .dest-chip-on  { background:#ecfdf5; border-color:#6ee7b7; color:#065f46 }
        .dest-chip-off { background:#f8fafc; border-color:#e2e8f0; color:#94a3b8; text-decoration:line-through; opacity:.6 }
        .dark .dest-chip-on  { background:rgba(16,185,129,.12); border-color:rgba(16,185,129,.3); color:#34d399 }
        .dark .dest-chip-off { background:#0f1623; border-color:#1e2a3b; color:#475569 }
    </style>

    <div class="page-bg" x-data="purchaseOrderForm(@js($products), @js($suppliers), @js($defaultNotesTemplate))">

        {{-- Backdrop liviano para cerrar combobox (z-10, MUY por debajo del modal) --}}
        <div x-show="supplierDropOpen" x-cloak @click="supplierDropOpen=false"
             class="fixed inset-0" style="z-index:10; background:transparent;"></div>

        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-4">

            @if($errors->any())
                <div class="px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700
                        dark:bg-rose-900/20 dark:border-rose-800 dark:text-rose-400 text-sm au d1">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('purchase_orders.store') }}" @submit="beforeSubmit()">
                @csrf

                {{-- ── Panel 1: Proveedor ─────────────────────────────────────────── --}}
                {{-- overflow:visible para que el dropdown del combobox no quede recortado --}}
                <div class="panel au d1" style="overflow:visible">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Proveedor y envío</h3>
                    </div>

                    <div class="px-5 py-4 space-y-4">
                        <input type="hidden" name="supplier_mode" value="existing">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            {{-- ── Combobox proveedor ── --}}
                            <div>
                                <label class="f-label">Proveedor</label>
                                <div class="flex gap-2">
                                    {{-- Input + dropdown (z-index 20 en combo-drop, por debajo del modal) --}}
                                    <div class="combo-wrap flex-1">
                                        <input type="text"
                                            class="f-input"
                                            x-model="supplierSearch"
                                            @focus="supplierDropOpen=true"
                                            @input="supplierDropOpen=true; onSupplierSearchChange()"
                                            @keydown.escape="supplierDropOpen=false"
                                            @keydown.enter.prevent="supplierDropEnter()"
                                            placeholder="Buscar o crear proveedor..."
                                            autocomplete="off">
                                        <input type="hidden" name="supplier_id" :value="selectedSupplierId">

                                        <div x-show="supplierDropOpen" x-cloak class="combo-drop">
                                            <template x-for="sp in filteredSuppliers()" :key="sp.id">
                                                <div class="combo-item" @click="selectSupplier(sp)">
                                                    <span class="font-semibold truncate" x-text="sp.name"></span>
                                                    <span class="text-[11px] text-gray-400 shrink-0 ml-2" x-text="sp.rut || ''"></span>
                                                </div>
                                            </template>
                                            <div class="combo-empty"
                                                x-show="filteredSuppliers().length === 0 && supplierSearch.trim()">
                                                Sin resultados &mdash; crea uno nuevo.
                                            </div>
                                            <div class="combo-create" @click="openSupplierModal(); supplierDropOpen=false">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                Crear nuevo proveedor
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Botón Editar (solo cuando hay proveedor seleccionado) --}}
                                    <button type="button" @click="openSupplierModal()"
                                        x-show="selectedSupplierId" x-cloak
                                        class="shrink-0 px-3 py-2 rounded-xl text-xs font-bold
                                               bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700
                                               text-gray-700 dark:text-gray-300 transition whitespace-nowrap">
                                        Editar
                                    </button>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1" x-show="selectedSupplierId" x-cloak>
                                    Proveedor seleccionado &middot;
                                    <button type="button" class="text-rose-400 hover:text-rose-600 hover:underline"
                                        @click="clearSupplier()">Limpiar</button>
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

                            {{-- ── Destinatarios ── --}}
                            <div class="md:col-span-2">
                                <label class="f-label">Destinatarios del envío</label>

                                {{-- Chips de correos del proveedor --}}
                                <div x-show="selectedSupplierEmails.length" class="flex flex-wrap gap-2">
                                    <template x-for="em in selectedSupplierEmails" :key="em.email">
                                        <label
                                            class="dest-chip"
                                            :class="em.checked ? 'dest-chip-on' : 'dest-chip-off'">
                                            <input type="checkbox"
                                                name="recipient_emails[]"
                                                :value="em.email"
                                                x-model="em.checked"
                                                @change="updateNotesBySupplier()"
                                                class="sr-only">
                                            {{-- Icono check/uncheck --}}
                                            <svg x-show="em.checked" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <svg x-show="!em.checked" class="w-3 h-3 shrink-0 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            <span x-text="em.email"></span>
                                        </label>
                                    </template>
                                </div>

                                {{-- Estado cuando no hay correos --}}
                                <p class="text-xs text-gray-400" x-show="!selectedSupplierEmails.length">
                                    <span x-show="selectedSupplierId" x-cloak>
                                        Este proveedor no tiene correos guardados &mdash;
                                        <button type="button" @click="openSupplierModal()"
                                            class="text-emerald-600 hover:underline font-semibold">agrégalos en Editar</button>.
                                    </span>
                                    <span x-show="!selectedSupplierId">Selecciona un proveedor para ver sus destinatarios.</span>
                                </p>
                            </div>
                        </div>

                        {{-- Observaciones --}}
                        <div>
                            <label class="f-label">Observaciones</label>
                            <textarea name="notes" rows="4" class="f-input" style="resize:vertical"
                                x-model="notes" @input="notesTouched=true"></textarea>
                        </div>
                    </div>
                </div>

                {{-- ── Panel 2: Líneas ──────────────────────────────────────────────── --}}
                <div class="panel au d2 mt-4">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Líneas de productos</h3>
                        <button type="button" @click="addLine()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Agregar línea
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th class="w-10 text-center">#</th>
                                    <th>Producto de inventario</th>
                                    <th>Descripción / nombre</th>
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
                                        <td class="min-w-[220px]">
                                            <select :name="`items[${i}][inventory_product_id]`"
                                                x-model="line.inventory_product_id"
                                                @change="fillFromInventory(line)"
                                                class="f-cell">
                                                <option value="">— Manual —</option>
                                                <template x-for="p in products" :key="p.id">
                                                    <option :value="String(p.id)"
                                                        x-text="`${p.nombre}${p.codigo ? ' (' + p.codigo + ')' : ''}`"></option>
                                                </template>
                                            </select>
                                        </td>
                                        <td class="min-w-[200px]">
                                            <input :name="`items[${i}][product_name]`" x-model="line.product_name"
                                                class="f-cell" placeholder="Nombre del producto">
                                            <label class="mt-1 inline-flex items-center gap-1 text-[11px] text-gray-500 cursor-pointer"
                                                x-show="!line.inventory_product_id">
                                                <input type="checkbox" :name="`items[${i}][save_as_inventory]`" value="1"
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
                                                class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-500 transition">
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

                    <div class="border-t-2 border-gray-100 dark:border-gray-800 px-5 py-4 bg-gray-50/60 dark:bg-gray-900/20 flex items-center justify-between gap-4 flex-wrap">
                        <p class="text-xs text-gray-400"><span x-text="lines.length"></span> línea<span x-show="lines.length !== 1">s</span></p>
                        <div class="flex items-center gap-6">
                            <div class="text-right">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Total</p>
                                <p class="text-2xl font-black tabular-nums text-emerald-600 dark:text-emerald-400">
                                    <span x-text="currencySymbol()"></span>&nbsp;<span x-text="money(grandTotal())"></span>
                                </p>
                            </div>
                            <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Crear orden
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- ── Modal Proveedor (z-50 > backdrop z-10 > combo-drop z-20) ─────── --}}
        <div x-show="supplierModalOpen" x-cloak
             class="fixed inset-0 flex items-center justify-center p-4"
             style="z-index:200">
            <div class="absolute inset-0 bg-black/50" @click="supplierModalOpen=false"></div>
            <div class="relative panel w-full max-w-5xl max-h-[90vh] overflow-y-auto" style="overflow-x:hidden; overflow-y:auto">

                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100"
                        x-text="supplierForm.id ? 'Actualizar proveedor' : 'Crear proveedor'"></h3>
                    <button type="button" @click="supplierModalOpen=false"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200
                               dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 text-lg leading-none transition">
                        &times;
                    </button>
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
                            <option value="Venezuela">Venezuela</option>
                            <option value="Brasil">Brasil</option>
                            <option value="Uruguay">Uruguay</option>
                            <option value="Paraguay">Paraguay</option>
                            <option value="México">México</option>
                            <option value="Estados Unidos">Estados Unidos</option>
                            <option value="Canadá">Canadá</option>
                            <option value="España">España</option>
                            <option value="Alemania">Alemania</option>
                            <option value="Francia">Francia</option>
                            <option value="Italia">Italia</option>
                            <option value="Reino Unido">Reino Unido</option>
                            <option value="Portugal">Portugal</option>
                            <option value="China">China</option>
                            <option value="Japón">Japón</option>
                            <option value="Corea del Sur">Corea del Sur</option>
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
                        class="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 text-white hover:bg-emerald-700
                               transition disabled:opacity-60"
                        x-text="savingSupplier ? 'Guardando...' : (supplierForm.id ? 'Actualizar proveedor' : 'Crear proveedor')">
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Datalists ─────────────────────────────────────────────────────────── --}}
    <datalist id="taxpayer-types-list">
        <option value="IVA afecto 1ª categoría">
        <option value="IVA exento 1ª categoría">
        <option value="2ª categoría (honorarios)">
        <option value="Persona Natural con Giro">
        <option value="Empresa Individual (EI)">
        <option value="EIRL">
        <option value="Sociedad de Responsabilidad Limitada (LTDA)">
        <option value="Sociedad Anónima Cerrada (S.A.C.)">
        <option value="Sociedad Anónima Abierta (S.A.A.)">
        <option value="SpA (Sociedad por Acciones)">
        <option value="Sociedad Colectiva">
        <option value="Cooperativa">
        <option value="Corporación / Fundación">
        <option value="Microempresa (Pro Pyme Transparente)">
        <option value="Pequeña empresa (Pro Pyme General)">
        <option value="Gran empresa (Semi Integrado)">
        <option value="Exportador">
        <option value="Importador">
        <option value="Organización sin fines de lucro">
        <option value="Entidad pública">
    </datalist>

    <datalist id="comunas-list">
        <option value="Arica"><option value="Iquique"><option value="Alto Hospicio">
        <option value="Antofagasta"><option value="Calama"><option value="Tocopilla"><option value="San Pedro de Atacama">
        <option value="Copiapó"><option value="Vallenar"><option value="Caldera">
        <option value="La Serena"><option value="Coquimbo"><option value="Ovalle"><option value="Illapel"><option value="Los Vilos">
        <option value="Valparaíso"><option value="Viña del Mar"><option value="Quilpué"><option value="Villa Alemana">
        <option value="Concón"><option value="San Antonio"><option value="Los Andes"><option value="San Felipe">
        <option value="Quillota"><option value="La Calera"><option value="Limache"><option value="Olmué">
        <option value="Santiago"><option value="Las Condes"><option value="Providencia"><option value="Ñuñoa">
        <option value="La Florida"><option value="Maipú"><option value="Puente Alto"><option value="San Bernardo">
        <option value="Lo Barnechea"><option value="Vitacura"><option value="La Reina"><option value="San Miguel">
        <option value="Peñalolén"><option value="Conchalí"><option value="Recoleta"><option value="Quilicura">
        <option value="Independencia"><option value="El Bosque"><option value="La Cisterna"><option value="La Granja">
        <option value="Lo Espejo"><option value="Lo Prado"><option value="Macul"><option value="Padre Hurtado">
        <option value="Pudahuel"><option value="Quinta Normal"><option value="Renca"><option value="Huechuraba">
        <option value="Estación Central"><option value="Colina"><option value="Lampa"><option value="Melipilla">
        <option value="Talagante"><option value="Buin"><option value="Paine"><option value="El Monte">
        <option value="Rancagua"><option value="San Fernando"><option value="Pichilemu"><option value="Santa Cruz"><option value="Machalí">
        <option value="Talca"><option value="Curicó"><option value="Linares"><option value="Cauquenes"><option value="Constitución">
        <option value="Chillán"><option value="San Carlos"><option value="Chillán Viejo">
        <option value="Concepción"><option value="Talcahuano"><option value="San Pedro de la Paz"><option value="Coronel">
        <option value="Lota"><option value="Tomé"><option value="Hualpén"><option value="Los Ángeles"><option value="Lebu">
        <option value="Temuco"><option value="Villarrica"><option value="Pucón"><option value="Angol"><option value="Lautaro">
        <option value="Valdivia"><option value="La Unión"><option value="Panguipulli"><option value="Río Bueno">
        <option value="Puerto Montt"><option value="Osorno"><option value="Castro"><option value="Puerto Varas"><option value="Ancud">
        <option value="Calbuco"><option value="Frutillar"><option value="Llanquihue">
        <option value="Coyhaique"><option value="Puerto Aysén">
        <option value="Punta Arenas"><option value="Puerto Natales"><option value="Porvenir">
    </datalist>

    <script>
        function purchaseOrderForm(products, suppliers, notesTemplate) {
            return {
                products,
                suppliers,
                selectedSupplierId: '',
                selectedSupplierEmails: [],   // [{email, is_primary, name?, checked}]

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
                lines: [{ uid: Date.now(), inventory_product_id: '', product_name: '', unit: 'UN', quantity: 1, unit_price: 0, line_total: 0 }],

                init() {
                    if (this.suppliers.length > 0) {
                        this.selectSupplier(this.suppliers[0]);
                    } else {
                        this.openSupplierModal();
                        this.updateNotesBySupplier();
                    }
                },

                // ── Combobox ──────────────────────────────────────────────────────
                filteredSuppliers() {
                    const q = (this.supplierSearch || '').toLowerCase().trim();
                    if (!q) return this.suppliers;
                    return this.suppliers.filter(s =>
                        (s.name || '').toLowerCase().includes(q) ||
                        (s.rut  || '').toLowerCase().includes(q)
                    );
                },

                selectSupplier(sp) {
                    this.selectedSupplierId = String(sp.id);
                    this.supplierSearch     = sp.name + (sp.rut ? ' (' + sp.rut + ')' : '');
                    this.supplierDropOpen   = false;
                    // Cargar correos con checked=true por defecto
                    const emails = Array.isArray(sp.emails) ? sp.emails : [];
                    this.selectedSupplierEmails = emails.map(e => ({
                        email: e.email || e,
                        name:  e.name  || '',
                        is_primary: e.is_primary || false,
                        checked: true   // todos marcados por defecto
                    }));
                    this.updateNotesBySupplier();
                },

                clearSupplier() {
                    this.selectedSupplierId     = '';
                    this.supplierSearch         = '';
                    this.selectedSupplierEmails = [];
                    this.updateNotesBySupplier();
                },

                onSupplierSearchChange() {
                    if (!(this.supplierSearch || '').trim()) this.clearSupplier();
                },

                supplierDropEnter() {
                    const list = this.filteredSuppliers();
                    if (list.length === 1) this.selectSupplier(list[0]);
                },

                // ── Moneda ────────────────────────────────────────────────────────
                currencySymbol() {
                    return { CLP: '$', USD: 'US$', EUR: '€' }[this.currency] || this.currency;
                },

                // ── Notas ─────────────────────────────────────────────────────────
                currentSupplierName() {
                    const sp = this.suppliers.find(s => String(s.id) === String(this.selectedSupplierId));
                    return sp ? (sp.name || '').trim() : '';
                },

                updateNotesBySupplier() {
                    if (this.notesTouched) return;
                    const name = this.currentSupplierName() || this.supplierForm.name || 'Proveedor';

                    // Construir cadena de destinatarios activos
                    const checkedEmails = this.selectedSupplierEmails
                        .filter(e => e.checked)
                        .map(e => e.name || e.email)
                        .filter(Boolean);
                    const destStr = checkedEmails.length ? checkedEmails.join(', ') : name;

                    let text = this.notesTemplate.replaceAll('{PROVEEDOR}', name);
                    text     = text.replaceAll('{DESTINATARIOS}', destStr);
                    this.notes = text;
                },

                // ── Modal proveedor ───────────────────────────────────────────────
                openSupplierModal() {
                    const sp = this.suppliers.find(s => String(s.id) === String(this.selectedSupplierId));
                    if (sp) {
                        this.supplierForm = {
                            id: sp.id,
                            name: sp.name || '',
                            rut: sp.rut || '',
                            taxpayer_type: sp.taxpayer_type || '',
                            activity_description: sp.activity_description || '',
                            address_line_1: sp.address_line_1 || '',
                            address_line_2: sp.address_line_2 || '',
                            comuna: sp.comuna || '',
                            region: sp.region || '',
                            postal_code: sp.postal_code || '',
                            country: sp.country || 'Chile',
                            phone: sp.phone || '',
                            mobile: sp.mobile || '',
                            website: sp.website || '',
                            language: sp.language || 'es_CL',
                            emails: (sp.emails || []).map(e => e.email || e).join('; ')
                        };
                    } else {
                        this.supplierForm = {
                            id: null,
                            name: (this.supplierSearch || '').split('(')[0].trim(),
                            rut: '', taxpayer_type: '', activity_description: '',
                            address_line_1: '', address_line_2: '', comuna: '', region: '', postal_code: '',
                            country: 'Chile', phone: '', mobile: '', website: '', language: 'es_CL', emails: ''
                        };
                    }
                    this.supplierModalOpen = true;
                },

                async saveSupplierFromModal() {
                    if (!(this.supplierForm.name || '').trim()) {
                        alert('Debes ingresar el nombre del proveedor.');
                        return;
                    }
                    this.savingSupplier = true;
                    try {
                        const res = await fetch('{{ route('purchase_orders.suppliers.upsert') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.supplierForm)
                        });
                        const data = await res.json();
                        if (!res.ok || !data.ok) throw new Error(data.message || 'No se pudo guardar el proveedor.');

                        const saved = data.supplier;
                        const idx   = this.suppliers.findIndex(s => String(s.id) === String(saved.id));
                        if (idx >= 0) {
                            this.suppliers[idx] = saved;
                        } else {
                            this.suppliers.push(saved);
                            this.suppliers.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                        }
                        this.selectSupplier(saved);
                        this.supplierModalOpen = false;
                    } catch (err) {
                        alert(err.message || 'Error guardando proveedor.');
                    } finally {
                        this.savingSupplier = false;
                    }
                },

                // ── Líneas ────────────────────────────────────────────────────────
                addLine() {
                    this.lines.push({ uid: Date.now() + Math.random(), inventory_product_id: '', product_name: '', unit: 'UN', quantity: 1, unit_price: 0, line_total: 0 });
                },

                removeLine(index) {
                    if (this.lines.length === 1) return;
                    this.lines.splice(index, 1);
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

                recalc(line) {
                    line.line_total = Number(line.quantity || 0) * Number(line.unit_price || 0);
                },

                grandTotal() {
                    return this.lines.reduce((acc, l) => acc + Number(l.line_total || 0), 0);
                },

                money(v) {
                    return new Intl.NumberFormat('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));
                },

                beforeSubmit() {
                    this.lines.forEach(l => this.recalc(l));
                    this.updateNotesBySupplier();
                }
            }
        }
    </script>
</x-app-layout>
