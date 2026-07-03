<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Configuraciones</h2>
                <p class="text-xs text-gray-400 mt-0.5">Facturación electrónica, certificados y alertas de stock</p>
            </div>
        </div>
    </x-slot>

    {{-- ── Estilos tag-input ─────────────────────────────────────── --}}
    <style>
        .combo-tags-wrap {
            display:flex; flex-wrap:wrap; gap:5px; align-items:center;
            padding:6px 10px; border:1.5px solid #e2e8f0; border-radius:12px;
            background:#fff; min-height:44px; cursor:text;
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

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-8xl mx-auto space-y-6">

         

            {{-- ── Banner Estado SII ───────────────────────────────────── --}}
            <div class="rounded-2xl border
                {{ $isRealMode
                    ? 'border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/10'
                    : 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10' }}">

                {{-- Fila principal --}}
                <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="w-3 h-3 rounded-full shrink-0
                            {{ $isRealMode
                                ? 'bg-emerald-500 shadow-[0_0_8px_2px] shadow-emerald-400/50'
                                : 'bg-amber-400 shadow-[0_0_8px_2px] shadow-amber-400/50' }}">
                        </span>
                        <div>
                            <p class="text-sm font-bold {{ $isRealMode ? 'text-emerald-800 dark:text-emerald-200' : 'text-amber-800 dark:text-amber-200' }}">
                                {{ $isRealMode ? 'Modo REAL activo' : 'Modo DESARROLLO activo' }}
                            </p>
                            <p class="text-xs {{ $isRealMode ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }} mt-0.5">
                                {{ $isRealMode
                                    ? 'CAF y certificado detectados. Los DTEs se emiten en producción al SII.'
                                    : 'Falta CAF o certificado .pfx. El envío usa TRACKID de desarrollo (no llega al SII real).' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        @foreach(['CAF' => $cafExists, 'PFX' => $pfxExists] as $label => $ok)
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center
                                {{ $ok ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-red-100 dark:bg-red-900/30' }}">
                                @if($ok)
                                    <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    <svg class="w-3.5 h-3.5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                @endif
                            </span>
                            <span class="text-xs font-semibold {{ $ok ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-600 dark:text-red-400' }}">
                                {{ $label }}
                            </span>
                        </div>
                        @endforeach

                        {{-- Botón endpoints --}}
                        <details class="group" x-data>
                            <summary class="flex items-center gap-1 text-xs font-medium text-gray-500 dark:text-gray-400 cursor-pointer select-none list-none hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                                <svg class="w-3 h-3 transition-transform duration-150 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                </svg>
                                Endpoints
                            </summary>
                        </details>
                    </div>
                </div>

                {{-- Endpoints expandibles (inline, no absolute) --}}
                <div id="endpoints-panel" class="hidden px-5 pb-4">
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3 space-y-1.5">
                        @foreach(['Seed' => $seedUrl, 'Token' => $tokenUrl, 'Recepción' => $recepcionUrl, 'Estado' => $estadoUrl] as $key => $val)
                        <div class="flex gap-2 text-[11px]">
                            <span class="w-16 font-semibold text-gray-600 dark:text-gray-300 shrink-0">{{ $key }}</span>
                            @if($val)
                                <span class="font-mono text-emerald-500 dark:text-emerald-400">Configurado</span>
                            @else
                                <span class="font-mono text-red-400 dark:text-red-500">No configurado</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ── Grid principal ──────────────────────────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- ══════════ COL IZQUIERDA: Archivos DTE ══════════════ --}}
                <div class="space-y-4">

                    {{-- Encabezado de sección --}}
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Archivos DTE</p>
                            <p class="text-[11px] text-gray-400">Certificado y folio de autorización de comprobantes</p>
                        </div>
                    </div>

                    {{-- CAF Tipo 33 --}}
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center
                                    {{ $cafExists ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-gray-100 dark:bg-gray-800' }}">
                                    <svg class="w-3.5 h-3.5 {{ $cafExists ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">CAF Tipo 33</span>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold
                                {{ $cafExists
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $cafExists ? 'Activo' : 'Ausente' }}
                            </span>
                        </div>
                        <div class="p-4 space-y-3">
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 font-mono break-all bg-gray-50 dark:bg-gray-800/50 rounded-lg px-3 py-2">
                                {{ $cafDisk }}/{{ $cafPath }}
                            </p>
                            <form method="POST" action="{{ route('gmail.inventory.sii.upload.caf') }}"
                                  enctype="multipart/form-data" x-data="{ fileName: null }">
                                @csrf
                                <label class="flex flex-col items-center gap-2 py-5 px-4 rounded-xl border-2 border-dashed cursor-pointer transition-all duration-150
                                              border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700"
                                       :class="fileName
                                           ? 'border-blue-300 dark:border-blue-600 bg-blue-50/50 dark:bg-blue-950/20'
                                           : 'hover:bg-gray-50 dark:hover:bg-gray-800/30'">
                                    <svg class="w-7 h-7 transition-colors"
                                         :class="fileName ? 'text-blue-400' : 'text-gray-300 dark:text-gray-600'"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <span x-show="!fileName" class="text-xs text-gray-400 text-center">
                                        Arrastra o haz clic para seleccionar<br>
                                        <span class="font-semibold text-gray-500 dark:text-gray-400">.xml</span>
                                    </span>
                                    <span x-show="fileName" x-text="fileName"
                                          class="text-xs text-blue-600 dark:text-blue-400 font-medium text-center break-all"></span>
                                    <input type="file" name="caf_file" accept=".xml" required class="hidden"
                                           @change="fileName = $event.target.files[0]?.name ?? null">
                                </label>
                                <button type="submit" x-show="fileName"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="mt-2 w-full py-2 text-xs font-semibold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                                    Subir archivo CAF
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Certificado PFX --}}
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center
                                    {{ $pfxExists ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-gray-100 dark:bg-gray-800' }}">
                                    <svg class="w-3.5 h-3.5 {{ $pfxExists ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </div>
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Certificado PFX</span>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold
                                {{ $pfxExists
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $pfxExists ? 'Activo' : 'Ausente' }}
                            </span>
                        </div>
                        <div class="p-4 space-y-3">
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 font-mono break-all bg-gray-50 dark:bg-gray-800/50 rounded-lg px-3 py-2">
                                {{ $pfxDisk }}/{{ $pfxPath }}
                            </p>
                            <form method="POST" action="{{ route('gmail.inventory.sii.upload.pfx') }}"
                                  enctype="multipart/form-data" x-data="{ fileName: null }">
                                @csrf
                                <label class="flex flex-col items-center gap-2 py-5 px-4 rounded-xl border-2 border-dashed cursor-pointer transition-all duration-150
                                              border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700"
                                       :class="fileName
                                           ? 'border-blue-300 dark:border-blue-600 bg-blue-50/50 dark:bg-blue-950/20'
                                           : 'hover:bg-gray-50 dark:hover:bg-gray-800/30'">
                                    <svg class="w-7 h-7 transition-colors"
                                         :class="fileName ? 'text-blue-400' : 'text-gray-300 dark:text-gray-600'"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <span x-show="!fileName" class="text-xs text-gray-400 text-center">
                                        Arrastra o haz clic para seleccionar<br>
                                        <span class="font-semibold text-gray-500 dark:text-gray-400">.pfx</span> /
                                        <span class="font-semibold text-gray-500 dark:text-gray-400">.p12</span>
                                    </span>
                                    <span x-show="fileName" x-text="fileName"
                                          class="text-xs text-blue-600 dark:text-blue-400 font-medium text-center break-all"></span>
                                    <input type="file" name="pfx_file" accept=".pfx,.p12" required class="hidden"
                                           @change="fileName = $event.target.files[0]?.name ?? null">
                                </label>
                                <button type="submit" x-show="fileName"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="mt-2 w-full py-2 text-xs font-semibold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                                    Subir certificado
                                </button>
                            </form>

                            <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                                <form method="POST" action="{{ route('gmail.inventory.sii.config') }}">
                                    @csrf
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">
                                        Contraseña del certificado
                                        @if($hasPfxPassword)
                                            <span class="ml-1 font-normal text-emerald-500"> — guardada</span>
                                        @else
                                            <span class="ml-1 font-normal text-amber-500"> — no configurada</span>
                                        @endif
                                    </label>
                                    <div class="flex gap-2">
                                        <input type="password" name="dte_signature_pfx_password"
                                               placeholder="{{ $hasPfxPassword ? 'Cambiar contraseña...' : 'Ingresa la contraseña del .pfx' }}"
                                               class="flex-1 px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700
                                                      bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200
                                                      placeholder-gray-400 dark:placeholder-gray-600
                                                      focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition">
                                        <button type="submit"
                                                class="shrink-0 px-4 py-2 text-xs font-semibold rounded-xl
                                                       bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700
                                                       text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 transition-colors">
                                            Guardar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- 🔑 BANCO DE CHILE — TOKEN DE CARTOLA AUTOMÁTICA --}}
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center
                                    {{ $bcTokenActivo ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-red-100 dark:bg-red-900/30' }}">
                                    <svg class="w-3.5 h-3.5 {{ $bcTokenActivo ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 7a2 2 0 012 2m-2 4a2 2 0 012 2m-2-4a2 2 0 012 2m-5-4a3 3 0 11-6 0 3 3 0 016 0zM4 15H2v2a2 2 0 002 2h2m14 0h2a2 2 0 002-2v-2M18 5h2a2 2 0 012 2v2"/>
                                    </svg>
                                </div>
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Token Banco de Chile (Cartola)</span>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold
                                {{ $bcTokenActivo
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-red-100 text-red-650 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $bcTokenActivo ? 'Activo' : 'Inactivo/Expirado' }}
                            </span>
                        </div>
                        <div class="p-4 space-y-3">
                            @if($bcTokenActivo)
                                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-250 dark:border-emerald-800 rounded-xl p-3">
                                    <p class="text-xs font-bold text-emerald-800 dark:text-emerald-400">✅ Conexión con el banco funcionando</p>
                                    <p class="text-[11px] text-emerald-600 dark:text-emerald-550 font-mono mt-0.5 truncate">{{ $bcTokenGuardado }}</p>
                                    <p class="text-[10px] text-emerald-500 mt-0.5">Expira: {{ $bcTokenExpira }}</p>
                                </div>
                            @else
                                <div class="bg-red-50 dark:bg-red-900/20 border border-red-250 dark:border-red-800 rounded-xl p-3">
                                    <p class="text-xs font-bold text-red-800 dark:text-red-400">⚠️ Requiere Atención</p>
                                    <p class="text-[10px] text-red-600 dark:text-red-500 mt-0.5">La sincronización automática de cartolas está pausada porque el token expiró o no ha sido ingresado.</p>
                                </div>
                            @endif

                            <p class="text-xs text-gray-400">
                                Para reactivar, obtén un nuevo token en el portal del banco (apistore.bancochile.cl → "Intentarlo") y pégalo abajo:
                            </p>

                            <div class="space-y-2 pb-3 border-b border-gray-100 dark:border-gray-800">
                                <textarea id="bc-token-input" rows="2"
                                          placeholder="Bearer eyJhbGciOiJSUzI1..."
                                          class="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700
                                                 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 font-mono
                                                 placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none resize-none"></textarea>
                                <button type="button" id="btn-save-bc-token"
                                        class="w-full py-2 text-xs font-semibold rounded-xl text-white bg-indigo-650 hover:bg-indigo-700 transition-colors">
                                    Guardar Token de Cartola
                                </button>
                                <div id="bc-token-status" class="text-xs text-center font-semibold mt-1 hidden"></div>
                            </div>

                            {{-- Credenciales Multi-Ambiente (Odoo & Banco de Chile) --}}
                            <div class="pt-2" x-data="{ activeTab: '{{ $bcEnv }}', previousTab: '{{ $bcEnv }}', showConfirmModal: false, pendingTab: '' }">
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-250 mb-2">⚙️ Configuración de Entornos</p>
                                
                                <form method="POST" action="{{ route('gmail.inventory.sii.config') }}" class="space-y-4">
                                    @csrf

                                    {{-- Selector de Ambiente Activo --}}
                                    <div class="bg-gray-55 dark:bg-gray-800/40 p-2.5 rounded-xl border border-gray-150 dark:border-gray-800 flex items-center justify-between gap-3">
                                        <label class="text-[11px] font-bold text-gray-650 dark:text-gray-300">Ambiente Activo:</label>
                                        <select name="banco_chile_env" x-model="activeTab"
                                                @change="pendingTab = $event.target.value; activeTab = previousTab; showConfirmModal = true;"
                                                class="text-xs font-bold rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-3 py-1 focus:outline-none">
                                            <option value="qa">QA (Ambiente de Pruebas)</option>
                                            <option value="production">🚀 Producción (Real)</option>
                                        </select>
                                    </div>

                                    {{-- Tabs de Navegación Visual --}}
                                    <div class="flex border-b border-gray-200 dark:border-gray-800 text-[11px] font-bold">
                                        <button type="button" 
                                                @click="if (activeTab !== 'qa') { pendingTab = 'qa'; showConfirmModal = true; }"
                                                :class="activeTab === 'qa' ? 'border-b-2 border-indigo-500 text-indigo-650 dark:text-indigo-400' : 'text-gray-400 hover:text-gray-500'"
                                                class="flex-1 py-1.5 text-center transition-colors">
                                            QA / Pruebas
                                        </button>
                                        <button type="button" 
                                                @click="if (activeTab !== 'production') { pendingTab = 'production'; showConfirmModal = true; }"
                                                :class="activeTab === 'production' ? 'border-b-2 border-indigo-500 text-indigo-650 dark:text-indigo-400' : 'text-gray-400 hover:text-gray-500'"
                                                class="flex-1 py-1.5 text-center transition-colors">
                                            🚀 Producción
                                        </button>
                                    </div>

                                    {{-- Contenedor QA --}}
                                    <div x-show="activeTab === 'qa'" class="space-y-3 pt-1">
                                        <p class="text-[10px] uppercase font-black text-amber-600 dark:text-amber-500">Conexión Odoo (QA)</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Odoo URL</label>
                                                <input type="text" name="qa_odoo_url" value="{{ $qaOdooUrl }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Base de Datos</label>
                                                <input type="text" name="qa_odoo_db" value="{{ $qaOdooDb }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="col-span-2">
                                                <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Usuario (Email)</label>
                                                <input type="text" name="qa_odoo_user" value="{{ $qaOdooUser }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Diario ID</label>
                                                <input type="number" name="qa_odoo_journal_id" value="{{ $qaOdooJournalId }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Password Odoo</label>
                                            <input type="password" name="qa_odoo_password" value="{{ $qaOdooPassword }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                        </div>

                                        <p class="text-[10px] uppercase font-black text-amber-600 dark:text-amber-500 pt-2 border-t border-gray-100 dark:border-gray-800">API Banco de Chile (QA)</p>
                                        <div>
                                            <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Client ID</label>
                                            <input type="text" name="qa_bc_client_id" value="{{ $qaBcClientId }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Client Secret</label>
                                            <input type="password" name="qa_bc_client_secret" value="{{ $qaBcClientSecret }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Endpoint API URL</label>
                                            <input type="text" name="qa_bc_api_url" value="{{ $qaBcApiUrl }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                        </div>
                                    </div>

                                    {{-- Contenedor Producción --}}
                                    <div x-show="activeTab === 'production'" class="space-y-3 pt-1">
                                        <p class="text-[10px] uppercase font-black text-indigo-600 dark:text-indigo-400">Conexión Odoo (Producción)</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Odoo URL</label>
                                                <input type="text" name="prod_odoo_url" value="{{ $prodOdooUrl }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Base de Datos</label>
                                                <input type="text" name="prod_odoo_db" value="{{ $prodOdooDb }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="col-span-2">
                                                <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Usuario (Email)</label>
                                                <input type="text" name="prod_odoo_user" value="{{ $prodOdooUser }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Diario ID</label>
                                                <input type="number" name="prod_odoo_journal_id" value="{{ $prodOdooJournalId }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Password Odoo</label>
                                            <input type="password" name="prod_odoo_password" value="{{ $prodOdooPassword }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                        </div>

                                        <p class="text-[10px] uppercase font-black text-indigo-600 dark:text-indigo-400 pt-2 border-t border-gray-100 dark:border-gray-800">API Banco de Chile (Producción)</p>
                                        <div>
                                            <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Client ID</label>
                                            <input type="text" name="prod_bc_client_id" value="{{ $prodBcClientId }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Client Secret</label>
                                            <input type="password" name="prod_bc_client_secret" value="{{ $prodBcClientSecret }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Endpoint API URL</label>
                                            <input type="text" name="prod_bc_api_url" value="{{ $prodBcApiUrl }}" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-750 bg-white dark:bg-gray-850 text-[11px] font-mono focus:outline-none">
                                        </div>
                                    </div>

                                    <button type="submit"
                                            class="w-full py-2 text-xs font-bold rounded-xl text-white bg-indigo-650 hover:bg-indigo-700 transition-colors shadow">
                                        Guardar Configuración de Ambiente
                                    </button>
                                </form>

                                {{-- ⚠️ MODAL DE CONFIRMACIÓN DE CAMBIO DE AMBIENTE --}}
                                <div x-show="showConfirmModal" x-transition.opacity.duration.150ms 
                                     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
                                    {{-- Backdrop oscuro --}}
                                    <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="showConfirmModal = false"></div>
                                    
                                    {{-- Tarjeta de Modal --}}
                                    <div x-show="showConfirmModal"
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         class="relative w-full max-w-md bg-white dark:bg-gray-900 border border-gray-150 dark:border-gray-800 rounded-3xl p-6 shadow-2xl space-y-4">
                                        
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-2xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-black text-gray-900 dark:text-gray-100">Confirmar cambio de ambiente</h3>
                                                <p class="text-xs text-gray-400">¿Estás seguro de que deseas continuar?</p>
                                            </div>
                                        </div>

                                        <p class="text-xs text-gray-650 dark:text-gray-300 leading-relaxed">
                                            Estás a punto de alterar el entorno activo del sistema contable. Al realizar el cambio se modificarán de inmediato los endpoints del Banco de Chile y el destino del Odoo conectado para la importación minuto a minuto de cartolas bancarias.
                                        </p>

                                        <div class="flex gap-2.5 pt-2">
                                            <button type="button" @click="showConfirmModal = false; pendingTab = '';" 
                                                    class="flex-1 py-2.5 text-xs font-bold rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-205 dark:hover:bg-gray-750 transition-all text-center">
                                                Cancelar
                                            </button>
                                            <button type="button" 
                                                    @click="activeTab = pendingTab; previousTab = pendingTab; showConfirmModal = false;" 
                                                    class="flex-1 py-2.5 text-xs font-bold rounded-xl bg-amber-500 hover:bg-amber-650 text-white transition-all shadow text-center">
                                                Confirmar cambio
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
                {{-- ── FIN COL IZQUIERDA ─────────────────────────────── --}}

                {{-- ══════════ COL DERECHA: Alertas + Combustibles ══════ --}}
                <div class="space-y-4">

                    {{-- Encabezado de sección --}}
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Alertas y notificaciones</p>
                            <p class="text-[11px] text-gray-400">Correos y umbrales para alertas automáticas</p>
                        </div>
                    </div>

                    {{-- Alertas de correo --}}
                    @php
                        $emailsList = array_values(array_filter(array_map('trim', explode(',', $lowStockEmails))));
                    @endphp
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden"
                         x-data="emailTagInput()">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Destinatarios de alertas</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">Recibirán el correo cuando el stock de combustible esté bajo</p>
                            </div>
                            <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 px-2 py-0.5 rounded-full"
                                  x-text="emails.length + (emails.length === 1 ? ' correo' : ' correos')">
                            </span>
                        </div>
                        <div class="p-4">
                            <form method="POST" action="{{ route('gmail.inventory.sii.config') }}"
                                  @submit="if(newEmail.trim()) add()">
                                @csrf
                                <input type="hidden" name="low_stock_emails" :value="emails.join(',')">

                                <div class="combo-tags-wrap"
                                     @mousedown="$event.target === $el && $refs.emailInput.focus()">
                                    <template x-for="(email, idx) in emails" :key="email">
                                        <span class="email-tag">
                                            <span x-text="email"></span>
                                            <button type="button" class="email-tag-x"
                                                    @mousedown.prevent="remove(idx)" title="Quitar">&times;</button>
                                        </span>
                                    </template>
                                    <input type="text" x-ref="emailInput" x-model="newEmail"
                                           class="tag-bare-input"
                                           @keydown.enter.prevent="add()"
                                           @keydown.,.prevent="add()"
                                           @keydown.tab="if(newEmail.trim()){ $event.preventDefault(); add(); }"
                                           @blur="if(newEmail.trim()) add()"
                                           :placeholder="emails.length ? 'Agregar otro correo...' : 'Escribe un correo y presiona Enter...'"
                                           autocomplete="off">
                                </div>

                                <div class="mt-2 min-h-[18px]">
                                    <p x-show="errMsg" x-text="errMsg" x-transition
                                       class="text-[11px] text-red-500 dark:text-red-400"></p>
                                    <p x-show="!errMsg && emails.length === 0" x-transition
                                       class="text-[11px] text-amber-600 dark:text-amber-400">
                                        Sin destinatarios — las alertas no se enviarán.
                                    </p>
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <p class="text-[11px] text-gray-400">
                                        <kbd class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 font-mono text-[10px]">Enter</kbd>
                                        o
                                        <kbd class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 font-mono text-[10px]">,</kbd>
                                        para agregar
                                    </p>
                                    <button type="submit"
                                            class="shrink-0 px-4 py-1.5 text-xs font-semibold rounded-xl
                                                   bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                                        Guardar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Mínimos de combustible --}}
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Mínimos de combustible</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">Umbral para activar la alerta por correo</p>
                            </div>
                        </div>
                        <div class="p-4">
                            <form method="POST" action="{{ route('gmail.inventory.sii.config') }}">
                                @csrf
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 p-3">
                                        <div class="flex items-center gap-1.5 mb-2">
                                            <span class="w-2 h-2 rounded-full bg-gray-600 dark:bg-gray-300"></span>
                                            <label class="text-xs font-bold text-gray-700 dark:text-gray-200">Diésel</label>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <input type="number" name="fuel_minimo_diesel" min="0" step="any"
                                                   value="{{ $fuelMinimoDiesel }}"
                                                   class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900
                                                          text-gray-800 dark:text-gray-200 px-3 py-1.5 text-sm font-semibold
                                                          focus:outline-none focus:border-indigo-400 transition tabular-nums">
                                            <span class="text-[11px] text-gray-400 shrink-0">L</span>
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 p-3">
                                        <div class="flex items-center gap-1.5 mb-2">
                                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                            <label class="text-xs font-bold text-gray-700 dark:text-gray-200">Gasolina</label>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <input type="number" name="fuel_minimo_gasolina" min="0" step="any"
                                                   value="{{ $fuelMinimoGasolina }}"
                                                   class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900
                                                          text-gray-800 dark:text-gray-200 px-3 py-1.5 text-sm font-semibold
                                                          focus:outline-none focus:border-indigo-400 transition tabular-nums">
                                            <span class="text-[11px] text-gray-400 shrink-0">L</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-2.5 text-[11px] text-gray-400 leading-relaxed">
                                    Si el stock cae por debajo del valor ingresado, se enviará un correo automático a los destinatarios configurados arriba. Solo se envía una vez por día.
                                </p>
                                <div class="mt-3 flex justify-end">
                                    <button type="submit"
                                            class="px-4 py-1.5 text-xs font-semibold rounded-xl
                                                   bg-orange-500 hover:bg-orange-600 text-white transition-colors">
                                        Guardar mínimos
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
                {{-- ── FIN COL DERECHA ───────────────────────────────── --}}

            </div>
            {{-- ── FIN GRID ──────────────────────────────────────────── --}}

        </div>
    </div>

    <script>
    // Toggle endpoints panel
    document.addEventListener('DOMContentLoaded', () => {
        const details = document.querySelector('details[x-data]');
        const panel   = document.getElementById('endpoints-panel');
        if (details && panel) {
            details.addEventListener('toggle', () => {
                panel.classList.toggle('hidden', !details.open);
            });
        }

        // Guardar token del Banco de Chile
        const btnSaveBc = document.getElementById('btn-save-bc-token');
        if (btnSaveBc) {
            btnSaveBc.addEventListener('click', async function () {
                const token = document.getElementById('bc-token-input').value.trim();
                const status = document.getElementById('bc-token-status');
                if (!token) { alert('Ingresa un token antes de guardar.'); return; }

                this.disabled = true; this.textContent = 'Guardando...';
                status.className = 'text-xs text-center mt-1 text-gray-500';
                status.classList.remove('hidden');
                status.textContent = 'Conectando...';

                try {
                    const res = await fetch('{{ route("bancochile.token") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ token })
                    });
                    const data = await res.json();
                    if (data.success) {
                        status.textContent = '✅ Token guardado. Expira: ' + data.expires_at;
                        status.className = 'text-xs text-center mt-1 text-emerald-600 font-semibold';
                        document.getElementById('bc-token-input').value = '';
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        status.textContent = '❌ ' + (data.error || 'Error al guardar.');
                        status.className = 'text-xs text-center mt-1 text-red-500 font-semibold';
                    }
                } catch (e) {
                    status.textContent = '❌ Error de red: ' + e.message;
                    status.className = 'text-xs text-center mt-1 text-red-500 font-semibold';
                } finally {
                    this.disabled = false; this.textContent = 'Guardar Token de Cartola';
                }
            });
        }
    });

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
