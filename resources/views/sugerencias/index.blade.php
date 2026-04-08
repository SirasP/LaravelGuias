<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Reclamos y Sugerencias
        </h2>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    {{-- Toast --}}
    <div
        x-data
        x-show="$store.toast.visible"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-6"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-6"
        x-cloak
        :class="$store.toast.type === 'success'
            ? 'fixed bottom-6 right-6 z-50 flex items-start gap-3 px-5 py-4 rounded-2xl shadow-2xl border border-green-100 bg-white dark:bg-gray-800 dark:border-green-800/60 max-w-sm w-full'
            : 'fixed bottom-6 right-6 z-50 flex items-start gap-3 px-5 py-4 rounded-2xl shadow-2xl border border-red-100 bg-white dark:bg-gray-800 dark:border-red-800/60 max-w-sm w-full'"
        style="display:none"
    >
        <div :class="$store.toast.type === 'success'
                ? 'shrink-0 w-9 h-9 rounded-xl bg-green-100 dark:bg-green-900/50 flex items-center justify-center'
                : 'shrink-0 w-9 h-9 rounded-xl bg-red-100 dark:bg-red-900/50 flex items-center justify-center'">
            <template x-if="$store.toast.type === 'success'">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </template>
            <template x-if="$store.toast.type === 'error'">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </template>
        </div>
        <div class="flex-1 min-w-0 pt-0.5">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100" x-text="$store.toast.title"></p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed" x-text="$store.toast.message"></p>
        </div>
        <button @click="$store.toast.hide()" class="shrink-0 text-gray-300 hover:text-gray-500 dark:hover:text-gray-200 mt-0.5 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <div :class="$store.toast.type === 'success' ? 'absolute bottom-0 left-0 h-1 bg-green-400 rounded-b-2xl' : 'absolute bottom-0 left-0 h-1 bg-red-400 rounded-b-2xl'"
             :style="'width:' + $store.toast.progress + '%; transition: width 4s linear'"></div>
    </div>

    {{-- Contenido --}}
    <div x-data="sugerenciasForm()" class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-green-50/40 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 py-12 px-4">
        <div class="max-w-xl mx-auto">

            {{-- Encabezado --}}
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 dark:bg-green-900/40 rounded-2xl mb-5 shadow-sm">
                    <svg class="w-8 h-8 text-green-700 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reclamos y Sugerencias</h1>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 leading-relaxed max-w-md mx-auto">
                    Su opinión es fundamental para optimizar la producción, el cuidado de los cultivos
                    y el buen funcionamiento del huerto. Todas las propuestas son evaluadas.
                </p>
            </div>

            {{-- Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">

                {{-- Top bar --}}
                <div class="flex items-center justify-between px-6 py-3.5 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-100 dark:border-gray-700">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Nuevo envío</span>
                    <button @click="downloadQR()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/30 hover:bg-green-100 dark:hover:bg-green-800/40 rounded-lg border border-green-200 dark:border-green-800 transition">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                        </svg>
                        Descargar QR
                    </button>
                    <div id="qrcode" class="hidden absolute"></div>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-6">
                    @csrf

                    {{-- Tipo --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            ¿Qué deseas enviar? <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">

                            <button type="button" @click="tipo = 'sugerencia'"
                                :class="tipo === 'sugerencia'
                                    ? 'border-blue-500 ring-2 ring-blue-400/40 bg-blue-50 dark:bg-blue-950/40'
                                    : 'border-gray-200 dark:border-gray-600 hover:border-blue-200 hover:bg-blue-50/50 dark:hover:bg-blue-950/20'"
                                class="relative flex flex-col items-center gap-1.5 p-4 border-2 rounded-xl transition-all duration-150">
                                <span class="text-2xl">💡</span>
                                <span class="text-sm font-bold" :class="tipo === 'sugerencia' ? 'text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'">Sugerencia</span>
                                <span class="text-[11px] text-center" :class="tipo === 'sugerencia' ? 'text-blue-400' : 'text-gray-400'">Ideas y propuestas</span>
                                <div x-show="tipo === 'sugerencia'" x-transition
                                     class="absolute top-2 right-2 w-5 h-5 bg-blue-500 rounded-full flex items-center justify-center shadow-sm">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </button>

                            <button type="button" @click="tipo = 'reclamo'"
                                :class="tipo === 'reclamo'
                                    ? 'border-red-500 ring-2 ring-red-400/40 bg-red-50 dark:bg-red-950/40'
                                    : 'border-gray-200 dark:border-gray-600 hover:border-red-200 hover:bg-red-50/50 dark:hover:bg-red-950/20'"
                                class="relative flex flex-col items-center gap-1.5 p-4 border-2 rounded-xl transition-all duration-150">
                                <span class="text-2xl">📢</span>
                                <span class="text-sm font-bold" :class="tipo === 'reclamo' ? 'text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-400'">Reclamo</span>
                                <span class="text-[11px] text-center" :class="tipo === 'reclamo' ? 'text-red-400' : 'text-gray-400'">Problemas a reportar</span>
                                <div x-show="tipo === 'reclamo'" x-transition
                                     class="absolute top-2 right-2 w-5 h-5 bg-red-500 rounded-full flex items-center justify-center shadow-sm">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </button>
                        </div>
                        <p x-show="errors.tipo" x-cloak class="mt-2 text-xs text-red-500 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <span x-text="errors.tipo"></span>
                        </p>
                    </div>

                    {{-- Mensaje --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="comentario" class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Tu mensaje <span class="text-red-500">*</span>
                            </label>
                            <span class="text-xs tabular-nums"
                                  :class="comentario.length > 900 ? 'text-orange-500 font-semibold' : 'text-gray-400'"
                                  x-text="comentario.length + ' / 1000'"></span>
                        </div>
                        <textarea
                            id="comentario"
                            x-model="comentario"
                            maxlength="1000"
                            rows="5"
                            placeholder="Escribe aquí con el mayor detalle posible..."
                            :class="errors.comentario
                                ? 'border-red-400 focus:ring-red-400 bg-red-50/30 dark:bg-red-950/10'
                                : 'border-gray-200 dark:border-gray-600 focus:ring-green-400 bg-gray-50 dark:bg-gray-700/50'"
                            class="w-full rounded-xl px-4 py-3 text-sm text-gray-800 dark:text-gray-100 border-2 focus:outline-none focus:ring-2 transition-all resize-none placeholder-gray-400 dark:placeholder-gray-500"
                        ></textarea>
                        <p x-show="errors.comentario" x-cloak class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <span x-text="errors.comentario"></span>
                        </p>
                    </div>

                    {{-- Submit --}}
                    <button type="submit" :disabled="loading"
                        class="w-full py-3.5 bg-green-600 hover:bg-green-700 active:bg-green-800 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-bold rounded-xl shadow-sm shadow-green-200 dark:shadow-none transition-all duration-150 flex items-center justify-center gap-2">
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

            <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-6">
                Huerto Agrícola EHE &mdash; Todas las propuestas son evaluadas
            </p>
        </div>
    </div>

    <script>
        // ── Store toast ────────────────────────────────────────────
        document.addEventListener('alpine:init', () => {
            Alpine.store('toast', {
                visible:  false,
                type:     'success',
                title:    '',
                message:  '',
                progress: 100,
                _timer:   null,

                show(type, title, message) {
                    clearTimeout(this._timer);
                    this.type     = type;
                    this.title    = title;
                    this.message  = message;
                    this.visible  = true;
                    this.progress = 100;
                    this.$nextTick?.(() => setTimeout(() => this.progress = 0, 30));
                    this._timer = setTimeout(() => this.visible = false, 4200);
                },
                hide() { this.visible = false; clearTimeout(this._timer); }
            });
        });

        // ── Form ───────────────────────────────────────────────────
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
                    return !Object.keys(this.errors).length;
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
                        try   { data = await res.json(); }
                        catch { Alpine.store('toast').show('error', 'Error ' + res.status, 'Respuesta inesperada del servidor.'); return; }

                        if (data.success) {
                            Alpine.store('toast').show('success', '¡Enviado correctamente!', data.message);
                            this.tipo = ''; this.comentario = ''; this.errors = {};
                        } else {
                            Alpine.store('toast').show('error', 'No se pudo enviar', data.message || data.error || 'Intenta nuevamente.');
                        }
                    } catch (err) {
                        Alpine.store('toast').show('error', 'Sin conexión', err.message);
                    } finally {
                        this.loading = false;
                    }
                },

                // ── Descarga QR profesional ─────────────────────
                downloadQR() {
                    const url = window.location.href.split('?')[0];

                    // Canvas temporal para generar el QR
                    const tmp = document.createElement('div');
                    tmp.style.cssText = 'position:absolute;left:-9999px;top:-9999px';
                    document.body.appendChild(tmp);

                    new QRCode(tmp, {
                        text: url, width: 360, height: 360,
                        colorDark: '#166534', colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M,
                    });

                    setTimeout(() => {
                        const qrCanvas = tmp.querySelector('canvas');
                        if (!qrCanvas) { document.body.removeChild(tmp); return; }

                        // Canvas final con diseño para imprimir
                        const pad    = 40;
                        const footer = 90;
                        const W      = qrCanvas.width  + pad * 2;
                        const H      = qrCanvas.height + pad * 2 + footer;

                        const canvas = document.createElement('canvas');
                        canvas.width  = W;
                        canvas.height = H;
                        const ctx = canvas.getContext('2d');

                        // Fondo blanco
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, W, H);

                        // Borde verde redondeado
                        const r = 20;
                        ctx.strokeStyle = '#166534';
                        ctx.lineWidth   = 6;
                        ctx.beginPath();
                        ctx.roundRect(4, 4, W - 8, H - 8, r);
                        ctx.stroke();

                        // QR
                        ctx.drawImage(qrCanvas, pad, pad);

                        // Línea separadora
                        ctx.strokeStyle = '#d1fae5';
                        ctx.lineWidth   = 1.5;
                        ctx.beginPath();
                        ctx.moveTo(pad, pad + qrCanvas.height + 16);
                        ctx.lineTo(W - pad, pad + qrCanvas.height + 16);
                        ctx.stroke();

                        // Título
                        ctx.fillStyle   = '#166534';
                        ctx.font        = 'bold 22px system-ui, sans-serif';
                        ctx.textAlign   = 'center';
                        ctx.fillText('Reclamos y Sugerencias', W / 2, pad + qrCanvas.height + 46);

                        // Subtítulo
                        ctx.fillStyle = '#6b7280';
                        ctx.font      = '15px system-ui, sans-serif';
                        ctx.fillText('Huerto Agrícola EHE', W / 2, pad + qrCanvas.height + 70);

                        // Descarga
                        const a = document.createElement('a');
                        a.href     = canvas.toDataURL('image/png');
                        a.download = 'sugerencias-qr.png';
                        a.click();

                        document.body.removeChild(tmp);
                    }, 250);
                }
            };
        }
    </script>
</x-app-layout>
