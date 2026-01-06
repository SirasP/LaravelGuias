<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between w-full gap-6">
            {{-- Izquierda: título + descripción --}}
            <div class="max-w-2xl">
                <nav class="text-sm text-gray-500 whitespace-nowrap">
                    <ol class="flex items-center gap-1">
                        <li>
                            <a href="{{ route('pdf.index') }}" class="hover:text-gray-900 transition">
                                PDFs
                            </a>
                        </li>
                        <li>/</li>
                        <li class="text-gray-900 font-medium">
                            Importar PDF
                        </li>
                    </ol>
                </nav>
                <p class="mt-1 text-sm text-gray-500">
                    Sube uno o varios PDFs. Detectamos el modelo (QC / MP) y guardamos el contenido.
                </p>
            </div>

            {{-- Derecha: breadcrumb --}}

        </div>
    </x-slot>


    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">

                @if(session('ok'))
                    <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-800">
                        {{ session('ok') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-800">
                        <div class="font-semibold mb-1">Errores:</div>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="" enctype="multipart/form-data" id="excelForm">
    @csrf

    <select id="excelType" class="mb-3 border rounded px-2 py-1">
        <option value="qc">Excel VT (GDD)</option>
        <option value="rfp">Excel RFP</option>
    </select>

    <input type="file" name="excel" required>

    <button class="mt-3 btn">Importar Excel</button>
</form>
<form method="POST" action="{{ route('pdf.import.xml') }}" enctype="multipart/form-data">
    @csrf

    <div class="mb-4">
        <label class="block font-semibold mb-1">Importar XML SII</label>
        <input type="file" name="xmls[]" multiple accept=".xml"
            class="border rounded p-2 w-full">
    </div>

    <button class="px-4 py-2 bg-green-600 text-white rounded">
        Importar XML
    </button>
</form>
<script>
    const form = document.getElementById('excelForm');
    const sel = document.getElementById('excelType');

    form.addEventListener('submit', () => {
        form.action = sel.value === 'rfp'
            ? "{{ route('excel.import.rfp') }}"
            : "{{ route('excel.import.qc') }}";
    });
</script>


            </div>
        </div>
    </div>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6" x-data="pdfUploader()" x-on:dragover.prevent="dragging=true"
                x-on:dragleave.prevent="dragging=false" x-on:drop.prevent="onDrop($event)">

                @if(session('ok'))
                    <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-800">
                        {{ session('ok') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-800">
                        <div class="font-semibold mb-1">Revisa estos puntos:</div>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('pdf.import') }}" enctype="multipart/form-data" @submit="onSubmit">
                    @csrf

                    {{-- Dropzone --}}
                    <div class="rounded-xl border-2 border-dashed p-6 transition"
                        :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-gray-50'">
                        <div class="flex flex-col items-center text-center gap-2">
                            <div class="text-sm font-semibold text-gray-800">
                                Arrastra y suelta tus PDFs aquí
                            </div>
                            <div class="text-xs text-gray-500">
                                o selecciona archivos desde tu equipo. Máx 10MB c/u.
                            </div>

                            <div class="mt-3">
                                <input x-ref="file" type="file" name="pdfs[]" accept="application/pdf" multiple required
                                    class="hidden" @change="onPick">

                                <button type="button"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-100"
                                    @click="$refs.file.click()" :disabled="submitting">
                                    Elegir archivos
                                </button>
                            </div>

                            <div class="mt-2 text-xs text-gray-400" x-show="files.length === 0">
                                Tip: puedes subir varios a la vez.
                            </div>
                        </div>
                    </div>

                    {{-- Lista de archivos --}}
                    <div class="mt-4" x-show="files.length > 0">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-sm font-semibold text-gray-800">
                                Archivos seleccionados (<span x-text="files.length"></span>)
                            </div>

                            <button type="button" class="text-xs text-gray-600 hover:underline" @click="clearAll"
                                :disabled="submitting">
                                Limpiar todo
                            </button>
                        </div>

                        <div class="border rounded-lg overflow-hidden">
                            <template x-for="(f, idx) in files" :key="f._key">
                                <div
                                    class="flex items-center justify-between px-3 py-2 border-b last:border-b-0 bg-white">
                                    <div class="min-w-0">
                                        <div class="text-sm text-gray-900 truncate" x-text="f.name"></div>
                                        <div class="text-xs text-gray-500">
                                            <span x-text="formatBytes(f.size)"></span>
                                        </div>
                                    </div>

                                    <button type="button"
                                        class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700"
                                        @click="removeAt(idx)" :disabled="submitting">
                                        Quitar
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Submit --}}
                    <div class="mt-5 flex items-center justify-between gap-3">
                        <div class="text-xs text-gray-500">
                            Al subir, detectamos el modelo automáticamente (QC / MP).
                            QC = COMFRUT MP = RIO FUTURO
                        </div>

                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-lg
           bg-indigo-600 text-sm font-semibold
           hover:bg-indigo-700
           disabled:opacity-60 disabled:cursor-not-allowed
           transition" :disabled="files.length === 0 || submitting">

                            <!-- Icono subir -->
                            <svg x-show="!submitting" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 12v9m0-9l-3 3m3-3l3 3M12 3v9" />
                            </svg>

                            <!-- Spinner -->
                            <svg x-show="submitting" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                                </path>
                            </svg>

                            <span x-text="submitting ? 'Subiendo…' : 'Subir'"></span>
                        </button>

                    </div>
                </form>



            </div>
        </div>
    </div>

    <script>
        function pdfUploader() {
            return {
                dragging: false,
                submitting: false,
                files: [],

                onPick(e) {
                    const picked = Array.from(e.target.files || []);
                    this.mergeFiles(picked);
                    this.syncInput();
                },

                onDrop(e) {
                    this.dragging = false;
                    const dropped = Array.from(e.dataTransfer.files || [])
                        .filter(f => f.type === 'application/pdf' || (f.name || '').toLowerCase().endsWith('.pdf'));

                    this.mergeFiles(dropped);
                    this.syncInput();
                },

                mergeFiles(incoming) {
                    // dedupe simple por name+size+lastModified
                    const key = (f) => `${f.name}__${f.size}__${f.lastModified}`;
                    const existing = new Set(this.files.map(key));

                    for (const f of incoming) {
                        if (!existing.has(key(f))) {
                            f._key = key(f);
                            this.files.push(f);
                            existing.add(key(f));
                        }
                    }
                },

                removeAt(idx) {
                    this.files.splice(idx, 1);
                    this.syncInput();
                },

                clearAll() {
                    this.files = [];
                    this.syncInput();
                },

                syncInput() {
                    // Actualiza el input real para que el backend reciba pdfs[]
                    const dt = new DataTransfer();
                    for (const f of this.files) dt.items.add(f);
                    this.$refs.file.files = dt.files;
                },

                onSubmit(e) {
                    if (this.submitting) {
                        e.preventDefault();
                        return;
                    }
                    if (!this.files.length) {
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