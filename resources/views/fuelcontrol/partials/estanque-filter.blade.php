@if(($estanques ?? collect())->isNotEmpty())
    <select name="estanque_id" aria-label="Estanque" onchange="this.form.submit()" class="{{ $selectClass ?? 'flt-select' }}">
        <option value="">Estanque — todos</option>
        @foreach($estanques as $estanque)
            <option value="{{ $estanque->id }}" @selected((string) request('estanque_id') === (string) $estanque->id)>{{ $estanque->nombre }}</option>
        @endforeach
        <option value="sin_asignar" @selected(request('estanque_id') === 'sin_asignar')>Diésel sin estanque</option>
    </select>
@endif
