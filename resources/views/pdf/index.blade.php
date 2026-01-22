<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3">
            <div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    DTE / Facturas
                </div>
                 
            </div>

          
            <a href="{{ route('pdf.export.xlsx') }}"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
                Exportar Excel
            </a>

        </div>
    </x-slot>

    @if(session('ok'))
        <div class="mb-4 p-3 rounded bg-green-100 text-green-800">
            {{ session('ok') }}
        </div>
    @endif

    <div class="py-6">
        <div class="w-full px-4 sm:px-7 lg:px-9">
            <div class="bg-white shadow sm:rounded-lg p-6" x-data="pdfIndex({{ $imports->getCollection()->map(fn($i) => [
    'id' => $i->id,
    'guia' => $i->guia_no,
    'name' => $i->original_name,
    'template' => $i->template ?? '—',
    'doc_fecha' => $i->doc_fecha ? \Carbon\Carbon::parse($i->doc_fecha)->format('d-m-Y') : null,
    'created_at' => $i->created_at->format('d-m-Y H:i'),
])->values()->toJson(JSON_UNESCAPED_UNICODE) }})">

                {{-- Toolbar --}}
                <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
    <form
        id="filtersForm"
        method="GET"
        action="{{ route('pdf.index') }}"
        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
    >
        {{-- Filtros --}}
        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
            {{-- Buscar --}}
            <div class="w-full sm:w-80">
                <label for="qInput" class="block text-xs font-medium text-gray-500 mb-1">
                    Buscar
                </label>
                <input
                    id="qInput"
                    name="q"
                    type="text"
                    value="{{ request('q') }}"
                    placeholder="Archivo / Guía / ID / fecha…"
                    class="w-full rounded-md border-gray-300 shadow-sm
                           focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>

            {{-- Modelo --}}
            <div class="w-full sm:w-44">
                <label for="modelSelect" class="block text-xs font-medium text-gray-500 mb-1">
                    Modelo
                </label>
                <select
                    id="modelSelect"
                    name="model"
                    class="w-full rounded-md border-gray-300 shadow-sm
                           focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Todos</option>
                    @foreach (['QC','MP','VT','B','C','—'] as $m)
                        <option value="{{ $m }}" {{ request('model') === $m ? 'selected' : '' }}>
                            {{ $m === '—' ? 'Sin modelo' : $m }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Estado de orden --}}
            <input type="hidden" name="order_by" value="{{ request('order_by', 'doc_fecha') }}">
            <input type="hidden" name="dir" value="{{ request('dir', 'desc') }}">

            {{-- Acciones --}}
            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm
                           hover:bg-indigo-700 transition"
                >
                    Filtrar
                </button>

                <a
                    href="{{ route('pdf.index') }}"
                    class="px-3 py-2 rounded-md bg-gray-100 text-sm
                           hover:bg-gray-200 transition"
                >
                    Limpiar
                </a>
            </div>
        </div>

        {{-- Contador --}}
        <div class="text-sm text-gray-600 text-right">
            Mostrando
            <span class="font-medium text-gray-900">{{ $imports->count() }}</span>
            de
            <span class="font-medium text-gray-900">{{ $imports->total() }}</span>
        </div>
    </form>
</div>

                {{-- Table --}}
                <div class="mt-4 overflow-x-auto border border-gray-200 rounded-xl shadow-sm">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 sticky top-0 z-10">
            <tr class="text-left text-gray-600">
                <th class="px-4 py-3 w-20">ID</th>
                <th class="px-4 py-3 w-28">Guía</th>
                <th class="px-4 py-3">Archivo</th>

                @php
                    $isPdfDate = ($orderBy ?? 'doc_fecha') === 'doc_fecha';
                    $nextDir = ($dir ?? 'desc') === 'desc' ? 'asc' : 'desc';
                @endphp

                <th class="px-4 py-3 w-40 select-none">
                    <a
                        href="{{ request()->fullUrlWithQuery([
                            'order_by' => 'doc_fecha',
                            'dir' => $isPdfDate ? $nextDir : 'desc'
                        ]) }}"
                        class="inline-flex items-center gap-1 hover:text-gray-900"
                    >
                        Fecha PDF
                        @if($isPdfDate)
                            <span class="text-xs text-gray-400">
                                {{ $dir === 'asc' ? '▲' : '▼' }}
                            </span>
                        @endif
                    </a>
                </th>

                <th class="px-4 py-3 w-44">Importado</th>
                <th class="px-4 py-3 w-40 text-right">Acciones</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-100 bg-white">
            <template x-for="r in filtered" :key="r.id">
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 text-gray-500" x-text="r.id"></td>

                    <td class="px-4 py-3 font-medium text-gray-800"
                        x-text="r.guia ?? '—'"></td>

                    <td class="px-4 py-3">
                        <div
                            class="max-w-[520px] truncate font-medium text-gray-900"
                            :title="r.name"
                            x-text="r.name"
                        ></div>
                    </td>

                    <td class="px-4 py-3 text-gray-600"
                        x-text="r.doc_fecha ?? '—'"></td>

                    <td class="px-4 py-3 text-gray-600"
                        x-text="r.created_at"></td>

                    <td class="px-4 py-3 text-right space-x-1 whitespace-nowrap">
                        <a
                            class="inline-flex items-center px-3 py-1.5 rounded-md bg-indigo-50 text-indigo-700 text-xs font-medium hover:bg-indigo-100 transition"
                            :href="`{{ url('/pdf/imports') }}/${r.id}/ver`"
                            target="_blank"
                        >
                            Ver
                        </a>

                        <a
                            class="inline-flex items-center px-3 py-1.5 rounded-md bg-gray-100 text-gray-700 text-xs font-medium hover:bg-gray-200 transition"
                            :href="`{{ url('/pdf/imports') }}/${r.id}/archivo`"
                            target="_blank"
                        >
                            Archivo
                        </a>

                        <a
                            class="inline-flex items-center px-3 py-1.5 rounded-md bg-gray-100 text-gray-700 text-xs font-medium hover:bg-gray-200 transition"
                            :href="`{{ url('/pdf/imports') }}/${r.id}`"
                            target="_blank"
                        >
                            JSON
                        </a>
                    </td>
                </tr>
            </template>

            <tr x-show="filtered.length === 0">
                <td colspan="7" class="px-6 py-16 text-center text-gray-500">
                    No hay resultados con ese filtro o búsqueda.
                </td>
            </tr>
        </tbody>
    </table>
