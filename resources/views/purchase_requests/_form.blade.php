@php
    $isEditing = filled($purchaseRequest?->getKey());

    // Con el asistente activo la pantalla tiene dos modos; sin él, el
    // formulario es lo único que hay y no debe depender de ninguna pestaña.
    // Editando tampoco: ya hay partidas escritas y rehacerlas desde texto
    // sería pisar el trabajo hecho.
    $modoIa = (bool) config('purchase_requests.reader.enabled') && ! $isEditing;
    $soloManual = $modoIa ? 'x-show="modo === \'manual\'"' : '';
    $requestItems = old('items', $purchaseRequest?->items?->map(fn ($item) => [
        'product_service' => $item->product_service,
        'specification' => $item->specification,
        'quantity' => $item->quantity,
        'unit' => $item->unit,
        'quantity_note' => $item->quantity_note,
        'destination' => $item->destination,
    ])->values()->all() ?? []);
    $requestItems = count($requestItems) ? $requestItems : [[
        'product_service' => '', 'specification' => '', 'quantity' => '', 'unit' => 'Unidades', 'quantity_note' => '', 'destination' => '',
    ]];
    $suppliers = old('suggested_suppliers', $purchaseRequest?->suggested_suppliers ?? []);
    $suppliers = is_array($suppliers) ? array_values($suppliers) : [];
    $suppliers = array_pad(array_slice($suppliers, 0, 4), 4, '');
    $requestedFor = old('requested_for_name', old('requested_for', $purchaseRequest?->requested_for_name ?? $purchaseRequest?->requested_for ?? ''));
    $internalNotes = old('internal_notes', old('notes', $purchaseRequest?->internal_notes ?? $purchaseRequest?->notes ?? ''));

    // Puntos que el revisor marcó al devolver la solicitud.
    $correcciones = collect($purchaseRequest?->requested_corrections ?? []);
    $partidasMarcadas = $correcciones
        ->map(fn (string $p) => \App\Enums\PurchaseRequestCorrection::itemPosition($p))
        ->filter()
        ->values()
        ->all();

    // Devuelve el resaltado de un campo señalado para corrección. Se acompaña
    // siempre de una etiqueta escrita: el color por sí solo no comunica.
    $marcado = static function (string $campo) use ($correcciones): string {
        return $correcciones->contains($campo)
            ? 'rounded-xl bg-amber-50 p-2 ring-2 ring-amber-400 dark:bg-amber-950/30 dark:ring-amber-600'
            : '';
    };
    $estaMarcado = static fn (string $campo): bool => $correcciones->contains($campo);
@endphp

@if($correcciones->isNotEmpty())
    <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-900/60 dark:bg-amber-950/30">
        <p class="text-sm font-extrabold text-amber-900 dark:text-amber-100">Compras pidió corregir estos puntos</p>
        <ul class="mt-2 flex flex-wrap gap-1.5">
            @foreach($correcciones as $punto)
                <li class="rounded-full bg-amber-200 px-2.5 py-1 text-xs font-bold text-amber-900 dark:bg-amber-900/60 dark:text-amber-100">
                    {{ \App\Enums\PurchaseRequestCorrection::labelFor($punto) }}
                </li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-amber-800 dark:text-amber-200">Aparecen resaltados más abajo. Al reenviar, las marcas se limpian.</p>
    </div>
@endif

{{-- Catálogos sugeridos. Son `datalist`: guían hacia el valor normalizado
     sin bloquear una unidad nueva que todavía no está en el catálogo. --}}
<datalist id="units-catalog">
    @foreach (($units ?? collect()) as $unit)
        <option value="{{ $unit->name }}"></option>
    @endforeach
</datalist>
<datalist id="cost-centers-catalog">
    @foreach (($costCenters ?? collect()) as $costCenter)
        <option value="{{ $costCenter->name }}"></option>
    @endforeach
</datalist>
<datalist id="locations-catalog">
    @foreach (($locations ?? collect()) as $location)
        <option value="{{ $location->name }}"></option>
    @endforeach
</datalist>

