@if(auth()->user()->isAdmin())
<div class="border-t border-gray-100 p-4 sm:p-6 space-y-4 dark:border-gray-800"
     x-data="dteOdooOrders(@js([
        'report' => route('gmail.dtes.odoo_orders.show', $document->id),
        'candidates' => route('gmail.dtes.odoo_orders.candidates', $document->id),
        'store' => route('gmail.dtes.odoo_orders.store', $document->id),
     ]))"
     x-init="$watch('tab', value => { if (value === 'cruzadas' && !report && !loading) load() }); if (tab === 'cruzadas') load()">

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Orden de compra en Odoo</h3>
            <p class="text-xs text-gray-500 mt-0.5">Para las órdenes hechas directo en Odoo, sin solicitud. Sólo se registra aquí: Odoo no cambia.</p>
        </div>
        <span x-show="report" x-cloak class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold"
              :class="report?.links.length ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
              x-text="report?.links.length ? 'Orden vinculada' : 'Sin orden'"></span>
    </div>

    <p x-show="loading && !report" role="status" class="text-sm text-gray-500">Buscando órdenes…</p>
    <p x-show="error" x-cloak role="alert" x-text="error" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"></p>
    <p x-show="message" x-cloak role="status" x-text="message" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"></p>

    <template x-if="report">
    <div class="space-y-4">
        <p x-show="!report.eligible" class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-800/50">Disponible para facturas vigentes.</p>

        {{-- Vinculadas --}}
        <template x-for="link in report.links" :key="link.id">
            <div class="rounded-2xl border border-gray-200 px-4 py-3 dark:border-gray-700">
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a :href="link.order?.url || '#'" target="_blank" rel="noopener" class="font-mono text-sm font-bold text-indigo-600 hover:underline dark:text-indigo-300" x-text="link.name"></a>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                  :class="link.source === 'manual' ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'"
                                  :title="link.note" x-text="link.source === 'manual' ? 'Manual · ' + link.actor : link.source_label"></span>
                            <span x-show="link.order" class="text-xs text-gray-400" x-text="link.order?.state"></span>
                        </div>
                        <p class="mt-0.5 truncate text-sm text-gray-700 dark:text-gray-200" x-text="link.order?.partner || 'La orden ya no está en la pestaña Odoo'"></p>
                        <template x-for="note in link.credit_notes" :key="note.folio">
                            <p class="mt-0.5 text-xs font-semibold" :class="note.annuls ? 'text-rose-600 dark:text-rose-400' : 'text-amber-700 dark:text-amber-400'"
                               x-text="note.annuls ? 'Anulada por la nota de crédito N° ' + note.folio + ': no cuenta como facturado' : 'Nota de crédito N° ' + note.folio + ': ' + money(-note.net) + ' descontados de lo facturado'"></p>
                        </template>
                        <p x-show="link.order" class="mt-0.5 text-xs text-gray-500"
                           x-text="(link.order?.date || '') + ' · creada por ' + (link.order?.created_by || '—')"></p>
                        <p x-show="link.order && link.order.currency !== 'CLP'" class="mt-1 text-xs text-gray-500"
                           x-text="'Orden en ' + link.order?.currency + ' por ' + money(link.order?.amount, link.order?.currency) + ': no se compara con los pesos de la factura.'"></p>
                        <template x-if="link.order && link.order.currency === 'CLP'">
                            <div class="mt-2">
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-600 dark:text-gray-300" x-text="'Facturado ' + money(link.order.invoiced, link.order.currency) + ' de ' + money(link.order.amount, link.order.currency) + ' neto'"></span>
                                    <span class="font-semibold text-gray-500" x-text="percent(link.order.invoiced, link.order.amount)"></span>
                                </div>
                                <div class="mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                    <div class="h-full rounded-full" :class="link.order.invoiced > link.order.amount * 1.01 ? 'bg-rose-500' : 'bg-emerald-500'"
                                         :style="`width: ${Math.min(100, link.order.amount > 0 ? link.order.invoiced * 100 / link.order.amount : 0)}%`"></div>
                                </div>
                                <p x-show="link.order.invoiced > link.order.amount * 1.01" class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">Lo facturado supera la orden</p>
                            </div>
                        </template>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <span class="text-sm font-bold tabular-nums text-gray-900 dark:text-gray-100" x-text="money(link.amount)"></span>
                        <button type="button" @click="revoking = link.id; revokeNote = ''" title="Desvincular" aria-label="Desvincular"
                                class="rounded-md p-1 text-gray-300 hover:bg-rose-50 hover:text-rose-600 dark:text-gray-600 dark:hover:bg-rose-950/40" :disabled="busy">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <form x-show="revoking === link.id" @submit.prevent="revoke()" class="mt-3 flex gap-2 flex-wrap">
                    <input type="text" x-model="revokeNote" minlength="5" maxlength="1500" required placeholder="Motivo de la desvinculación"
                           class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-700">
                    <button class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white" :disabled="busy">Desvincular</button>
                    <button type="button" @click="revoking = null" class="px-2 text-sm text-gray-500">Cancelar</button>
                </form>
            </div>
        </template>

        {{-- Sugerencias y búsqueda --}}
        <button type="button" x-show="report.eligible && report.links.length && !searching" @click="searching = true"
                class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-300">¿Paga también otra orden? Buscar</button>
        <div x-show="report.eligible && (!report.links.length || searching)" class="space-y-3">
            <div class="flex items-end justify-between gap-3 flex-wrap">
                <p class="text-xs text-gray-500" x-text="report.links.length ? 'Busca la otra orden por su número o proveedor.' : 'Órdenes del mismo proveedor que calzan con esta factura.'"></p>
                <form @submit.prevent="search()" class="flex gap-2 w-full sm:w-auto">
                    <label class="sr-only" for="dte-odoo-search">Buscar orden por número o proveedor</label>
                    <input id="dte-odoo-search" type="search" x-model="query" maxlength="100" placeholder="Buscar P00… o proveedor"
                           class="min-w-0 flex-1 sm:w-56 rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-700">
                    <button class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800" :disabled="busy">Buscar</button>
                </form>
            </div>
            <p x-show="!candidates.length && (searched || !report.links.length)" class="rounded-xl bg-gray-50 p-3 text-sm text-gray-500 dark:bg-gray-800/50"
               x-text="searched ? 'Ninguna orden calza con esa búsqueda.' : 'Ninguna orden de este proveedor hecha en Odoo calza con esta factura. Búscala por su número si la conoces.'"></p>
            <div x-show="candidates.length" class="rounded-2xl border border-gray-200 divide-y divide-gray-100 dark:border-gray-700 dark:divide-gray-800">
                <template x-for="candidate in candidates" :key="candidate.odoo_id">
                    <div class="flex items-start gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a :href="candidate.url || '#'" target="_blank" rel="noopener" class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-300" x-text="candidate.name"></a>
                                <span class="text-xs text-gray-400" x-text="(candidate.date || '') + ' · ' + candidate.state"></span>
                            </div>
                            <p class="mt-0.5 truncate text-sm text-gray-800 dark:text-gray-100" x-text="candidate.partner"></p>
                            <p class="mt-0.5 text-xs" :class="candidate.referenced || (candidate.coverage >= 0.999 && candidate.same_amount) ? 'font-semibold text-emerald-700 dark:text-emerald-400' : 'text-gray-500'" x-text="reason(candidate)"></p>
                            <p class="mt-0.5 text-xs text-gray-400" x-text="(candidate.remaining !== null
                                ? 'Pendiente ' + money(candidate.remaining, candidate.currency) + ' de ' + money(candidate.amount, candidate.currency)
                                : 'Orden en ' + candidate.currency + ' por ' + money(candidate.amount, candidate.currency) + (candidate.invoices ? ' · ya tiene ' + candidate.invoices + (candidate.invoices === 1 ? ' factura' : ' facturas') : ''))
                                + ' · creada por ' + (candidate.created_by || '—')"></p>
                        </div>
                        <button type="button" @click="link(candidate)" class="shrink-0 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700" :disabled="busy"
                                :title="'Se vinculan ' + money(candidate.quick_amount) + ' de esta factura'"
                                x-text="candidate.partial ? 'Vincular ' + money(candidate.quick_amount) : 'Vincular'"></button>
                    </div>
                </template>
            </div>
        </div>
    </div>
    </template>
</div>
@endif
