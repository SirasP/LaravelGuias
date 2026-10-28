@if(auth()->user()->isAdmin())
<div id="referencias-cruzadas" class="p-4 sm:p-6 space-y-5"
     x-data="dtePurchaseReferences(@js([
        'report' => route('gmail.dtes.purchase_references.show', $document->id),
        'candidates' => route('gmail.dtes.purchase_references.candidates', $document->id),
        'store' => route('gmail.dtes.purchase_references.store', $document->id),
        'classify' => route('gmail.dtes.purchase_references.classify', $document->id),
     ]))"
     x-init="$watch('tab', value => { if (value === 'cruzadas' && !report && !loading) load() }); if (tab === 'cruzadas') load()">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h3 class="font-bold text-gray-900 dark:text-gray-100">Solicitud de compra y factura</h3>
            <p class="text-xs text-gray-500 mt-1">Relaciona las partidas que se compraron a este proveedor. Una factura puede cubrir varias solicitudes.</p>
        </div>
        <span class="chip bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300" x-show="report" x-text="labels[report?.classification]"></span>
    </div>
    <p x-show="loading" role="status" class="text-sm text-gray-500">Cargando relaciones…</p>
    <div x-show="error" role="alert" class="rounded-xl bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950/40 dark:text-rose-200">
        <span x-text="error"></span>
        <button type="button" @click="load()" class="underline ml-2" :disabled="busy">Actualizar</button>
    </div>
    <p x-show="message" role="status" x-text="message" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"></p>
    <template x-if="report">
    <div class="space-y-5">
        <p x-show="!report.eligible" class="text-sm text-gray-500">La relación con solicitudes está disponible para facturas vigentes. Este documento es de otro tipo o está anulado/rechazado.</p>
        <div x-show="report.decision_stale" class="rounded-xl bg-amber-50 text-amber-900 p-3 text-sm">La factura cambió desde que se marcó como compra sin solicitud. Revisa nuevamente la clasificación.</div>

        <div class="space-y-3" x-show="report.references.length">
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-200">Partidas vinculadas</h4>
            <template x-for="reference in report.references" :key="reference.id">
                <article class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-2">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <a :href="reference.request_url || '#'" class="font-bold text-sm text-indigo-600 dark:text-indigo-300" x-text="reference.folio"></a>
                        <span class="text-xs text-gray-500" x-text="reference.source === 'odoo' ? 'Origen verificado en Odoo' : (reference.source === 'auto' ? 'Vinculada automáticamente' : 'Confirmada por ' + reference.actor)"></span>
                    </div>
                    <p class="text-sm text-gray-800 dark:text-gray-100" x-text="reference.invoice_product"></p>
                    <p class="text-xs text-gray-500">
                        Factura: <strong x-text="number(reference.invoice_quantity) + ' ' + (reference.invoice_unit || 'sin unidad')"></strong>
                        <span class="mx-1">→</span>
                        Solicitud: <strong x-text="number(reference.request_quantity) + ' ' + reference.request_unit"></strong>
                        <span x-text="' · ' + reference.request_product"></span>
                    </p>
                    <p class="text-xs text-gray-500" x-text="'Solicitado: ' + number(reference.requested_quantity) + ' · Pendiente de vincular entre todas las facturas: ' + number(reference.remaining_request)"></p>
                    <p x-show="reference.stale" class="text-sm font-semibold text-amber-700 dark:text-amber-300">Revisar: cambió o dejó de estar disponible una de las partidas. Esta relación conserva su cantidad hasta que se revise.</p>
                    <p class="text-xs text-gray-500" x-text="reference.note"></p>
                    <button type="button" @click="revoking = reference.id; revokeNote = ''" class="text-xs text-rose-600 underline" :disabled="busy">Desvincular esta partida</button>
                </article>
            </template>
        </div>

        <form x-show="revoking" @submit.prevent="revoke()" class="rounded-xl border border-rose-200 p-4 space-y-3">
            <label class="block text-sm font-semibold">Motivo de la desvinculación
                <input type="text" x-model="revokeNote" minlength="5" maxlength="1500" required class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 text-sm">
            </label>
            <div class="flex gap-3">
                <button class="rounded-lg bg-rose-600 text-white px-3 py-2 text-sm" :disabled="busy">Desvincular</button>
                <button type="button" @click="revoking = null" class="text-sm text-gray-500">Cancelar</button>
            </div>
        </form>

        <div x-show="report.eligible && report.classification !== 'without_request' && hasRemaining" class="space-y-4">
            <form @submit.prevent="search()" class="flex gap-2">
                <label class="sr-only" for="dte-reference-search">Buscar solicitud por folio o motivo</label>
                <input id="dte-reference-search" type="search" x-model="query" maxlength="100" placeholder="Buscar solicitud por folio o motivo…" class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 text-sm">
                <button class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold" :disabled="busy">Buscar</button>
            </form>
            <p class="text-xs text-gray-500">Se vinculan solas las partidas que calzan sin dudas: cotizadas por este proveedor, o con el mismo nombre, unidad y cantidad que lo solicitado. Las demás quedan como sugerencia: revisa y vincula con un clic.</p>
            <p x-show="searched && !candidates.length" class="text-sm text-gray-500">No se encontraron coincidencias con evidencia suficiente. Puedes buscar una solicitud por su folio o registrar una compra sin solicitud.</p>

            <div class="space-y-2 max-h-96 overflow-y-auto">
                <template x-for="candidate in candidates" :key="candidate.item_id">
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3 flex items-start gap-3 justify-between">
                        <div class="min-w-0">
                            <a :href="candidate.request_url" class="font-semibold text-sm text-indigo-600 dark:text-indigo-300" x-text="candidate.folio"></a>
                            <span class="text-xs text-gray-400" x-text="' · ' + candidate.status + ' · ' + candidate.date"></span>
                            <p class="text-sm text-gray-800 dark:text-gray-100" x-text="candidate.product"></p>
                            <p class="text-xs text-gray-500" x-text="candidate.reason"></p>
                            <p class="text-xs text-gray-500 mt-1" x-text="'Disponible para vincular: ' + number(candidate.remaining) + ' ' + candidate.unit"></p>
                            <p class="text-xs mt-1" :class="candidate.supplier_known ? 'text-gray-500' : 'text-amber-700 dark:text-amber-300'" x-text="candidate.explanation"></p>
                            <p x-show="candidate.quick_quantity" class="text-xs text-gray-500 mt-1" x-text="'Con la partida: ' + (report.lines.find(l => l.id === candidate.line_id)?.description || '') + ' · ' + number(candidate.quick_quantity) + ' ' + candidate.unit"></p>
                        </div>
                        <div class="shrink-0 flex flex-col gap-2">
                            <button type="button" x-show="candidate.quick_quantity" @click="quickLink(candidate)" class="rounded-lg bg-indigo-600 text-white px-3 py-2 text-xs font-semibold" :disabled="busy">Vincular</button>
                            <button type="button" @click="choose(candidate); $nextTick(() => $refs.linkForm.scrollIntoView({behavior: 'smooth', block: 'center'}))" class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-600 dark:text-indigo-300" :disabled="busy" x-text="candidate.quick_quantity ? 'Otra cantidad' : 'Revisar'"></button>
                        </div>
                    </div>
                </template>
            </div>

            <form x-show="selected" x-ref="linkForm" @submit.prevent="save()" class="rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/40 dark:bg-indigo-950/20 p-4 space-y-4">
                <h4 class="font-bold text-sm" x-text="'Confirmar relación con ' + (selected?.folio || '')"></h4>
                <p class="text-sm" x-text="selected?.product"></p>
                <label class="block text-xs font-semibold">Partida de esta factura
                    <select x-model="form.lineId" @change="changeLine()" required class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 text-sm">
                        <option value="">Selecciona una partida…</option>
                        <template x-for="line in report.lines.filter(l => Number(l.remaining) > 0)" :key="line.id">
                            <option :value="String(line.id)" x-text="line.description + ' · ' + number(line.remaining) + ' ' + (line.unit || '')"></option>
                        </template>
                    </select>
                </label>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="block text-xs font-semibold">Cantidad de la factura <span x-text="currentLine?.unit"></span>
                        <input type="number" x-model="form.invoiceQuantity" min="0.000001" step="0.000001" :max="currentLine?.remaining" required class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 text-sm">
                    </label>
                    <label class="block text-xs font-semibold">Equivale en la solicitud a <span x-text="selected?.unit"></span>
                        <input type="number" x-model="form.requestQuantity" min="0.000001" step="0.000001" :max="selected?.remaining" required class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 text-sm">
                    </label>
                </div>
                <p class="text-xs text-gray-500">Indica las cantidades de esta relación. Para cajas, paquetes o unidades distintas, confirma la equivalencia; el sistema no la supone.</p>
                <label x-show="needsReview" class="block text-xs font-semibold">Motivo o antecedente de la relación
                    <textarea x-model="form.note" maxlength="1500" :required="needsReview" :minlength="needsReview ? 10 : 0" rows="2" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 text-sm"></textarea>
                </label>
                <label x-show="needsReview" class="flex gap-2 items-start text-sm text-amber-800 dark:text-amber-200">
                    <input type="checkbox" x-model="form.acknowledge" :required="needsReview" class="mt-1 rounded border-gray-300">
                    <span>Confirmo que las cantidades indicadas son equivalentes.</span>
                </label>
                <div class="flex gap-3">
                    <button class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold" :disabled="busy" x-text="busy ? 'Guardando…' : 'Confirmar vínculo'"></button>
                    <button type="button" @click="selected = null" class="text-sm text-gray-500">Cancelar</button>
                </div>
            </form>
        </div>

        <form x-show="report.eligible && !report.references.length" @submit.prevent="classify(report.classification === 'without_request' ? 'unidentified' : 'without_request')" class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4 space-y-3">
            <h4 class="font-semibold text-sm" x-text="report.classification === 'without_request' ? 'Compra directa registrada' : '¿La compra se hizo sin solicitud?'"></h4>
            <p x-show="report.decision_note" x-text="report.decision_note" class="text-xs text-gray-500"></p>
            <p class="text-xs text-gray-500">No encontrar una coincidencia deja el documento «Sin vínculo identificado». Marcarlo sin solicitud requiere confirmar que fue una compra directa.</p>
            <label class="block text-xs font-semibold">Motivo
                <input type="text" x-model="classificationNote" minlength="5" maxlength="1500" required class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 text-sm">
            </label>
            <button class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm font-semibold" :disabled="busy" x-text="report.classification === 'without_request' ? 'Volver a revisar' : 'Confirmar compra sin solicitud'"></button>
        </form>

        <details x-show="report.history.length" class="text-xs text-gray-500">
            <summary class="cursor-pointer font-semibold">Historial de decisiones (últimas 15)</summary>
            <ul class="mt-3 space-y-2">
                <template x-for="(event, index) in report.history" :key="index">
                    <li><span x-text="date(event.created_at) + ' · ' + event.actor_name + ' · '"></span><span x-text="event.details.folio || ''"></span> <span x-text="event.details.note"></span></li>
                </template>
            </ul>
        </details>
    </div>
    </template>
</div>
@else
<div class="p-6 text-sm text-gray-500">Administración puede consultar y confirmar las solicitudes relacionadas con esta factura.</div>
@endif
