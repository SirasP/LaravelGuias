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

            {{-- ── Flash ──────────────────────────────────────────────── --}}
            @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="flex items-center gap-3 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
                <button @click="show = false" class="ml-auto text-emerald-400 hover:text-emerald-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            @endif

            {{-- ── Banner Estado SII ───────────────────────────────────── --}}
            <div class="rounded-2xl border overflow-hidden
                {{ $isRealMode
                    ? 'border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/10'
                    : 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10' }}">
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

                        <details class="group relative">
                            <summary class="flex items-center gap-1 text-xs font-medium text-gray-500 dark:text-gray-400 cursor-pointer select-none list-none hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                                <svg class="w-3 h-3 transition-transform duration-150 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                </svg>
                                Endpoints
                            </summary>
                            <div class="absolute right-0 top-full mt-2 z-10 w-80 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg p-3 space-y-1.5">
                                @foreach(['Seed' => $seedUrl, 'Token' => $tokenUrl, 'Recepción' => $recepcionUrl, 'Estado' => $estadoUrl] as $key => $val)
                                <div class="flex gap-2 text-[11px]">
                                    <span class="w-16 font-semibold text-gray-600 dark:text-gray-300 shrink-0">{{ $key }}</span>
                                    <span class="font-mono text-gray-400 dark:text-gray-500 break-all">{{ $val ?: '—' }}</span>
                                </div>
                                @endforeach
                            </div>
                        </details>
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
