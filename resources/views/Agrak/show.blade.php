<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Agrak · Registro #{{ $item->id }}
            </div>

            <a href="{{ route('agrak.index') }}"
                class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline">
                ← Volver
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 space-y-6">

            {{-- =========================
            RESUMEN
            ========================== --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-5">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                    <div>
                        <div class="text-xs text-gray-500">Código BIN</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ $item->codigo_bin }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Fecha / Hora</div>
                        <div class="font-medium">
                            {{ \Carbon\Carbon::parse($item->fecha_registro)->format('d-m-Y') }}
                            {{ $item->hora_registro }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Campo · Cuartel</div>
                        <div class="font-medium">
                            {{ $item->nombre_campo ?? '—' }}
                            <span class="text-gray-400">·</span>
                            {{ $item->cuartel ?? '—' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Especie / Variedad</div>
                        <div class="font-medium">
                            {{ $item->especie ?? '—' }}
                            <span class="text-gray-400">·</span>
                            {{ $item->variedad ?? '—' }}
                        </div>
                    </div>

                </div>
            </div>

            {{-- =========================
            DETALLE
            ========================== --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    {{-- Operación --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Operación
                        </h3>

                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Usuario</dt>
                                <dd>{{ $item->usuario ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">ID usuario</dt>
                                <dd>{{ $item->id_usuario ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Cuadrilla</dt>
                                <dd>{{ $item->cuadrilla ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Vuelta</dt>
                                <dd>{{ $item->vuelta ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Transporte --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Transporte
                        </h3>

                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Máquina</dt>
                                <dd>{{ $item->maquina ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Chofer</dt>
                                <dd>{{ $item->nombre_chofer ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Patente</dt>
                                <dd>{{ $item->patente_camion ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Bandejas</dt>
                                <dd>{{ $item->numero_bandejas_palet ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Exportación --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Exportación
                        </h3>

                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Exportadora 1</dt>
                                <dd>{{ $item->exportadora_1 ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Exportadora 2</dt>
                                <dd>{{ $item->exportadora_2 ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Sello 1</dt>
                                <dd>{{ $item->numero_sello_1 ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Sello 2</dt>
                                <dd>{{ $item->numero_sello_2 ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                </div>
            </div>

            {{-- =========================
            OBSERVACIÓN
            ========================== --}}
            @if($item->observacion)
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-5">
                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Observación
                    </div>
                    <div class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap">
                        {{ $item->observacion }}
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>