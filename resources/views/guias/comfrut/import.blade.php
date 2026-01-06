<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between w-full gap-6">
            <div class="max-w-2xl">
                <nav class="text-sm text-gray-500 whitespace-nowrap">
                    <ol class="flex items-center gap-1">
                        <li>
                            <a href="{{ route('guias.comfrut.index') }}" class="hover:text-gray-900 transition">
                                Guías COMFRUT
                            </a>
                        </li>
                        <li>/</li>
                        <li class="text-gray-900 font-medium">
                            Importar XML
                        </li>
                    </ol>
                </nav>

                <p class="mt-1 text-sm text-gray-500">
                    Sube el archivo XML de guías de recepción COMFRUT. Se validan duplicados por número de guía.
                </p>
            </div>

            <div class="max-w-2xl">
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        Formato: .xml
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        Máx: 20MB
                    </span>
                    <span
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                        Evita duplicados por guía
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('guias.comfrut.index') }}"
                    class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Volver a vista
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6" x-data="comfrutXmlUploader()"
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

                <form method="POST" action="{{ route('guias.comfrut.import') }}" enctype="multipart/form-data"
                    @submit="onSubmit">
                    @csrf

                    {{-- Dropzone --}}
                    <div class="rounded-xl border-2 border-dashed p-6 transition"
                        :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-gray-50'">

                        <div class="flex flex-col items-center text-center gap-2">
                            <div class="text-sm font-semibold text-gray-800">
                                Arrastra y suelta tu XML aquí
                            </div>
                            <div class="text-xs text-gray-500">
                                o selecciona un archivo desde tu equipo.
                            </div>

                            <div class="mt-3">
                                <input x-ref="file" type="file" name="xml[]" multiple accept=".xml" required
                                    class="hidden" @change="onPick">

                                <button type="button"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-100"
                                    @click="$refs.file.click()" :disabled="submitting">
                                    Elegir archivo
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Archivo seleccionado --}}
                    <div class="mt-4" x-show="files.length">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-sm font-semibold text-gray-800">
                                Archivos seleccionados (<span x-text="files.length"></span>)
                            </div>

                            <button type="button" class="text-xs text-gray-600 hover:underline" @click="clearFile"
                                :disabled="submitting">
                                Quitar todos
                            </button>
                        </div>

                        <div class="border rounded-lg bg-white divide-y">
                            <template x-for="f in files" :key="f.name">
                                <div class="flex items-center justify-between px-3 py-2">
                                    <div class="min-w-0">
                                        <div class="text-sm text-gray-900 truncate" x-text="f.name"></div>
                                        <div class="text-xs text-gray-500" x-text="f.sizeHuman"></div>
                                    </div>

                                    <span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">
                                        XML
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>


                    {{-- Submit --}}
                    <div class="mt-5 flex items-center justify-between gap-3">
                        <div class="text-xs text-gray-500">
                            El XML será validado y procesado antes de guardarse.
                        </div>

                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-lg
                                   bg-indigo-600 text-sm font-semibold hover:bg-indigo-700
                                   disabled:opacity-60 disabled:cursor-not-allowed transition"
                            :disabled="!files.length || submitting">
                            <span x-text="submitting ? 'Importando…' : 'Importar'"></span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function comfrutXmlUploader() {
            return {
                dragging: false,
                submitting: false,

                files: [],

                onPick(e) {
                    const files = Array.from(e.target.files)
                        .filter(f => f.name.toLowerCase().endsWith('.xml'));

                    this.setFiles(files);
                },

                onDrop(e) {
                    this.dragging = false;

                    const files = Array.from(e.dataTransfer.files)
                        .filter(f => f.name.toLowerCase().endsWith('.xml'));

                    if (!files.length) return;

                    const dt = new DataTransfer();
                    files.forEach(f => dt.items.add(f));
                    this.$refs.file.files = dt.files;

                    this.setFiles(files);
                },

                setFiles(files) {
                    this.files = files.map(f => ({
                        name: f.name,
                        size: f.size,
                        sizeHuman: this.formatBytes(f.size),
                    }));
                },

                clearFile() {
                    this.files = [];
                    this.$refs.file.files = new DataTransfer().files;
                },

                onSubmit(e) {
                    if (this.submitting || !this.files.length) {
                        e.preventDefault();
                        return;
                    }
                    this.submitting = true;
                },

                formatBytes(bytes) {
                    const units = ['B', 'KB', 'MB'];
                    let i = 0;
                    while (bytes >= 1024 && i < units.length - 1) {
                        bytes /= 1024;
                        i++;
                    }
                    return `${bytes.toFixed(i ? 1 : 0)} ${units[i]}`;
                }
            }
        }
    </script>

</x-app-layout>