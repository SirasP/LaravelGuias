<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                DTE desde Gmail (selecciona e importa)
            </div>

            <form method="GET" class="flex gap-2" id="searchForm">
                <input name="q" value="{{ $q ?? '' }}" placeholder="Buscar (Gmail)..."
                    class="w-72 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm"
                    autocomplete="off">
                <input type="hidden" name="perPage" value="{{ $perPage ?? 30 }}">
                <button class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-sm">
                    Buscar
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4">

            @if(!empty($error))
                <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-3 text-red-800">
                    {{ $error }}
                </div>
            @endif

            @if ($errors->any())
                <script>
                    window.addEventListener('load', () => {
                        const messages = @json($errors->all());
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: messages.join('\n'),
                        });
                    });
                </script>
            @endif

            <form method="POST" action="{{ route('inventario.dtes.gmail.import') }}" id="importForm">
                @csrf

                <div class="flex items-center justify-between mb-3 gap-3">
                    <label class="inline-flex items-center gap-2 text-sm select-none">
                        <input id="checkAll" type="checkbox" class="rounded">
                        <span>Seleccionar todo</span>
                        <span class="text-gray-500 dark:text-gray-400" id="selectedCount"></span>
                    </label>

                    <div class="flex items-center gap-2">
                        <button type="submit" id="submitBtn"
                            class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                            Importar seleccionados
                        </button>

                        <button type="button" id="loadMoreBtn"
                            class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-sm disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2"
                            @disabled(empty($nextPageToken))>
                            <svg id="btnSpinner" class="hidden animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                                </path>
                            </svg>
                            <span id="loadMoreText">Cargar más</span>
                        </button>
                    </div>
                </div>

                {{-- CONTENEDOR RELATIVE para overlay --}}
                <div class="relative">
                    {{-- OVERLAY SPINNER encima de la tabla --}}
                    <div id="tableOverlay"
                        class="hidden absolute inset-0 z-10 rounded-xl bg-white/70 dark:bg-gray-950/70 backdrop-blur-[2px]">
                        <div class="h-full w-full flex items-center justify-center">
                            <div
                                class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-950 px-4 py-3 shadow-sm">
                                <svg class="animate-spin h-5 w-5 text-gray-700 dark:text-gray-200"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                <span class="text-sm text-gray-700 dark:text-gray-200">Cargando más correos…</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-left">
                                    <th class="w-10"></th>
                                    <th>Asunto</th>
                                    <th>Desde</th>
                                    <th>Fecha</th>
                                    <th class="w-44">Ver</th>
                                </tr>
                            </thead>

                            <tbody id="rowsTbody" class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($items as $it)
                                    @php
                                        $oldSelected = (array) old('message_ids', []);
                                        $isChecked = in_array($it->gmail_id, $oldSelected, true);
                                    @endphp

                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40" data-id="{{ $it->gmail_id}}">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" name="message_ids[]" value="{{ $it->gmail_id }}"
                                                class="rowCheck rounded" @checked($isChecked)
                                                aria-label="Seleccionar mensaje {{ $it->gmail_id }}">
                                        </td>

                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                                {{ $it->subject }}
                                            </div>
                                        </td>

                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $it->from }}
                                        </td>

                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $it->date }}
                                        </td>

                                        <td class="px-4 py-3">
                                            <div class="flex gap-3">
                                                <a class="text-blue-600 hover:underline"
                                                    href="{{ route('inventario.dte.leer', ['id' => $it->gmail_id]) }}">
                                                    Ver XML
                                                </a>

                                                <a class="text-green-600 hover:underline"
                                                    href="{{ route('inventario.dte.ver', ['id' => $it->gmail_id]) }}">
                                                    Ver DTE
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="emptyRow">
                                        <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                            No hay DTE en Gmail.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Tip: puedes seleccionar varios y luego importar en un solo envío.
                    </p>

                    <span class="text-xs text-gray-500 dark:text-gray-400" id="loadMoreStatus">
                        @if(empty($nextPageToken) && count($items) > 0)
                            No hay más resultados.
                        @endif
                    </span>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const all = document.getElementById('checkAll');
            const submitBtn = document.getElementById('submitBtn');
            const selectedCount = document.getElementById('selectedCount');
            const rowsTbody = document.getElementById('rowsTbody');
            const loadMoreBtn = document.getElementById('loadMoreBtn');
            const loadMoreText = document.getElementById('loadMoreText');
            const btnSpinner = document.getElementById('btnSpinner');
            const loadMoreStatus = document.getElementById('loadMoreStatus');
            const tableOverlay = document.getElementById('tableOverlay');

            // Backend devuelve: nextPageToken + items[{id,subject,from,date}]
            let nextPageToken = @json($nextPageToken ?? null);
            const perPage = @json($perPage ?? 30);
            const q = @json($q ?? '');
            const modo = @json($modo ?? 'inbox'); // IMPORTANTE: mantener el mismo modo al paginar

            const rowChecks = () => Array.from(document.querySelectorAll('.rowCheck'));

            const updateUI = () => {
                const rows = rowChecks();
                const checked = rows.filter(cb => cb.checked).length;
                const total = rows.length;

                if (selectedCount) selectedCount.textContent = total ? `(${checked}/${total})` : '';
                if (submitBtn) submitBtn.disabled = checked === 0;

                if (!all) return;
                if (total === 0) {
                    all.checked = false;
                    all.indeterminate = false;
                    all.disabled = true;
                    return;
                }
                all.disabled = false;
                all.checked = checked === total;
                all.indeterminate = checked > 0 && checked < total;
            };

            const escapeHtml = (s) => String(s ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const addRows = (items) => {
                const emptyRow = document.getElementById('emptyRow');
                if (emptyRow) emptyRow.remove();

                const existingIds = new Set(
                    Array.from(rowsTbody.querySelectorAll('tr[data-id]'))
                        .map(tr => tr.getAttribute('data-id'))
                );

                const verXmlTpl = @json(route('inventario.dte.leer', ['id' => '__ID__']));
                const verDteTpl = @json(route('inventario.dte.ver', ['id' => '__ID__']));

                const html = (items || [])
                    .filter(it => it && it.id && !existingIds.has(String(it.id)))
                    .map(it => {
                        const id = escapeHtml(it.id);
                        const subject = escapeHtml(it.subject);
                        const from = escapeHtml(it.from);
                        const date = escapeHtml(it.date);

                        return `
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40" data-id="${id}">
            <td class="px-4 py-3">
              <input type="checkbox" name="message_ids[]" value="${id}"
                class="rowCheck rounded" aria-label="Seleccionar mensaje ${id}">
            </td>
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900 dark:text-gray-100">${subject}</div>
            </td>
            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">${from}</td>
            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">${date}</td>
            <td class="px-4 py-3">
              <div class="flex gap-3">
                <a class="text-blue-600 hover:underline" href="${verXmlTpl.replace('__ID__', id)}">Ver XML</a>
                <a class="text-green-600 hover:underline" href="${verDteTpl.replace('__ID__', id)}">Ver DTE</a>
              </div>
            </td>
          </tr>
        `;
                    }).join('');

                if (html) rowsTbody.insertAdjacentHTML('beforeend', html);
                updateUI();
            };

            const setLoading = (loading) => {
                if (loadMoreBtn) loadMoreBtn.disabled = loading || !nextPageToken;
                if (btnSpinner) btnSpinner.classList.toggle('hidden', !loading);
                if (tableOverlay) tableOverlay.classList.toggle('hidden', !loading);
                if (loadMoreText) loadMoreText.textContent = loading ? 'Cargando…' : 'Cargar más';
            };

            const loadMore = async () => {
                if (!nextPageToken) return;

                setLoading(true);
                if (loadMoreStatus) loadMoreStatus.textContent = '';

                try {
                    const url = new URL(window.location.href);

                    // Mantener los mismos filtros para que la página 2 sea consistente
                    url.searchParams.set('q', q);
                    url.searchParams.set('perPage', perPage);

                    // IMPORTANTE: tu backend usa "max" para cantidad (si lo implementaste así)
                    url.searchParams.set('max', perPage);

                    // IMPORTANTE: mantener el modo (inbox/all/xml) en paginación
                    url.searchParams.set('modo', modo);

                    // Paginación Gmail
                    url.searchParams.set('pageToken', nextPageToken);

                    const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });

                    const contentType = res.headers.get('content-type') || '';
                    if (!contentType.includes('application/json')) {
                        const text = await res.text();
                        throw new Error('El servidor no devolvió JSON (probable error/redirect). ' + text.slice(0, 120));
                    }
                    if (!res.ok) throw new Error('No se pudo cargar más.');

                    const data = await res.json();

                    if (data.error) throw new Error(data.error);

                    addRows(data.items || []);
                    nextPageToken = data.nextPageToken || null;

                    if (!nextPageToken) {
                        if (loadMoreStatus) loadMoreStatus.textContent = 'No hay más resultados.';
                    }
                } catch (e) {
                    Swal?.fire?.({
                        icon: 'error',
                        title: 'Error',
                        text: e?.message || 'Error cargando más resultados.'
                    });
                } finally {
                    setLoading(false);
                }
            };

            if (all) {
                all.addEventListener('change', () => {
                    rowChecks().forEach(cb => cb.checked = all.checked);
                    updateUI();
                });
            }

            document.addEventListener('change', (e) => {
                if (e.target && e.target.classList && e.target.classList.contains('rowCheck')) {
                    updateUI();
                }
            });

            if (loadMoreBtn) loadMoreBtn.addEventListener('click', loadMore);

            updateUI();
        })();
    </script>

</x-app-layout>