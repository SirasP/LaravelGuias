<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Reclamos y Sugerencias
        </h2>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    {{-- Toast global --}}
    <div
        x-data="toast()"
        x-show="visible"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        x-cloak
        :class="type === 'success'
            ? 'fixed bottom-6 right-6 z-50 flex items-start gap-3 px-5 py-4 rounded-xl shadow-xl border border-green-200 bg-white dark:bg-gray-800 dark:border-green-800 max-w-sm w-full'
            : 'fixed bottom-6 right-6 z-50 flex items-start gap-3 px-5 py-4 rounded-xl shadow-xl border border-red-200 bg-white dark:bg-gray-800 dark:border-red-800 max-w-sm w-full'"
    >
        {{-- Icono --}}
        <div :class="type === 'success' ? 'shrink-0 w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center' : 'shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center'">
            <template x-if="type === 'success'">
                <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </template>
            <template x-if="type === 'error'">
                <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </template>
        </div>
        {{-- Texto --}}
        <div class="flex-1 min-w-0">
            <p :class="type === 'success' ? 'text-sm font-semibold text-green-800 dark:text-green-300' : 'text-sm font-semibold text-red-800 dark:text-red-300'"
               x-text="title"></p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="message"></p>
        </div>
        {{-- Cerrar --}}
        <button @click="visible = false" class="shrink-0 text-gray-300 hover:text-gray-500 dark:hover:text-gray-200 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        {{-- Barra de progreso --}}
        <div :class="type === 'success' ? 'absolute bottom-0 left-0 h-1 bg-green-400 rounded-b-xl transition-all duration-[4000ms] ease-linear' : 'absolute bottom-0 left-0 h-1 bg-red-400 rounded-b-xl transition-all duration-[4000ms] ease-linear'"
             :style="'width:' + progress + '%'"></div>
    </div>

    <div
        x-data="sugerenciasForm()"
        class="min-h-screen bg-gradient-to-br from-gray-50 to-green-50/30 dark:from-gray-900 dark:to-gray-800 py-12 px-4"
    >
        <div class="max-w-2xl mx-auto">

            {{-- Encabezado de página --}}
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-green-100 dark:bg-green-900/40 rounded-2xl mb-4">
                    <svg class="w-7 h-7 text-green-700 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white tracking-tight">Reclamos y Sugerencias</h1>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 leading-relaxed max-w-lg mx-auto">
                    Recogemos sugerencias, reclamos y propuestas de mejora del huerto agrícola.
                    Su opinión es fundamental para optimizar la producción y el buen funcionamiento del espacio.
                </p>
            </div>

            {{-- Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">

                {{-- Header card con QR --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Nuevo envío</span>
                    <button @click="downloadQR()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/30 hover:bg-green-100 dark:hover:bg-green-900/50 rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 4h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                        Descargar QR
                    </button>
                    <div id="qrcode" class="hidden absolute"></div>
                </div>

                {{-- Formulario --}}
                <form @submit.prevent="submit" class="p-6 space-y-6">
                    @csrf

                    {{-- Selector tipo como tarjetas --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            ¿Qué deseas enviar? <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="tipo = 'sugerencia'"
                                :class="tipo === 'sugerencia'
                                    ? 'ring-2 ring-blue-500 border-blue-500 bg-blue-50 dark:bg-blue-900/30'
                                    : 'border-gray-200 dark:border-gray-600 hover:border-blue-300 dark:hover:border-blue-700 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                                class="relative flex flex-col items-center gap-2 p-4 border-2 rounded-xl transition-all duration-150 cursor-pointer">
                                <span class="text-2xl">💡</span>
                                <span class="text-sm font-semibold"
                                    :class="tipo === 'sugerencia' ? 'text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'">
                                    Sugerencia
                                </span>
                                <span class="text-xs text-center"
                                    :class="tipo === 'sugerencia' ? 'text-blue-500 dark:text-blue-400' : 'text-gray-400'">
                                    Ideas y propuestas de mejora
                                </span>
                                <div x-show="tipo === 'sugerencia'"
                                     class="absolute top-2 right-2 w-4 h-4 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </button>

                            <button type="button" @click="tipo = 'reclamo'"
                                :class="tipo === 'reclamo'
                                    ? 'ring-2 ring-red-500 border-red-500 bg-red-50 dark:bg-red-900/30'
                                    : 'border-gray-200 dark:border-gray-600 hover:border-red-300 dark:hover:border-red-700 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                                class="relative flex flex-col items-center gap-2 p-4 border-2 rounded-xl transition-all duration-150 cursor-pointer">
                                <span class="text-2xl">📢</span>
                                <span class="text-sm font-semibold"
                                    :class="tipo === 'reclamo' ? 'text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-400'">
                                    Reclamo
                                </span>
                                <span class="text-xs text-center"
                                    :class="tipo === 'reclamo' ? 'text-red-500 dark:text-red-400' : 'text-gray-400'">
                                    Problemas a reportar
                                </span>
                                <div x-show="tipo === 'reclamo'"
                                     class="absolute top-2 right-2 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center">
                                    <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </button>
                        </div>
                        <p x-show="errors.tipo" x-text="errors.tipo" x-cloak
                           class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
                        </p>
                    </div>

                    {{-- Textarea --}}
                    <div>
                        <label for="comentario" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Tu mensaje <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <textarea
                                id="comentario"
                                x-model="comentario"
                                maxlength="1000"
                                rows="5"
                                placeholder="Escribe aquí con el mayor detalle posible..."
                                :class="errors.comentario ? 'border-red-400 focus:ring-red-400' : 'border-gray-200 dark:border-gray-600 focus:ring-green-400'"
                                class="w-full rounded-xl px-4 py-3 text-sm bg-gray-50 dark:bg-gray-700/50 text-gray-800 dark:text-gray-100 border focus:outline-none focus:ring-2 transition resize-none placeholder-gray-400"
                            ></textarea>
                            <div class="absolute bottom-3 right-3 flex items-center gap-1">
                                <span :class="comentario.length > 900 ? 'text-orange-500' : 'text-gray-300 dark:text-gray-500'"
                                      class="text-xs tabular-nums" x-text="comentario.length + '/1000'"></span>
                            </div>
                        </div>
                        <p x-show="errors.comentario" x-text="errors.comentario" x-cloak
                           class="mt-1.5 text-xs text-red-500"></p>
                    </div>

                    {{-- Botón submit --}}
                    <button type="submit" :disabled="loading"
                        class="w-full py-3.5 bg-green-600 hover:bg-green-700 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-bold rounded-xl shadow-sm shadow-green-200 dark:shadow-none transition-all duration-150 flex items-center justify-center gap-2">
                        <template x-if="!loading">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                Enviar
                            </span>
                        </template>
                        <template x-if="loading">
                            <span class="flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                Enviando...
                            </span>
                        </template>
                    </button>
                </form>
            </div>

            {{-- Footer --}}
            <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-6">
                Huerto Agrícola EHE &mdash; Todas las propuestas son evaluadas
            </p>
        </div>
    </div>

    <script>
        function toast() {
            return {
                visible: false,
                type: 'success',
                title: '',
                message: '',
                progress: 100,
                _timer: null,

                show(type, title, message) {
                    clearTimeout(this._timer);
                    this.type    = type;
                    this.title   = title;
                    this.message = message;
                    this.visible = true;
                    this.progress = 100;

                    this.$nextTick(() => {
                        setTimeout(() => this.progress = 0, 50);
                    });

                    this._timer = setTimeout(() => this.visible = false, 4200);
                }
            };
        }

        function sugerenciasForm() {
            return {
                tipo:       '',
                comentario: '',
                loading:    false,
                errors:     {},

                validate() {
                    this.errors = {};
                    if (!this.tipo)
                        this.errors.tipo = 'Selecciona si es una sugerencia o reclamo.';
                    if (this.comentario.trim().length < 10)
                        this.errors.comentario = 'El mensaje debe tener al menos 10 caracteres.';
                    return Object.keys(this.errors).length === 0;
                },

                async submit() {
                    if (!this.validate()) return;

                    this.loading = true;
                    const fd = new FormData();
                    fd.append('_token', document.querySelector('[name=_token]').value);
                    fd.append('tipo', this.tipo);
                    fd.append('comentario', this.comentario);

                    try {
                        const res = await fetch('{{ route("sugerencias.store") }}', { method: 'POST', body: fd });

                        let data;
                        try { data = await res.json(); }
                        catch {
                            window.dispatchEvent(new CustomEvent('show-toast', {
                                detail: { type: 'error', title: 'Error ' + res.status, message: 'Respuesta inesperada del servidor.' }
                            }));
                            return;
                        }

                        if (data.success) {
                            window.dispatchEvent(new CustomEvent('show-toast', {
                                detail: { type: 'success', title: '¡Enviado!', message: data.message }
                            }));
                            this.tipo       = '';
                            this.comentario = '';
                            this.errors     = {};
                        } else {
                            window.dispatchEvent(new CustomEvent('show-toast', {
                                detail: { type: 'error', title: 'No se pudo enviar', message: data.message || data.error || 'Intenta nuevamente.' }
                            }));
                        }
                    } catch (err) {
                        window.dispatchEvent(new CustomEvent('show-toast', {
                            detail: { type: 'error', title: 'Sin conexión', message: err.message }
                        }));
                    } finally {
                        this.loading = false;
                    }
                },

                downloadQR() {
                    const qrDiv = document.getElementById('qrcode');
                    qrDiv.innerHTML = '';
                    new QRCode(qrDiv, {
                        text:         window.location.href.split('?')[0],
                        width:        800, height: 800,
                        colorDark:    '#166534',
                        colorLight:   '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M,
                    });
                    setTimeout(() => {
                        const canvas = qrDiv.querySelector('canvas');
                        if (!canvas) return;
                        const a = document.createElement('a');
                        a.href = canvas.toDataURL('image/png');
                        a.download = 'sugerencias-qr.png';
                        a.click();
                    }, 200);
                }
            };
        }

        // Puente entre el form y el toast (componentes distintos)
        window.addEventListener('show-toast', e => {
            const { type, title, message } = e.detail;
            document.querySelector('[x-data="toast()"]')?._x_dataStack?.[0]?.show(type, title, message);
        });
    </script>
</x-app-layout>
