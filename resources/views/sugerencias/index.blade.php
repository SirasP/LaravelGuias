<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Reclamos y Sugerencias
        </h2>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <div class="py-10 px-4 flex flex-col items-center">

        {{-- Descripción --}}
        <div class="w-full max-w-xl mb-8 text-center">
            <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed">
                Este formulario tiene como objetivo recoger sugerencias, reclamos y propuestas de mejora relacionadas con el huerto agrícola.
                Su opinión es fundamental para optimizar la producción, el cuidado de los cultivos, el uso de recursos y las condiciones generales del espacio.
            </p>
            <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed mt-3">
                Le invitamos a compartir sus ideas y observaciones de manera clara y detallada. Todas las propuestas serán evaluadas con el fin de fortalecer el desarrollo sostenible y el buen funcionamiento del huerto.
            </p>
        </div>

        {{-- Card --}}
        <div class="w-full max-w-xl bg-white dark:bg-gray-800 rounded-2xl shadow-md p-8">

            {{-- Botón descargar QR --}}
            <div class="flex justify-end mb-6">
                <button id="btnDownloadQR"
                    class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-green-700 dark:text-green-400 border border-green-300 dark:border-green-700 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/30 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    Descargar QR
                </button>
                {{-- QR oculto para generar --}}
                <div id="qrcode" class="hidden absolute"></div>
            </div>

            {{-- Formulario --}}
            <form id="formSugerencias" novalidate>
                @csrf

                {{-- Tipo --}}
                <div class="mb-5">
                    <label for="tipo" class="block text-sm font-semibold text-green-800 dark:text-green-400 mb-1">
                        Tipo de envío <span class="text-red-500">*</span>
                    </label>
                    <select id="tipo" name="tipo" required
                        class="w-full border border-green-300 dark:border-green-700 rounded-lg px-3 py-2.5 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-green-400">
                        <option value="" disabled selected>— Selecciona una opción —</option>
                        <option value="sugerencia">💡 Sugerencia</option>
                        <option value="reclamo">📢 Reclamo</option>
                    </select>
                </div>

                {{-- Comentario --}}
                <div class="mb-6">
                    <label for="comentario" class="block text-sm font-semibold text-green-800 dark:text-green-400 mb-1">
                        Tu mensaje <span class="text-red-500">*</span>
                    </label>
                    <textarea id="comentario" name="comentario" maxlength="1000" rows="5" required
                        placeholder="Escribe aquí tu sugerencia o reclamo con el mayor detalle posible..."
                        class="w-full border border-green-300 dark:border-green-700 rounded-lg px-3 py-2.5 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-green-400 resize-y"></textarea>
                    <p class="text-right text-xs text-gray-400 mt-1">
                        <span id="charCount">0</span> / 1000
                    </p>
                </div>

                {{-- Botón enviar --}}
                <button type="submit" id="btnSubmit"
                    class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl transition-all duration-150 flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    Enviar
                </button>

                <div id="alertBox" class="hidden mt-5 px-4 py-3 rounded-lg text-sm font-medium"></div>
            </form>
        </div>
    </div>

    <script>
        // ── Descargar QR ──────────────────────────────────────────
        document.getElementById('btnDownloadQR').addEventListener('click', () => {
            const qrDiv = document.getElementById('qrcode');
            qrDiv.innerHTML = '';

            new QRCode(qrDiv, {
                text:             window.location.href.split('?')[0],
                width:            800,
                height:           800,
                colorDark:        '#166534',
                colorLight:       '#ffffff',
                correctLevel:     QRCode.CorrectLevel.M,
            });

            setTimeout(() => {
                const canvas = qrDiv.querySelector('canvas');
                if (!canvas) return;
                const a    = document.createElement('a');
                a.href     = canvas.toDataURL('image/png');
                a.download = 'sugerencias-qr.png';
                a.click();
            }, 200);
        });

        // ── Contador ──────────────────────────────────────────────
        const textarea  = document.getElementById('comentario');
        const charCount = document.getElementById('charCount');
        textarea.addEventListener('input', () => charCount.textContent = textarea.value.length);

        // ── Submit ────────────────────────────────────────────────
        const form      = document.getElementById('formSugerencias');
        const alertBox  = document.getElementById('alertBox');
        const btnSubmit = document.getElementById('btnSubmit');

        function showAlert(msg, type) {
            alertBox.textContent = msg;
            alertBox.className = type === 'success'
                ? 'mt-5 px-4 py-3 rounded-lg text-sm font-medium bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 border-l-4 border-green-500'
                : 'mt-5 px-4 py-3 rounded-lg text-sm font-medium bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-l-4 border-red-500';
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            alertBox.className = 'hidden';

            const tipo       = document.getElementById('tipo').value;
            const comentario = textarea.value.trim();

            if (!tipo)                  { showAlert('Selecciona si es una sugerencia o reclamo.', 'error'); return; }
            if (comentario.length < 10) { showAlert('El mensaje debe tener al menos 10 caracteres.', 'error'); return; }

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg> Enviando...';

            try {
                const res = await fetch('{{ route("sugerencias.store") }}', { method: 'POST', body: new FormData(form) });

                let data;
                try {
                    data = await res.json();
                } catch {
                    showAlert('Error ' + res.status + ': respuesta inesperada del servidor.', 'error');
                    return;
                }

                if (data.success) {
                    showAlert(data.message, 'success');
                    form.reset();
                    charCount.textContent = '0';
                } else {
                    showAlert(data.message || data.error || 'Error al enviar.', 'error');
                }
            } catch (err) {
                showAlert('Sin conexión con el servidor: ' + err.message, 'error');
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Enviar';
            }
        });
    </script>
</x-app-layout>
