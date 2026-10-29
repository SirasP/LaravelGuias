<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a3 3 0 006 0M9 5a3 3 0 016 0m-7 7h8m-8 4h5" />
                </svg>
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Solicitudes de compra</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Órdenes hechas directo en Odoo</p>
            </div>
        </div>
    </x-slot>

    @php
        $money = function (float $value, ?string $currency): string {
            return $currency && $currency !== 'CLP'
                ? $currency.' '.number_format($value, 2, ',', '.')
                : '$'.number_format($value, 0, ',', '.');
        };
        $qty = fn (float $value): string => rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',');
        $stateClasses = fn (string $state): string => match (true) {
            in_array($state, \App\Models\OdooPurchaseOrder::QUOTATION_STATES, true) => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
            in_array($state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true) => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
            default => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        };
        $tabs = [
            'cotizaciones' => ['Cotizaciones', 'aún sin confirmar'],
            'confirmadas' => ['Confirmadas', 'orden de compra'],
            'canceladas' => ['Canceladas', ''],
            'todas' => ['Todas', ''],
        ];
    @endphp

    <div class="mx-auto max-w-full space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav')

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="font-extrabold text-slate-900 dark:text-white">Hechas en Odoo, sin solicitud</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Cotizaciones y órdenes que alguien creó directo en Odoo. Las que salen de una solicitud no aparecen aquí.
                    </p>
                </div>
                <p class="text-xs text-slate-400">
                    @if($syncedAt)
                        Actualizado {{ \Illuminate\Support\Carbon::parse($syncedAt)->format('d-m-Y H:i') }} · cada noche a las 02:10
                    @else
                        Todavía no se ha sincronizado
                    @endif
                </p>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach($tabs as $key => [$label, $hint])
                    <a href="{{ route('purchase_requests.odoo', array_filter(['estado' => $key, 'q' => $search, 'creada_por' => $creator])) }}"
                       @if($filter === $key) aria-current="page" @endif
                       class="rounded-xl border px-3 py-2.5 transition {{ $filter === $key
                            ? 'border-blue-600 bg-blue-50 dark:border-blue-500 dark:bg-blue-950/40'
                            : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800' }}">
                        <p class="text-[11px] font-bold uppercase tracking-wider {{ $filter === $key ? 'text-blue-700 dark:text-blue-300' : 'text-slate-400' }}">{{ $label }}</p>
                        <p class="text-xl font-black tabular-nums text-slate-900 dark:text-white">{{ $counts[$key] }}</p>
                        @if($hint)<p class="text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>@endif
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('purchase_requests.odoo') }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
                <input type="hidden" name="estado" value="{{ $filter }}">
                <label class="sr-only" for="odoo-search">Buscar por número o proveedor</label>
                <input id="odoo-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Buscar P00… o proveedor"
                       class="min-w-0 flex-1 rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                <label class="sr-only" for="odoo-creator">Creada por</label>
                <select id="odoo-creator" name="creada_por" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Cualquiera la creó</option>
                    @foreach($creators as $name)
                        <option value="{{ $name }}" @selected($creator === $name)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900">Filtrar</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($items as $item)
                    @if(count($item['orders']) > 1)
                        @php
                            $groupOrders = $item['orders'];
                            $groupStates = $groupOrders->countBy(fn ($o) => in_array($o->state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true) ? 'confirmadas' : ($o->state === 'cancel' ? 'canceladas' : 'cotizaciones'));
                            $firstLine = $groupOrders->flatMap->lines->reject->isNote()->first();
                        @endphp
                        {{-- Una misma compra cotizada a varios proveedores: Odoo las une en un grupo. --}}
                        <li class="bg-slate-50/60 dark:bg-slate-950/30">
                            <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-3 sm:px-5">
                                <p class="text-xs font-bold text-slate-700 dark:text-slate-200">
                                    Cotizada a {{ $groupOrders->pluck('partner_odoo_id')->unique()->count() }} proveedores
                                    @if($firstLine) <span class="font-normal text-slate-500">· {{ \Illuminate\Support\Str::limit($firstLine->name, 60) }}</span> @endif
                                </p>
                                <p class="text-[11px] font-semibold">
                                    @foreach(['confirmadas' => ['confirmada', 'text-emerald-700 dark:text-emerald-400'], 'cotizaciones' => ['cotización', 'text-amber-700 dark:text-amber-400'], 'canceladas' => ['cancelada', 'text-slate-400']] as $state => [$one, $color])
                                        @if($groupStates[$state] ?? 0)<span class="ml-2 {{ $color }}">{{ $groupStates[$state] }} {{ $groupStates[$state] === 1 ? $one : $state }}</span>@endif
                                    @endforeach
                                </p>
                            </div>
                            <ul class="m-2 overflow-hidden rounded-xl border border-slate-200 bg-white divide-y divide-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:divide-slate-800 sm:m-3">
                                @foreach($groupOrders as $order)
                                    <li class="{{ $order->state === 'cancel' ? 'opacity-60' : '' }}">@include('purchase_requests.partials.odoo-order-row')</li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        @php($order = $item['orders']->first())
                        <li>@include('purchase_requests.partials.odoo-order-row')</li>
                    @endif
                @empty
                    <li class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ $syncedAt ? 'No hay órdenes en esta pestaña.' : 'Aún no se han traído las órdenes de Odoo.' }}
                    </li>
                @endforelse
            </ul>

            @if($pages->hasPages())
                <div class="border-t border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">{{ $pages->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
