@if(auth()->user()->isAdmin())
<div id="referencias-cruzadas" class="p-4 sm:p-6 space-y-6"
     x-data="dtePurchaseReferences(@js([
        'report' => route('gmail.dtes.purchase_references.show', $document->id),
        'candidates' => route('gmail.dtes.purchase_references.candidates', $document->id),
        'store' => route('gmail.dtes.purchase_references.store', $document->id),
        'classify' => route('gmail.dtes.purchase_references.classify', $document->id),
     ]))"
     x-init="$watch('tab', value => { if (value === 'cruzadas' && !report && !loading) load() }); if (tab === 'cruzadas') load()"
     @dte-odoo-orders.window="if (report) report.odoo_orders = $event.detail">

    {{-- Encabezado: estado y avance --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Solicitudes de compra</h3>
            <p class="text-xs text-gray-500 mt-0.5">Qué partidas de esta factura se pidieron en una solicitud. Una factura puede cubrir varias.</p>
        </div>
        <span x-show="report" x-cloak class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold"
              :class="viaOdoo ? 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300' : statusClass"
              x-text="viaOdoo ? 'Compra hecha en Odoo' : labels[report?.classification]"></span>
    </div>

    {{-- Comprada con una orden hecha directo en Odoo: no lleva solicitud, y el resto sobra. --}}
    <div x-show="viaOdoo" x-cloak class="flex items-center justify-between gap-3 flex-wrap rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:bg-sky-950/40 dark:text-sky-200">
        <span x-text="'Se compró con la orden ' + (report?.odoo_orders || []).join(', ') + ', hecha directo en Odoo: no lleva solicitud.'"></span>
        <button type="button" x-show="!showRequests" @click="showRequests = true" class="text-xs font-semibold underline">¿Tiene también solicitud? Buscar</button>
    </div>

    <div x-show="report && report.lines.length && (!viaOdoo || showRequests)" x-cloak>
        <div class="flex items-center justify-between text-xs mb-1.5">
            <span class="font-semibold text-gray-600 dark:text-gray-300" x-text="linkedLines + ' de ' + report?.lines.length + ' líneas vinculadas'"></span>
            <span class="text-gray-400" x-text="report?.references.length + (report?.references.length === 1 ? ' vínculo' : ' vínculos')"></span>
        </div>
        <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
            <div class="h-full rounded-full bg-emerald-500 transition-all" :style="`width: ${report ? Math.round(linkedLines * 100 / report.lines.length) : 0}%`"></div>
        </div>
    </div>

    {{-- Notas de crédito que corrigen esta factura --}}
    <template x-for="note in (report?.credit_notes || [])" :key="note.folio">
        <p class="rounded-xl px-4 py-3 text-sm" :class="note.annuls ? 'bg-rose-50 text-rose-900 dark:bg-rose-950/40 dark:text-rose-200' : 'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'">
            <span class="font-semibold" x-text="note.annuls ? 'Anulada por la nota de crédito N° ' + note.folio : 'Nota de crédito N° ' + note.folio + ': ' + money(-note.net)"></span>
            <span class="text-xs opacity-80" x-text="note.reason ? ' · ' + note.reason : ''"></span>
        </p>
    </template>

    {{-- Lo que cobra la factura frente a lo que se pidió --}}
    <div x-show="report?.prices" x-cloak class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm"
         :class="priceAlert ? (report?.prices?.difference > 0 ? 'bg-rose-50 text-rose-900 dark:bg-rose-950/40 dark:text-rose-200' : 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200') : 'bg-gray-50 text-gray-600 dark:bg-gray-800/50 dark:text-gray-300'">
        <template x-if="report?.prices">
            <div class="min-w-0 flex-1">
                <p class="font-semibold" x-text="!priceAlert ? 'Precios dentro de lo solicitado'
                    : 'La factura cobra ' + money(Math.abs(report.prices.difference)) + (report.prices.difference > 0 ? ' más' : ' menos') + ' que lo solicitado (' + percent(report.prices.change) + ')'"></p>
                <p class="mt-0.5 text-xs opacity-80">
                    <span x-text="'Neto de las partidas vinculadas: solicitado ' + money(report.prices.requested) + ' · facturado ' + money(report.prices.invoiced)"></span>
                    <span x-show="report.prices.without_price" x-text="' · ' + report.prices.without_price + (report.prices.without_price === 1 ? ' partida sin precio en la solicitud' : ' partidas sin precio en la solicitud')"></span>
                </p>
            </div>
        </template>
    </div>

    <p x-show="loading && !report" role="status" class="text-sm text-gray-500">Cargando relaciones…</p>
    <div x-show="error" x-cloak role="alert" class="flex items-start justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
        <span x-text="error"></span>
        <button type="button" @click="load()" class="shrink-0 font-semibold underline" :disabled="busy">Actualizar</button>
    </div>
    <p x-show="message" x-cloak role="status" x-text="message" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"></p>

    <template x-if="report">
    <div class="space-y-6">
        <p x-show="!report.eligible" class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-800/50">La relación con solicitudes está disponible para facturas vigentes. Este documento es de otro tipo o está anulado/rechazado.</p>
        <div x-show="report.decision_stale" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">La factura cambió desde que se marcó como compra sin solicitud. Revisa nuevamente la clasificación.</div>

        {{-- Vinculadas, agrupadas por solicitud --}}
        <template x-for="group in groups" :key="group.folio">
            <section class="rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <header class="flex items-center justify-between gap-3 flex-wrap bg-gray-50 px-4 py-2.5 dark:bg-gray-800/60">
                    <a :href="group.url || '#'" class="inline-flex items-center gap-1.5 text-sm font-bold text-indigo-600 hover:text-indigo-800 dark:text-indigo-300">
                        <span x-text="group.folio"></span>
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                    <span class="text-xs text-gray-500" x-text="group.rows.length + (group.rows.length === 1 ? ' partida' : ' partidas')"></span>
                </header>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-for="reference in group.rows" :key="reference.id">
                        <li class="px-4 py-3" :class="reference.stale ? 'bg-amber-50/70 dark:bg-amber-950/20' : ''">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" :title="reference.invoice_product" x-text="reference.invoice_product"></p>
                                    <p x-show="!sameName(reference.invoice_product, reference.request_product)" class="mt-0.5 text-xs text-gray-500 truncate" :title="reference.request_product">
                                        <span class="text-gray-400">En la solicitud:</span> <span x-text="reference.request_product"></span>
                                    </p>
                                    <p x-show="reference.measures" class="mt-0.5 text-xs font-semibold text-rose-600 dark:text-rose-400" x-text="reference.measures && measuresText(reference.measures)"></p>
                                    <p x-show="reference.returned" class="mt-0.5 text-xs font-semibold text-amber-700 dark:text-amber-400"
                                       x-text="'La nota de crédito devolvió ' + number(reference.returned) + ' ' + shortUnit(reference.request_unit) + ': vuelven a quedar pendientes en la solicitud'"></p>
                                    <p x-show="reference.price" class="mt-0.5 text-xs tabular-nums" :class="priceClass(reference.price?.change)">
                                        <span x-text="reference.price && (money(reference.price.invoiced_unit) + ' c/u · solicitado ' + money(reference.price.requested_unit))"></span>
                                        <span x-show="reference.price && reference.price.change !== 0" x-text="reference.price && ('(' + percent(reference.price.change) + ')')"></span>
                                    </p>
                                    <p x-show="reference.stale" class="mt-0.5 text-xs font-semibold text-amber-700 dark:text-amber-300">Revisar: cambió una de las partidas</p>
                                    <p x-show="reference.remaining_request !== null && number(reference.remaining_request) !== '0'" class="mt-0.5 text-xs text-gray-400"
                                       x-text="'Quedan ' + number(reference.remaining_request) + ' de ' + number(reference.requested_quantity) + ' por facturar en la solicitud'"></p>
                                </div>
                                <div class="shrink-0 flex items-center gap-3">
                                    <span class="hidden sm:inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="sourceClass(reference.source)"
                                          :title="reference.note" x-text="sourceLabel(reference)"></span>
                                    <div class="text-right">
                                        <p class="text-sm font-bold tabular-nums text-gray-900 dark:text-gray-100" x-text="number(reference.invoice_quantity) + ' ' + shortUnit(reference.invoice_unit)"></p>
                                        <p x-show="number(reference.invoice_quantity) !== number(reference.request_quantity) || shortUnit(reference.invoice_unit) !== shortUnit(reference.request_unit)"
                                           class="text-xs tabular-nums text-gray-500" x-text="'= ' + number(reference.request_quantity) + ' ' + shortUnit(reference.request_unit)"></p>
                                    </div>
                                    <button type="button" @click="revoking = reference.id; revokeNote = ''" title="Desvincular" aria-label="Desvincular"
                                            class="rounded-md p-1 text-gray-300 hover:bg-rose-50 hover:text-rose-600 dark:text-gray-600 dark:hover:bg-rose-950/40" :disabled="busy">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                            <form x-show="revoking === reference.id" @submit.prevent="revoke()" class="mt-3 flex gap-2 flex-wrap">
                                <input type="text" x-model="revokeNote" minlength="5" maxlength="1500" required placeholder="Motivo de la desvinculación"
                                       class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-700">
                                <button class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white" :disabled="busy">Desvincular</button>
                                <button type="button" @click="revoking = null" class="px-2 text-sm text-gray-500">Cancelar</button>
                            </form>
                        </li>
                    </template>
                </ul>
                <div x-show="group.unbilled.length" class="border-t border-dashed border-gray-200 bg-gray-50/60 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-800/30">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">La solicitud también pidió · sin facturar todavía</p>
                    <ul class="mt-1.5 space-y-1">
                        <template x-for="(item, index) in group.unbilled" :key="index">
                            <li class="flex justify-between gap-3 text-sm">
                                <span class="truncate text-gray-600 dark:text-gray-300" :title="item.product" x-text="item.product"></span>
                                <span class="shrink-0 tabular-nums text-gray-500"
                                      x-text="number(item.remaining) + (number(item.remaining) !== number(item.quantity) ? ' de ' + number(item.quantity) : '') + ' ' + shortUnit(item.unit)"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </section>
        </template>

        {{-- Lo que aún no tiene solicitud --}}
        <div x-show="report.eligible && report.classification !== 'without_request' && hasRemaining && (!viaOdoo || showRequests)" class="space-y-4">
            <div x-show="pendingLines.length && report.references.length" class="rounded-2xl border border-dashed border-gray-300 p-4 dark:border-gray-700">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Sin solicitud todavía</p>
                <ul class="mt-2 space-y-1">
                    <template x-for="line in pendingLines" :key="line.id">
                        <li class="flex justify-between gap-3 text-sm">
                            <span class="truncate text-gray-700 dark:text-gray-300" x-text="line.description"></span>
                            <span class="shrink-0 tabular-nums text-gray-500" x-text="number(line.remaining) + ' ' + shortUnit(line.unit)"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="flex items-end justify-between gap-3 flex-wrap">
                <div>
                    <h4 class="text-sm font-bold text-gray-800 dark:text-gray-100">Sugerencias</h4>
                    <p class="text-xs text-gray-500 mt-0.5">Lo que calza sin dudas ya se vinculó solo. Revisa estas y vincula con un clic.</p>
                </div>
                <form @submit.prevent="search()" class="flex gap-2 w-full sm:w-auto">
                    <label class="sr-only" for="dte-reference-search">Buscar solicitud por folio o motivo</label>
                    <input id="dte-reference-search" type="search" x-model="query" maxlength="100" placeholder="Buscar SC o motivo…"
                           class="min-w-0 flex-1 sm:w-56 rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-700">
                    <button class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800" :disabled="busy">Buscar</button>
                </form>
            </div>

            <p x-show="searched && !candidates.length" class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-800/50">No hay solicitudes que calcen. Busca una por su folio, o marca la compra como directa más abajo.</p>

            <div x-show="candidates.length" class="rounded-2xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800 max-h-[28rem] overflow-y-auto">
                <template x-for="candidate in candidates" :key="candidate.item_id">
                    <div class="flex items-start gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a :href="candidate.request_url" class="text-xs font-bold text-indigo-600 dark:text-indigo-300" x-text="candidate.folio"></a>
                                <span class="text-xs text-gray-400" x-text="candidate.date + ' · ' + candidate.status"></span>
                                <span x-show="candidate.supplier_known" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">Cotizó este proveedor</span>
                            </div>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100" x-text="candidate.product"></p>
                            <p x-show="candidate.line_id" class="mt-0.5 text-xs text-gray-500 truncate">
                                <span class="text-gray-400">Factura:</span> <span x-text="lineDescription(candidate.line_id)"></span>
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500" x-text="'Pendiente en la solicitud: ' + number(candidate.remaining) + ' ' + shortUnit(candidate.unit)"></p>
                            <p x-show="candidate.measures" class="mt-0.5 text-xs font-semibold text-rose-600 dark:text-rose-400" x-text="candidate.measures && measuresText(candidate.measures)"></p>
                        </div>
                        <div class="shrink-0 flex flex-col items-end gap-1.5">
                            <button type="button" x-show="candidate.quick_quantity && !candidate.measures" @click="quickLink(candidate)"
                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700" :disabled="busy"
                                    x-text="'Vincular ' + number(candidate.quick_quantity)"></button>
                            <button type="button" @click="choose(candidate); $nextTick(() => $refs.linkForm.scrollIntoView({behavior: 'smooth', block: 'center'}))"
                                    class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-300" :disabled="busy"
                                    x-text="candidate.quick_quantity && !candidate.measures ? 'Otra cantidad' : 'Revisar'"></button>
                        </div>
                    </div>
                </template>
            </div>

            <form x-show="selected" x-ref="linkForm" @submit.prevent="save()" class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-4 space-y-4 dark:border-indigo-800 dark:bg-indigo-950/20">
                <div>
                    <p class="text-xs font-bold text-indigo-600 dark:text-indigo-300" x-text="selected?.folio"></p>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100" x-text="selected?.product"></h4>
                    <p x-show="selected?.line_measures?.[form.lineId]" class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400"
                       x-text="selected?.line_measures?.[form.lineId] && (measuresText(selected.line_measures[form.lineId]) + '. Vincúlala sólo si es lo que se pidió.')"></p>
                </div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Línea de esta factura
                    <select x-model="form.lineId" @change="changeLine()" required class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-700">
                        <option value="">Selecciona una línea…</option>
                        <template x-for="line in report.lines.filter(l => Number(l.remaining) > 0)" :key="line.id">
                            <option :value="String(line.id)" x-text="line.description + ' · ' + number(line.remaining) + ' ' + (line.unit || '')"></option>
                        </template>
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">En la factura <span class="font-normal text-gray-400" x-text="shortUnit(currentLine?.unit)"></span>
                        <input type="number" x-model="form.invoiceQuantity" min="0.000001" step="0.000001" :max="currentLine?.remaining" required class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-700">
                    </label>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Equivale en la solicitud <span class="font-normal text-gray-400" x-text="shortUnit(selected?.unit)"></span>
                        <input type="number" x-model="form.requestQuantity" min="0.000001" step="0.000001" :max="selected?.remaining" required class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-700">
                    </label>
                </div>
                <div x-show="needsReview" class="space-y-3 rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30">
                    <p class="text-xs text-amber-800 dark:text-amber-200">Las unidades o cantidades no son iguales (cajas, packs, otra unidad). Explica la equivalencia; el sistema no la supone.</p>
                    <textarea x-model="form.note" maxlength="1500" :required="needsReview" :minlength="needsReview ? 10 : 0" rows="2" placeholder="Ej.: 18 sprays equivalen a 1,5 cajas de 12"
                              class="w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-700"></textarea>
                    <label class="flex gap-2 items-start text-xs text-amber-900 dark:text-amber-200">
                        <input type="checkbox" x-model="form.acknowledge" :required="needsReview" class="mt-0.5 rounded border-gray-300">
                        <span>Confirmo que las cantidades indicadas son equivalentes.</span>
                    </label>
                </div>
                <div class="flex items-center gap-3">
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" :disabled="busy" x-text="busy ? 'Guardando…' : 'Vincular'"></button>
                    <button type="button" @click="selected = null" class="text-sm text-gray-500">Cancelar</button>
                </div>
            </form>
        </div>

        {{-- Compra directa --}}
        <details x-show="report.eligible && !report.references.length && !viaOdoo" :open="report.classification === 'without_request'"
                 class="group rounded-2xl border border-gray-200 dark:border-gray-700">
            <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200">
                <span x-text="report.classification === 'without_request' ? 'Compra directa, sin solicitud' : '¿Se compró sin solicitud?'"></span>
                <svg class="h-4 w-4 text-gray-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <form @submit.prevent="classify(report.classification === 'without_request' ? 'unidentified' : 'without_request')" class="space-y-3 border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                <p x-show="report.decision_note" x-text="report.decision_note" class="text-xs text-gray-500"></p>
                <p x-show="report.classification !== 'without_request'" class="text-xs text-gray-500">Que no aparezca una solicitud no prueba que no la hubo. Márcala sólo si fue una compra directa.</p>
                <div class="flex gap-2 flex-wrap">
                    <input type="text" x-model="classificationNote" minlength="5" maxlength="1500" required placeholder="Motivo"
                           class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-700">
                    <button class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800" :disabled="busy"
                            x-text="report.classification === 'without_request' ? 'Volver a revisar' : 'Marcar compra directa'"></button>
                </div>
            </form>
        </details>

        <details x-show="report.history.length" class="text-xs text-gray-500">
            <summary class="cursor-pointer font-semibold">Historial (últimas 15)</summary>
            <ul class="mt-3 space-y-1.5 border-l-2 border-gray-100 pl-3 dark:border-gray-800">
                <template x-for="(event, index) in report.history" :key="index">
                    <li><span class="text-gray-400" x-text="date(event.created_at)"></span> · <span class="font-semibold" x-text="event.actor_name"></span> · <span x-text="historyLabels[event.event] || event.event"></span> <span x-text="event.details.folio || ''"></span> <span class="text-gray-400" x-text="event.details.note"></span></li>
                </template>
            </ul>
        </details>
    </div>
    </template>
</div>
@else
<div class="p-6 text-sm text-gray-500">Administración puede consultar y confirmar las solicitudes relacionadas con esta factura.</div>
@endif
