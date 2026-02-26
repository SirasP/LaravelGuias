<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Configuraciones</h2>
            <p class="text-xs text-gray-400 mt-0.5">Archivos DTE, certificados y alertas de stock</p>
        </div>
    </x-slot>

    {{-- ── Estilos para el tag-input de correos ──────────────────── --}}
    <style>
        .combo-tags-wrap {
            display:flex; flex-wrap:wrap; gap:5px; align-items:center;
            padding:4px 8px; border:1px solid #e2e8f0; border-radius:10px;
            background:#fff; min-height:42px; cursor:text;
            transition:border-color .15s, box-shadow .15s;
        }
        .combo-tags-wrap:focus-within {
            border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12);
        }
        .dark .combo-tags-wrap { border-color:#1e293b; background:#0f172a; }
        .dark .combo-tags-wrap:focus-within { border-color:#6366f1; }

        .email-tag {
            display:inline-flex; align-items:center; gap:3px;
            padding:2px 4px 2px 9px; border-radius:999px;
            background:#eef2ff; border:1.5px solid #c7d2fe;
            font-size:12px; font-weight:600; color:#3730a3; white-space:nowrap;
        }
        .dark .email-tag { background:rgba(99,102,241,.14); border-color:rgba(99,102,241,.38); color:#a5b4fc; }

        .email-tag-x {
            display:inline-flex; align-items:center; justify-content:center;
            width:16px; height:16px; border-radius:999px; cursor:pointer;
            font-size:15px; line-height:1; color:#818cf8; background:none;
            border:none; padding:0; transition:.12s;
        }
        .email-tag-x:hover { background:rgba(239,68,68,.15); color:#dc2626; }
        .dark .email-tag-x { color:#818cf8; }

        .tag-bare-input {
            flex:1; min-width:140px; border:none; outline:none;
            background:transparent; font-size:13px; color:#111827; padding:2px 4px;
        }
        .dark .tag-bare-input { color:#f1f5f9; }
        .tag-bare-input::placeholder { color:#9ca3af; }
    </style>

    <div class="py-6">
        <div class="max-w-7xxl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

           

            {{-- ═══════════════ ESTADO SII ══════════════════════════ --}}
            <section>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400/80 mb-3">Estado SII</p>
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/30 overflow-hidden">
                    <div class="px-5 py-4 flex flex-wrap items-center gap-5">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="inline-block w-2 h-2 rounded-full shrink-0
                                    {{ $isRealMode ? 'bg-emerald-500 shadow-[0_0_6px_1px] shadow-emerald-400/60' : 'bg-amber-400 shadow-[0_0_6px_1px] shadow-amber-400/60' }}">
                                </span>
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                    {{ $isRealMode ? 'Modo REAL activo' : 'Modo DESARROLLO activo' }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                {{ $isRealMode
                                    ? 'CAF y certificado detectados. Los DTEs se envían en producción al SII.'
                                    : 'Falta CAF o certificado .pfx. El envío usa TRACKID de desarrollo (no llega al SII real).' }}
                            </p>
                        </div>
                        <div class="flex gap-3 shrink-0">
                            @foreach([['CAF', $cafExists], ['PFX', $pfxExists]] as [$label, $ok])
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">{{ $label }}</span>
                                    <span class="w-8 h-8 rounded-full flex items-center justify-center
                                        {{ $ok ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-red-100 dark:bg-red-900/30' }}">
                                        @if($ok)
                                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <details class="group border-t border-gray-100 dark:border-gray-800">
                        <summary class="px-5 py-2.5 flex items-center gap-1.5 text-[11px] font-medium text-gray-400 dark:text-gray-500 cursor-pointer select-none list-none hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                            <svg class="w-3 h-3 transition-transform duration-200 group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                            Endpoints SII
                        </summary>
                        <div class="px-5 pb-3.5 pt-1 bg-gray-50 dark:bg-gray-900/50 space-y-1">
                            @foreach(['Seed' => $seedUrl, 'Token' => $tokenUrl, 'Recepción' => $recepcionUrl, 'Estado' => $estadoUrl] as $key => $val)
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                    <span class="inline-block w-16 font-medium text-gray-600 dark:text-gray-300 shrink-0">{{ $key }}</span>
                                    <span class="font-mono break-all">{{ $val }}</span>
                                </p>
                            @endforeach
                        </div>
                    </details>
                </div>
            </section>

            {{-- ═══════════════ ARCHIVOS DTE ════════════════════════ --}}
            <section>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400/80 mb-3">Archivos DTE</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    {{-- ── CAF Tipo 33 ─── --}}
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/30 overflow-hidden flex flex-col">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center
                                    {{ $cafExists ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-gray-100 dark:bg-gray-800' }}">
                                    <svg class="w-3.5 h-3.5 {{ $cafExists ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">CAF Tipo 33</p>
                            </div>
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold
                                {{ $cafExists
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $cafExists ? 'Disponible' : 'Ausente' }}
                            </span>
                        </div>
                        <div class="p-4 flex-1 flex flex-col gap-4">
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 font-mono break-all leading-relaxed">
                                {{ $cafDisk }}/{{ $cafPath }}
                            </p>
                            <form method="POST"
                                  action="{{ route('gmail.inventory.sii.upload.caf') }}"
                                  enctype="multipart/form-data"
                                  class="flex-1 flex flex-col gap-2"
                                  x-data="{ fileName: null }">
                                @csrf
                                <label class="flex flex-col items-center gap-2 py-5 px-4 rounded-xl border-2 border-dashed
                                           border-gray-200 dark:border-gray-700
                                           hover:border-indigo-300 dark:hover:border-indigo-600
                                           cursor-pointer transition-all duration-150"
                                    :class="fileName
                                        ? 'border-indigo-300 dark:border-indigo-600 bg-indigo-50/60 dark:bg-indigo-950/25'
                                        : 'hover:bg-gray-50/60 dark:hover:bg-gray-800/30'">
                                    <svg class="w-7 h-7 transition-colors"
                                        :class="fileName ? 'text-indigo-400 dark:text-indigo-500' : 'text-gray-300 dark:text-gray-600'"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <span x-show="!fileName" class="text-xs text-gray-400 dark:text-gray-500 text-center">
                                        Arrastra o selecciona un<br>
                                        <span class="font-semibold text-gray-500 dark:text-gray-400">.xml</span>
                                    </span>
                                    <span x-show="fileName" x-text="fileName"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 font-medium text-center break-all"></span>
                                    <input type="file" name="caf_file" accept=".xml" required class="hidden"
                                        @change="fileName = $event.target.files[0]?.name ?? null">
                                </label>
                                <button type="submit" x-show="fileName"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="w-full py-1.5 text-xs font-semibold rounded-lg
                                           bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                                    Subir CAF
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- ── Certificado PFX ─── --}}
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/30 overflow-hidden flex flex-col">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center
                                    {{ $pfxExists ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-gray-100 dark:bg-gray-800' }}">
                                    <svg class="w-3.5 h-3.5 {{ $pfxExists ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Certificado PFX</p>
                            </div>
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold
                                {{ $pfxExists
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $pfxExists ? 'Disponible' : 'Ausente' }}
                            </span>
                        </div>
                        <div class="p-4 flex-1 flex flex-col gap-4">
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 font-mono break-all leading-relaxed">
                                {{ $pfxDisk }}/{{ $pfxPath }}
                            </p>
                            <form method="POST"
                                  action="{{ route('gmail.inventory.sii.upload.pfx') }}"
                                  enctype="multipart/form-data"
                                  class="flex flex-col gap-2"
                                  x-data="{ fileName: null }">
                                @csrf
                                <label class="flex flex-col items-center gap-2 py-5 px-4 rounded-xl border-2 border-dashed
                                           border-gray-200 dark:border-gray-700
                                           hover:border-indigo-300 dark:hover:border-indigo-600
                                           cursor-pointer transition-all duration-150"
                                    :class="fileName
                                        ? 'border-indigo-300 dark:border-indigo-600 bg-indigo-50/60 dark:bg-indigo-950/25'
                                        : 'hover:bg-gray-50/60 dark:hover:bg-gray-800/30'">
                                    <svg class="w-7 h-7 transition-colors"
                                        :class="fileName ? 'text-indigo-400 dark:text-indigo-500' : 'text-gray-300 dark:text-gray-600'"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <span x-show="!fileName" class="text-xs text-gray-400 dark:text-gray-500 text-center">
                                        Arrastra o selecciona un<br>
                                        <span class="font-semibold text-gray-500 dark:text-gray-400">.pfx</span> o
                                        <span class="font-semibold text-gray-500 dark:text-gray-400">.p12</span>
                                    </span>
                                    <span x-show="fileName" x-text="fileName"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 font-medium text-center break-all"></span>
                                    <input type="file" name="pfx_file" accept=".pfx,.p12" required class="hidden"
                                        @change="fileName = $event.target.files[0]?.name ?? null">
                                </label>
                                <button type="submit" x-show="fileName"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="w-full py-1.5 text-xs font-semibold rounded-lg
                                           bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                                    Subir Certificado
                                </button>
                            </form>

                            <div class="border-t border-gray-100 dark:border-gray-800 pt-4">
                                <form method="POST" action="{{ route('gmail.inventory.sii.config') }}">
                                    @csrf
                                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Contraseña del certificado
                                        @if($hasPfxPassword)
                                            <span class="font-normal text-emerald-500 dark:text-emerald-400"> — configurada</span>
                                        @else
                                            <span class="font-normal text-amber-500 dark:text-amber-400"> — no configurada</span>
                                        @endif
                                    </p>
                                    <div class="flex gap-2">
                                        <input type="password" name="dte_signature_pfx_password"
                                            placeholder="{{ $hasPfxPassword ? 'Actualizar contraseña…' : 'Contraseña del .pfx' }}"
                                            class="flex-1 px-3 py-1.5 text-xs rounded-lg
                                                   border border-gray-200 dark:border-gray-700
                                                   bg-white dark:bg-gray-800
                                                   text-gray-800 dark:text-gray-200
                                                   placeholder-gray-400 dark:placeholder-gray-600
                                                   focus:outline-none focus:ring-2 focus:ring-indigo-500/25
                                                   focus:border-indigo-400 dark:focus:border-indigo-600 transition">
                                        <button type="submit"
                                            class="shrink-0 px-3 py-1.5 text-xs font-semibold rounded-lg
                                                   bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700
                                                   text-gray-700 dark:text-gray-300 transition-colors
                                                   border border-gray-200 dark:border-gray-700">
                                            Guardar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            {{-- ═══════════════ ALERTAS DE STOCK ═══════════════════ --}}
            @php
                $emailsList = array_values(array_filter(array_map('trim', explode(',', $lowStockEmails))));
            @endphp
            <section>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400/80 mb-3">Alertas de Stock Bajo</p>
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/30 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                            Estas personas recibirán un correo automático cuando el stock de un producto caiga por debajo del mínimo configurado.
                        </p>
                    </div>
                    <div class="p-5" x-data="emailTagInput()">

                        <form method="POST"
                              action="{{ route('gmail.inventory.sii.config') }}"
                              @submit="if(newEmail.trim()) add()">
                            @csrf
                            <input type="hidden" name="low_stock_emails" :value="emails.join(',')">

                            {{-- Tag-input (mismo patrón que cotizaciones/crear) --}}
                            <div class="combo-tags-wrap" @mousedown="$event.target === $el && $refs.emailInput.focus()">
                                <template x-for="(email, idx) in emails" :key="email">
                                    <span class="email-tag">
                                        <span x-text="email"></span>
                                        <button type="button" class="email-tag-x"
                                            @mousedown.prevent="remove(idx)"
                                            title="Quitar">&times;</button>
                                    </span>
                                </template>
                                <input type="text"
                                    x-ref="emailInput"
                                    x-model="newEmail"
                                    class="tag-bare-input"
                                    @keydown.enter.prevent="add()"
                                    @keydown.,.prevent="add()"
                                    @keydown.tab="if(newEmail.trim()){ $event.preventDefault(); add(); }"
                                    @blur="if(newEmail.trim()) add()"
                                    :placeholder="emails.length ? 'Agregar otro correo...' : 'Escribe un correo y presiona Enter...'"
                                    autocomplete="off">
                            </div>

                            {{-- Error / aviso vacío --}}
                            <div class="mt-1.5 min-h-[18px]">
                                <p x-show="errMsg" x-text="errMsg" x-transition
                                    class="text-[11px] text-red-500 dark:text-red-400"></p>
                                <p x-show="!errMsg && emails.length === 0" x-transition
                                    class="text-[11px] text-amber-600 dark:text-amber-400">
                                    Sin destinatarios — las alertas no se enviarán por correo.
                                </p>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-4">
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">
                                    Presiona
                                    <kbd class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-mono text-[10px]">Enter</kbd>
                                    o
                                    <kbd class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-mono text-[10px]">,</kbd>
                                    para agregar
                                </p>
                                <button type="submit"
                                    class="shrink-0 px-4 py-1.5 text-xs font-semibold rounded-lg
                                           bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                                    Guardar destinatarios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

        </div>
    </div>

    <script>
    function emailTagInput() {
        return {
            emails: @json($emailsList),
            newEmail: '',
            errMsg: '',
            add() {
                const e = this.newEmail.trim().toLowerCase();
                if (!e) return;
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) {
                    this.errMsg = 'El correo ingresado no es válido.';
                    return;
                }
                if (this.emails.includes(e)) {
                    this.errMsg = 'Ese correo ya está en la lista.';
                    return;
                }
                this.emails.push(e);
                this.newEmail = '';
                this.errMsg = '';
            },
            remove(i) {
                this.emails.splice(i, 1);
                this.errMsg = '';
            }
        };
    }
    </script>
</x-app-layout>
