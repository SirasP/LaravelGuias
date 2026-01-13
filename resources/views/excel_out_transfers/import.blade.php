<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between w-full gap-6">
            <div class="max-w-2xl">
                <nav class="text-sm text-gray-500 whitespace-nowrap">
                    <ol class="flex items-center gap-2">

                        <li class="text-gray-900 font-sm">
                            Importar Excel
                        </li>
                    </ol>
                </nav>

                <p class="hidden lg:block mt-1 text-sm text-gray-500">
                    Sube el archivo Excel exportado desde Odoo. Normalizamos la guía (sin ceros) y evitamos duplicados.
                </p>
            </div>
            <div class="hidden lg:block max-w-2xl">
                <ol class="flex items-center gap-1">
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                            <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                            Formatos: .xlsx / .xls
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                            <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                            Máx: 20MB
                        </span>
                        <span
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                            Evita duplicados por guía_entrega
                        </span>
                    </div>
                </ol>
            </div>

            <div class="hidden lg:block flex items-center gap-2">
                <a href="{{ route('excel_out_transfers.index') }}"
                    class="inline-flex items-center px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Volver a vista </a>
            </div>
            <div class="flex items-center">
                <a href="{{ route('excel_out_transfers.index') }}" class="
        w-full sm:w-auto
        inline-flex items-center justify-center gap-2
        px-4 py-3
        rounded-xl
        border border-gray-300 dark:border-gray-700
        bg-white dark:bg-gray-900
        text-sm sm:text-base font-medium
        text-gray-700 dark:text-gray-200
        hover:bg-gray-50 dark:hover:bg-gray-800
        transition
       ">
                    <!-- icono -->
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>

                    <span>Atras</span>
                </a>
            </div>

        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6" x-data="excelOutTransfersUploader()"
                x-on:dragover.prevent="dragging=true" x-on:dragleave.prevent="dragging=false"
                x-on:drop.prevent="onDrop($event)">

                {{-- OK --}}
                @if(session('ok'))
                    <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-800">
                        {{ session('ok') }}
                    </div>
                @endif

                {{-- ERRORS --}}
                @if($errors->any())
                    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-800">
                        <div class="font-semibold mb-1">Errores:</div>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('excel_out_transfers.import') }}" enctype="multipart/form-data"
                    @submit="onSubmit">
                    @csrf

                    {{-- Dropzone --}}
                    <div class="rounded-xl border-2 border-dashed p-6 transition"
                        :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-gray-50'">

                        <div class="flex flex-col items-center text-center gap-2">
                            <div class="text-sm font-semibold text-gray-800">
                                Arrastra y suelta tu Excel aquí
                            </div>
                            <div class="text-xs text-gray-500">
                                o selecciona un archivo desde tu equipo.
                            </div>

                            <div class="mt-3">
                                <input x-ref="file" type="file" name="excel" accept=".xlsx,.xls" required class="hidden"
                                    @change="onPick">

                                <button type="button"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-100"
                                    @click="$refs.file.click()" :disabled="submitting">
                                    Elegir archivo
                                </button>
                            </div>

                            <div class="mt-2 text-xs text-gray-400" x-show="!fileName">
                                Tip: si re-importas, se actualizarán registros existentes.
                            </div>
                        </div>
                    </div>

                    {{-- Archivo seleccionado --}}
                    <div class="mt-4" x-show="fileName">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-sm font-semibold text-gray-800">
                                Archivo seleccionado
                            </div>

                            <button type="button" class="text-xs text-gray-600 hover:underline" @click="clearFile"
                                :disabled="submitting">
                                Quitar
                            </button>
                        </div>

                        <div class="border rounded-lg bg-white overflow-hidden">
                            <div class="flex items-center justify-between px-3 py-2">
                                <div class="min-w-0">
                                    <div class="text-sm text-gray-900 truncate" x-text="fileName"></div>
                                    <div class="text-xs text-gray-500">
                                        <span x-text="fileSizeHuman"></span>
                                    </div>
                                </div>

                                <span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">
                                    EXCEL
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Submit --}}
                    <div class="mt-5 flex items-center justify-between gap-3">
                        <div class="text-xs text-gray-500">
                            Se procesan columnas como: Contacto, Fecha prevista, Patente, Número de guía de entrega,
                            etc.
                        </div>

                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-lg
                                       bg-indigo-600 text-sm font-semibold 
                                       hover:bg-indigo-700
                                       disabled:opacity-60 disabled:cursor-not-allowed
                                       transition" :disabled="!hasFile || submitting">
                            {{-- icon --}}
                            <svg x-show="!submitting" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 12v9m0-9l-3 3m3-3l3 3M12 3v9" />
                            </svg>

                            {{-- spinner --}}
                            <svg x-show="submitting" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                                </path>
                            </svg>

                            <span x-text="submitting ? 'Importando…' : 'Importar'"></span>
                        </button>
                    </div>
                </form>

                {{-- Detalle import_report (si lo envías en session) --}}
                @if(session('import_report'))
                    <div class="mt-6 p-4 rounded-lg border bg-white">
                        <div class="font-semibold text-gray-800 mb-2">Detalle de importación</div>

                        <div class="overflow-auto border rounded-lg">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-700">
                                    <tr class="text-left">
                                        <th class="p-2">Archivo</th>
                                        <th class="p-2 w-24">Estado</th>
                                        <th class="p-2 w-24">Guía</th>
                                        <th class="p-2">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach(session('import_report') as $r)
                                        @php $st = $r['status'] ?? ''; @endphp
                                        <tr>
                                            <td class="p-2">{{ $r['file'] ?? '—' }}</td>
                                            <td class="p-2">
                                                <span class="px-2 py-0.5 rounded text-xs
                                                                                                                    {{ $st === 'imported' ? 'bg-green-100 text-green-800' : '' }}
                                                                                                                    {{ $st === 'duplicate' ? 'bg-amber-100 text-amber-900' : '' }}
                                                                                                                    {{ $st === 'skip' ? 'bg-gray-100 text-gray-700' : '' }}
                                                                                                                ">
                                                    {{ $st }}
                                                </span>
                                            </td>
                                            <td class="p-2 font-semibold">{{ $r['guia'] ?? '—' }}</td>
                                            <td class="p-2 text-gray-600">{{ $r['reason'] ?? '' }}</td>
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
        function excelOutTransfersUploader() {
            return {
                dragging: false,
                submitting: false,
                hasFile: false,
                fileName: '',
                fileSizeHuman: '',

                onPick(e) {
                    const f = (e.target.files || [])[0];
                    if (!f) return;
                    this.setFile(f);
                },

                onDrop(e) {
                    this.dragging = false;
                    const f = (e.dataTransfer.files || [])[0];
                    if (!f) return;

                    const name = (f.name || '').toLowerCase();
                    const ok = name.endsWith('.xlsx') || name.endsWith('.xls');
                    if (!ok) return;

                    // Setear al input real
                    const dt = new DataTransfer();
                    dt.items.add(f);
                    this.$refs.file.files = dt.files;

                    this.setFile(f);
                },

                setFile(f) {
                    this.hasFile = true;
                    this.fileName = f.name || 'archivo';
                    this.fileSizeHuman = this.formatBytes(f.size || 0);
                },

                clearFile() {
                    this.hasFile = false;
                    this.fileName = '';
                    this.fileSizeHuman = '';
                    const dt = new DataTransfer();
                    this.$refs.file.files = dt.files;
                },

                onSubmit(e) {
                    if (this.submitting || !this.hasFile) {
                        e.preventDefault();
                        return;
                    }
                    this.submitting = true;
                },

                formatBytes(bytes) {
                    const units = ['B', 'KB', 'MB', 'GB'];
                    let i = 0;
                    let n = bytes;
                    while (n >= 1024 && i < units.length - 1) {
                        n /= 1024;
                        i++;
                    }
                    return `${n.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
                }
            }
        }
    </script>
</x-app-layout>