<form method="POST" enctype="multipart/form-data"
    action="{{ $isEditing ? route('purchase_requests.update', $purchaseRequest) : route('purchase_requests.store') }}"
    x-data="purchaseRequestForm(@js($requestItems), @js($partidasMarcadas))" class="space-y-5">
    @csrf
    @if($isEditing)
        @method('PUT')
    @endif

    {{-- Compatibility fields used by the request contract; the descriptive names remain visible in the UI. --}}
    <input type="hidden" name="requested_for" x-model="requestedFor">
    <input type="hidden" name="notes" x-model="internalNotes">

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200" role="alert">
            <p class="font-bold">Revisa los campos marcados antes de guardar.</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($modoIa)
        {{-- Escribir de corrido es más rápido que llenar una tabla. Lo que el
             asistente propone se agrega como partidas normales y editables:
             nada se guarda hasta que la persona envíe. --}}
        <section x-show="modo === 'ia'" x-cloak
            class="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm dark:border-violet-900/60 dark:bg-slate-900"
            x-data="{ texto: '', cargando: false, error: '', avisos: [] }">
            <div class="border-b border-violet-100 px-4 py-4 dark:border-violet-900/60 sm:px-5">
                <h2 class="font-extrabold text-violet-900 dark:text-violet-100">Cuéntale qué necesitas</h2>
                <p class="mt-1 text-xs text-violet-800 dark:text-violet-300">
                    Escríbelo como se lo dirías a un compañero. Lo ordenamos en partidas y las revisas en Manual antes de enviar.
                </p>
            </div>

            <div class="space-y-3 p-4 sm:p-5">
                <label for="asistente-texto" class="sr-only">Escribe lo que necesitas</label>
                <textarea id="asistente-texto" x-model="texto" rows="5"
                    @keydown.ctrl.enter="$refs.armar.click()"
                    class="w-full rounded-xl border-violet-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-violet-900 dark:bg-slate-950 dark:text-white"
                    placeholder="Por ejemplo: pañuelos desechables 2, confort 2, cloro 5 litros"></textarea>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" x-ref="armar" :disabled="cargando || texto.trim().length < 3"
                        @click="
                                cargando = true; error = ''; avisos = [];
                                fetch('{{ route('purchase_requests.ingestions.draft') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({ text: texto })
                                })
                                .then(r => r.json())
                                .then(d => {
                                    if (!d.available) { error = d.error || 'El asistente no pudo ayudar esta vez.'; return; }
                                    // Se reemplaza sólo si las líneas actuales están vacías.
                                    const vacias = items.every(i => !i.product_service && !i.quantity);
                                    const nuevas = d.items.map(i => ({
                                        key: Date.now() + Math.random(),
                                        product_service: i.product_service ?? '',
                                        specification: i.specification ?? '',
                                        quantity: i.quantity ?? '',
                                        unit: i.unit ?? '',
                                        quantity_note: '', destination: ''
                                    }));
                                    items = vacias ? nuevas : items.concat(nuevas);
                                    avisos = d.warnings ?? [];
                                    if (d.reason && !document.getElementById('reason').value) {
                                        document.getElementById('reason').value = d.reason;
                                    }
                                    texto = '';
                                    modo = 'manual';
                                })
                                .catch(() => error = 'No se pudo contactar al asistente.')
                                .finally(() => cargando = false)
                        "
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-violet-600 px-5 text-sm font-extrabold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!cargando">Armar partidas</span>
                        <span x-show="cargando" x-cloak>Leyendo…</span>
                    </button>
                    <p class="text-xs text-slate-500 dark:text-slate-400">La primera lectura del día tarda unos segundos más.</p>
                </div>

                <template x-if="error">
                    <p class="text-xs font-bold text-rose-700 dark:text-rose-300" x-text="error"></p>
                </template>

                <template x-if="avisos.length">
                    <div class="rounded-xl bg-amber-50 px-3 py-2 dark:bg-amber-950/30">
                        <p class="text-xs font-bold text-amber-800 dark:text-amber-300">Revisa esto antes de enviar</p>
                        <ul class="mt-1 list-inside list-disc text-xs text-amber-800 dark:text-amber-300">
                            <template x-for="a in avisos" :key="a"><li x-text="a"></li></template>
                        </ul>
                    </div>
                </template>
            </div>
        </section>
    @endif

    <section {!! $soloManual !!} class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">
            <h2 class="font-extrabold text-slate-900 dark:text-white">1. Lo básico</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sólo tres datos. Todo lo demás es opcional y está más abajo.</p>
        </div>
        <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <div>
                @php
                    $departmentNames = ($departments ?? collect())->pluck('name');
                    $currentDepartment = old('department', $purchaseRequest?->department ?? ($defaults['department'] ?? null));
                    // Un área heredada que ya no está en el catálogo no debe
                    // perderse al editar: se trata como valor libre.
                    $departmentIsCustom = filled($currentDepartment) && ! $departmentNames->contains($currentDepartment);
                @endphp
                <div x-data="{ custom: @js($departmentIsCustom), value: @js($currentDepartment) }" class="{{ $marcado('department') }}">
                    <label for="department" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Área o departamento <span class="text-rose-500">*</span></label>
                    <select id="department" x-show="!custom" x-model="value"
                        @change="if ($event.target.value === '__otra__') { custom = true; value = ''; $nextTick(() => $refs.customDepartment?.focus()); }"
                        :name="custom ? null : 'department'" :required="!custom"
                        class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">Selecciona un área…</option>
                        @foreach ($departmentNames as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                        <option value="__otra__">Otra (especificar)…</option>
                    </select>
                    <div x-show="custom" x-cloak class="mt-1.5 flex gap-2">
                        <input x-ref="customDepartment" :name="custom ? 'department' : null" x-model="value" :required="custom" autocomplete="organization"
                            class="min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Escribe el área">
                        <button type="button" @click="custom = false; value = ''"
                            class="min-h-11 shrink-0 rounded-xl border border-slate-300 px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">Ver lista</button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Elegir del listado evita que se dupliquen variantes del mismo área.</p>
                    @if($estaMarcado('department'))
                        <p class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-amber-700 dark:text-amber-300">
                            <span aria-hidden="true">↺</span> Compras pidió corregir esto
                        </p>
                    @endif
                    @error('department') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="{{ $marcado('required_date') }}">
                <label for="required_date" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Fecha requerida
                    @if($estaMarcado('required_date'))
                        <p class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-amber-700 dark:text-amber-300">
                            <span aria-hidden="true">↺</span> Compras pidió corregir esto
                        </p>
                    @endif <span class="text-rose-500">*</span></label>
                <input id="required_date" type="date" name="required_date" value="{{ old('required_date', $purchaseRequest?->required_date?->format('Y-m-d') ?? $purchaseRequest?->required_date ?? ($defaults['required_date'] ?? null)) }}" required
                    class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                @error('required_date') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="reason" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Motivo de la compra <span class="text-rose-500">*</span></label>
                <textarea id="reason" name="reason" rows="3" required
                    class="mt-1.5 w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Explica para qué se necesita.">{{ old('reason', $purchaseRequest?->reason) }}</textarea>
                @error('reason') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section {!! $soloManual !!} class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">
            <div>
                <h2 class="font-extrabold text-slate-900 dark:text-white">2. ¿Qué necesitas?</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Usa coma o punto para decimales. Puedes repetir productos si tienen distinto destino.</p>
            </div>
            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300" x-text="items.length + (items.length === 1 ? ' partida' : ' partidas')"></span>
        </div>
        <div class="space-y-3 p-4 sm:p-5">
            <template x-for="(item, index) in items" :key="item.key">
                <article class="rounded-xl border p-4"
                    :class="isFlagged(index) ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-400 dark:border-amber-700 dark:bg-amber-950/30' : 'border-slate-200 dark:border-slate-700'">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-extrabold text-slate-800 dark:text-slate-100"><span x-text="index + 1"></span>. Producto o servicio</p>
                            <template x-if="isFlagged(index)">
                                <p class="mt-0.5 inline-flex items-center gap-1 text-xs font-bold text-amber-700 dark:text-amber-300">
                                    <span aria-hidden="true">↺</span> Compras pidió corregir esta partida
                                </p>
                            </template>
                        </div>
                        <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="inline-flex min-h-10 items-center rounded-lg px-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/30">Quitar</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Producto o servicio <span class="text-rose-500">*</span></label>
                            <input :name="`items[${index}][product_service]`" x-model="item.product_service" required
                                class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Ej. Tubo PVC sanitario">
                            <template x-if="fieldError(`items.${index}.product_service`)"><p class="mt-1 text-xs font-medium text-rose-600" x-text="fieldError(`items.${index}.product_service`)"></p></template>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Cantidad <span class="text-rose-500">*</span></label>
                            <input :name="`items[${index}][quantity]`" x-model="item.quantity" inputmode="decimal" required
                                class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Ej. 1,5">
                            <template x-if="fieldError(`items.${index}.quantity`)"><p class="mt-1 text-xs font-medium text-rose-600" x-text="fieldError(`items.${index}.quantity`)"></p></template>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Unidad <span class="text-rose-500">*</span></label>
                            <input :name="`items[${index}][unit]`" x-model="item.unit" required list="units-catalog"
                                class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Ej. Metros, Unidades, Paquetes">
                            <template x-if="fieldError(`items.${index}.unit`)"><p class="mt-1 text-xs font-medium text-rose-600" x-text="fieldError(`items.${index}.unit`)"></p></template>
                        </div>
                        <div class="sm:col-span-2" x-data="{ detalle: !!(item.specification || item.quantity_note || item.destination) }">
                            <button type="button" @click="detalle = !detalle" :aria-expanded="detalle.toString()"
                                class="inline-flex min-h-11 items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                <span x-text="detalle ? '− Ocultar detalle' : '+ Agregar especificación, presentación o destino'"></span>
                            </button>
                            <div x-show="detalle" x-cloak class="mt-2 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Especificación</label>
                                <input :name="`items[${index}][specification]`" x-model="item.specification"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Ej. 75 mm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Nota de cantidad / presentación</label>
                                <input :name="`items[${index}][quantity_note]`" x-model="item.quantity_note"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Ej. paquete de 6">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Destino o uso específico</label>
                                <input :name="`items[${index}][destination]`" x-model="item.destination"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Ej. Casa de operarios">
                            </div>
                            </div>
                        </div>
                    </div>
                </article>
            </template>
            <button type="button" @click="addItem()" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-dashed border-blue-300 px-4 text-sm font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-950/30 sm:w-auto">
                <span class="text-lg leading-none">+</span> Agregar producto o servicio
            </button>
            @error('items') <p class="text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
        </div>
    </section>

    {{-- Todo lo opcional, plegado. Pedir algo no puede costar dieciséis campos
         en blanco: quien necesita el detalle lo abre; el resto ni lo ve. Se
         despliega solo si hay errores ahí dentro o correcciones marcadas. --}}
    <section {!! $soloManual !!} x-data="{ abierto: {{ $errors->hasAny(['requested_for_name', 'cost_center', 'priority', 'urgent_reason', 'delivery_location', 'internal_notes', 'suggested_suppliers', 'attachments']) || $correcciones->isNotEmpty() ? 'true' : 'false' }} }"
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">

        <button type="button" @click="abierto = !abierto" :aria-expanded="abierto.toString()"
            class="flex min-h-11 w-full items-center justify-between gap-3 px-4 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:px-5">
            <span class="min-w-0">
                <span class="block font-extrabold text-slate-900 dark:text-white">3. Más detalles</span>
                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                    Opcional: para quién es, urgencia, dónde entregar, proveedores y adjuntos.
                </span>
            </span>
            <span class="shrink-0 text-sm font-bold text-blue-600 dark:text-blue-400" x-text="abierto ? 'Ocultar' : 'Mostrar'"></span>
        </button>

        <div x-show="abierto" x-cloak class="border-t border-slate-100 dark:border-slate-800">
            <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <div class="{{ $marcado('requested_for_name') }}">
                <label for="requested_for_name" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Solicitado para</label>
                    @if($estaMarcado('requested_for_name'))
                        <p class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-amber-700 dark:text-amber-300">
                            <span aria-hidden="true">↺</span> Compras pidió corregir esto
                        </p>
                    @endif
                <input id="requested_for_name" name="requested_for_name" x-model="requestedFor" value="{{ $requestedFor }}" autocomplete="name"
                    class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Persona que necesita esta compra">
                @error('requested_for') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                @error('requested_for_name') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="{{ $marcado('cost_center') }}">
                <label for="cost_center" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Centro de costo o proyecto</label>
                    @if($estaMarcado('cost_center'))
                        <p class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-amber-700 dark:text-amber-300">
                            <span aria-hidden="true">↺</span> Compras pidió corregir esto
                        </p>
                    @endif
                <input id="cost_center" name="cost_center" list="cost-centers-catalog" value="{{ old('cost_center', $purchaseRequest?->cost_center ?? ($isEditing ? null : ($defaults['cost_center'] ?? null))) }}"
                    class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Opcional">
                @error('cost_center') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
            </div>
            <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <div>
                <label for="priority" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Prioridad <span class="text-rose-500">*</span></label>
                <select id="priority" name="priority" x-model="priority" required
                    class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="normal">Normal</option>
                    <option value="urgent">Urgente</option>
                </select>
                @error('priority') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="{{ $marcado('delivery_location') }}">
                <label for="delivery_location" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Lugar de entrega o uso</label>
                    @if($estaMarcado('delivery_location'))
                        <p class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-amber-700 dark:text-amber-300">
                            <span aria-hidden="true">↺</span> Compras pidió corregir esto
                        </p>
                    @endif
                <input id="delivery_location" name="delivery_location" list="locations-catalog" value="{{ old('delivery_location', $purchaseRequest?->delivery_location ?? ($isEditing ? null : ($defaults['delivery_location'] ?? null))) }}"
                    class="mt-1.5 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Opcional">
                @error('delivery_location') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div x-show="priority === 'urgent'" x-cloak class="sm:col-span-2">
                <label for="urgent_reason" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Justificación de urgencia <span class="text-rose-500">*</span></label>
                <textarea id="urgent_reason" name="urgent_reason" rows="2" :required="priority === 'urgent'"
                    class="mt-1.5 w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Explica por qué no puede esperar.">{{ old('urgent_reason', $purchaseRequest?->urgent_reason) }}</textarea>
                @error('urgent_reason') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="internal_notes" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Observaciones internas</label>
                <textarea id="internal_notes" name="internal_notes" rows="2" x-model="internalNotes"
                    class="mt-1.5 w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Información adicional para Compras.">{{ $internalNotes }}</textarea>
                @error('notes') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                @error('internal_notes') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

            <div class="grid gap-5 p-4 sm:p-5 lg:grid-cols-2">
            <div class="space-y-3">
                <p class="text-sm font-bold text-slate-700 dark:text-slate-200">Proveedores sugeridos <span class="font-normal text-slate-400">(opcional, máximo 4)</span></p>
                @foreach($suppliers as $index => $supplier)
                    <input name="suggested_suppliers[{{ $index }}]" value="{{ $supplier }}"
                        class="min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Proveedor sugerido {{ $index + 1 }}">
                @endforeach
            </div>
            <div>
                <label for="attachments" class="block text-sm font-bold text-slate-700 dark:text-slate-200">Adjuntar antecedentes</label>
                <input id="attachments" type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700 hover:file:bg-blue-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:file:bg-blue-950/50 dark:file:text-blue-300">
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">PDF, JPG o PNG. Los archivos se guardan de forma privada.</p>
                @error('attachments') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                @error('attachments.*') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        </div>
    </section>

    <div {!! $soloManual !!} class="flex flex-col-reverse gap-3 pb-6 sm:flex-row sm:items-center sm:justify-end">
        <a href="{{ $isEditing ? route('purchase_requests.show', $purchaseRequest) : route('purchase_requests.index') }}"
            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-5 text-sm font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Cancelar</a>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-5 text-sm font-extrabold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
            {{ $isEditing ? 'Guardar cambios' : 'Guardar borrador' }}
        </button>
    </div>
</form>

<script>
    function purchaseRequestForm(initialItems, flaggedPositions = []) {
        return {
            items: initialItems.map((item, index) => ({ key: Date.now() + index, ...item })),
            priority: @js(old('priority', $purchaseRequest?->priority ?? 'normal')),
            requestedFor: @js($requestedFor),
            internalNotes: @js($internalNotes),
            errors: @js($errors->getMessages()),

            // Posiciones (1..N) que el revisor marcó al devolver la solicitud.
            // Se congelan al cargar: si el solicitante agrega o quita líneas,
            // el marcado deja de tener sentido y desaparece solo.
            flagged: Array.isArray(flaggedPositions) ? flaggedPositions.slice() : [],
            initialCount: initialItems.length,

            isFlagged(index) {
                return this.items.length === this.initialCount
                    && this.flagged.includes(index + 1);
            },

            addItem() {
                // La unidad más común, para no obligar a elegirla en cada línea.
                this.items.push({ key: Date.now() + Math.random(), product_service: '', specification: '', quantity: '', unit: 'Unidades', quantity_note: '', destination: '' });
            },
            removeItem(index) {
                if (this.items.length > 1) this.items.splice(index, 1);
            },
            fieldError(key) {
                return this.errors[key] ? this.errors[key][0] : '';
            },
        };
    }
</script>
