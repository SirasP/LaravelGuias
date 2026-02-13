<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">

            <div class="space-y-1">
                <nav class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                    <a href="{{ route('excel_out_transfers.index') }}"
                        class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        Excel OUT Transfers
                    </a>
                    <span>/</span>
                    <span class="text-gray-600 dark:text-gray-300">Detalle</span>
                </nav>

                <div class="text-xl font-black tracking-tight text-gray-800 dark:text-gray-100">
                    Guía
                    <span class="font-mono text-indigo-600 dark:text-indigo-400">
                        {{ $transfer->guia_entrega }}
                    </span>
                </div>
            </div>

            <a href="{{ route('excel_out_transfers.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl
               border border-gray-200 dark:border-gray-700
               bg-white dark:bg-gray-900
               text-sm font-semibold text-gray-600 dark:text-gray-300
               hover:bg-gray-50 dark:hover:bg-gray-800
               transition active:scale-95">
                ← Volver
            </a>
        </div>
    </x-slot>

    {{-- ESTILOS --}}
    <style>
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 18px;
            transition: box-shadow .2s, transform .15s;
        }

        .dark .card {
            background: #161c2c;
            border-color: #1e2a3b;
        }

        .card:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, .05);
            transform: translateY(-1px);
        }

        .dark .card:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, .35);
        }

        .dt {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .dt thead tr {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .dark .dt thead tr {
            background: #111827;
            border-bottom-color: #1e2a3b;
        }

        .dt th {
            padding: 12px 16px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .dt td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }

        .dark .dt td {
            border-bottom-color: #1a2232;
            color: #cbd5e1;
        }

        .dt tbody tr:last-child td {
            border-bottom: none;
        }

        .dt tbody tr:hover td {
            background: #f8fafc;
        }

        .dark .dt tbody tr:hover td {
            background: #1a2436;
        }
    </style>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 space-y-8">

            {{-- Cabecera info --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <div class="card">
                    <div class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold mb-1">
                        Contacto
                    </div>
                    <div class="font-semibold text-gray-800 dark:text-gray-100">
                        {{ $transfer->contacto ?? '—' }}
                    </div>
                </div>

                <div class="card">
                    <div class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold mb-1">
                        Chofer
                    </div>
                    <div class="font-semibold text-gray-800 dark:text-gray-100">
                        {{ $transfer->chofer ?? '—' }}
                    </div>
                </div>

                <div class="card">
                    <div class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold mb-1">
                        Patente
                    </div>
                    <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">
                        {{ $transfer->patente ?? '—' }}
                    </div>
                </div>

                <div class="card">
                    <div class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold mb-1">
                        Referencia
                    </div>
                    <div class="font-medium text-gray-700 dark:text-gray-300">
                        {{ $transfer->referencia ?? '—' }}
                    </div>
                </div>

            </div>

            {{-- Tabla --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700
                        overflow-hidden bg-white dark:bg-gray-900 shadow-sm">

                <table class="dt">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="w-40 text-right">Cantidad</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($transfer->lines as $line)
                            <tr>
                                <td class="font-medium">
                                    {{ $line->producto ?? '—' }}
                                </td>
                                <td class="text-right font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                                    {{ number_format((float) $line->cantidad, 3, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center py-10 text-gray-400">
                                    Sin ítems registrados
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>

        </div>
    </div>
</x-app-layout>