</div>


                {{-- Pagination --}}
                {{-- Esto evita que Turbo/SPA te "recicle" el DOM y se quede pegado con los mismos rows --}}
                <div class="mt-4" data-turbo="false">
                    {{ $imports->links() }}
                </div>

                @if(session('import_report'))
                    <div class="mb-4 p-4 rounded-lg border bg-white">
                        <div class="font-semibold text-gray-800 mb-2">Detalle de importación</div>

                        <div class="overflow-auto border rounded-lg">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-700">
                                    <tr class="text-left">
                                        <th class="p-2">Archivo</th>
                                        <th class="p-2 w-20">Estado</th>
                                        <th class="p-2 w-20">Modelo</th>
                                        <th class="p-2 w-24">Guía</th>
                                        <th class="p-2">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($imports as $r)
<tr class="hover:bg-gray-50">
    <td class="p-3 text-gray-600">{{ $r->id }}</td>
    <td class="p-3 font-semibold">{{ $r->guia_no ?? '—' }}</td>
    <td class="p-3">
        <div class="font-medium truncate max-w-[520px]">
            {{ $r->original_name }}
        </div>
    </td>
    <td class="p-3">
        <span class="px-2 py-1 rounded text-xs">
            {{ $r->template ?? '—' }}
        </span>
    </td>
    <td class="p-3">{{ $r->doc_fecha }}</td>
    <td class="p-3">{{ $r->created_at }}</td>
    <td class="p-3 text-right">
        <a href="{{ route('pdf.show', $r->id) }}">Ver</a>
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
    </div>

    <script>
        function pdfIndex(rows) {
            return {
                rows,
                q: '',
                model: '',

                get filtered() {
                    const q = (this.q || '').trim().toLowerCase();
                    const m = (this.model || '').trim();

                    return this.rows.filter(r => {
                        const okModel = m ? (String(r.template) === m) : true;
                        if (!okModel) return false;

                        if (!q) return true;

                        return (
                            String(r.id).includes(q) ||
                            String(r.guia ?? '').toLowerCase().includes(q) ||
                            (r.name || '').toLowerCase().includes(q) ||
                            (r.doc_fecha || '').toLowerCase().includes(q) ||
                            (r.created_at || '').toLowerCase().includes(q) ||
                            (r.template || '').toLowerCase().includes(q)
                        );
                    });
                },

                badgeClass(tpl) {
                    switch (tpl) {
                        case 'QC': return 'bg-emerald-100 text-emerald-800';
                        case 'MP': return 'bg-blue-100 text-blue-800';
                        case 'VT': return 'bg-amber-100 text-amber-900';
                        case 'C': return 'bg-purple-100 text-purple-800';
                        case '—': return 'bg-gray-100 text-gray-700';
                        default: return 'bg-gray-100 text-gray-700';
                    }
                }
            }
        }
    </script>
    <script>
        (function () {
            const form = document.getElementById('filtersForm');
            const q = document.getElementById('qInput');
            const model = document.getElementById('modelSelect');

            // al cambiar modelo, enviar altiro
            model?.addEventListener('change', () => {
                // volver siempre a página 1
                const url = new URL(window.location.href);
                url.searchParams.set('page', '1');
                window.history.replaceState({}, '', url.toString());
                form.submit();
            });

            // al escribir, enviar con debounce (ej. 450ms)
            let t = null;
            q?.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(() => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('page', '1');
                    window.history.replaceState({}, '', url.toString());
                    form.submit();
                }, 450);
            });
        })();
    </script>

</x-app-layout>