<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-lg font-semibold text-gray-800">
                DTE Mail
            </h2>

            <div class="flex items-center gap-2">
                <form method="GET" class="flex items-center gap-2">
                    <input 
                        name="q" 
                        value="{{ $q }}" 
                        placeholder="Buscar guía / productor / patente..."
                        class="w-80 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">

                    <button type="submit" class="px-4 py-2 rounded-lg bg-gray-800 text-white text-sm hover:bg-gray-900 transition">
                        Buscar
                    </button>

                    <a href="{{ route('guias.comfrut.index') }}"
                        class="px-4 py-2 rounded-lg bg-white border border-gray-300 text-sm hover:bg-gray-50 transition">
                        Limpiar
                    </a>
                </form>

                <a href="{{ route('guias.comfrut.export-php', ['q' => $q]) }}"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Exportar
                </a>

                <a href="{{ route('guias.comfrut.import.form') }}"
                    class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    Importar XML
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('ok'))
                <div class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-green-800 text-sm font-medium">{{ session('ok') }}</span>
                </div>
            @endif

            {{-- Stats --}}
            <div class="mb-4 flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1.5 rounded-full bg-indigo-100 text-indigo-800 text-xs font-medium">
                    <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                    </svg>
                    Total: <span class="font-bold ml-1">{{ number_format($total, 0, ',', '.') }}</span>
                </span>
            </div>

            {{-- Tabla --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                                <th class="px-4  text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Guía</th>
                                <th class="px-4  text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Fecha</th>
                                <th class="px-4  text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Productor</th>
                                <th class="px-4  text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Patente</th>
                                <th class="px-4  text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Chofer</th>
                                <th class="px-4  text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Bandejas</th>
                                <th class="px-4  text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Pallets</th>
                                <th class="px-4  text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Otros</th>
                                <th class="px-4  text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Total</th>
                                <th class="px-4  text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($guias as $g)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3.5">
                                        <span class="font-mono font-semibold text-gray-900">{{ $g->guia_numero }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-gray-600">
                                        {{ $g->fecha_guia?->format('d-m-Y') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-gray-900">
                                        <div class="max-w-xs truncate" title="{{ $g->productor }}">
                                            {{ $g->productor ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="uppercase font-medium text-gray-700">{{ $g->patente ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="capitalize text-gray-700">
                                            {{ Str::lower($g->detalles->first()->nombre_chofer ?? '—') }}
                                        </span>
                                    </td>
                                    
                                    @php
                                        $bandejas = $g->detalles->filter(fn($d) => 
                                            preg_match('/BANDEJ|BDJA/i', $d->nombre_item)
                                        )->sum('cantidad');
                                        
                                        $pallets = $g->detalles->filter(fn($d) => 
                                            preg_match('/PALLET|PALE/i', $d->nombre_item)
                                        )->sum('cantidad');
                                        
                                        $otros = $g->detalles->filter(fn($d) => 
                                            !preg_match('/BANDEJ|BDJA|PALLET|PALE/i', $d->nombre_item)
                                        )->sum('cantidad');
                                    @endphp

                                    <td class="px-4 py-3.5 text-right">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $bandejas > 0 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $bandejas > 0 ? number_format($bandejas, 0, ',', '.') : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pallets > 0 ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $pallets > 0 ? number_format($pallets, 0, ',', '.') : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $otros > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $otros > 0 ? number_format($otros, 0, ',', '.') : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <span class="font-semibold text-gray-900">
                                            {{ number_format($g->cantidad_total, 2, ',', '.') }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3.5 text-center">
                                        <a href="{{ route('guias.comfrut.show', $g->id) }}"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-md text-xs font-medium hover:bg-indigo-100 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-16 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p class="mt-4 text-sm text-gray-500">No hay guías COMFRUT importadas.</p>
                                        <a href="{{ route('guias.comfrut.import.form') }}" 
                                            class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 transition">
                                            Importar primera guía
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            @if($guias->hasPages())
                <div class="mt-6">
                    {{ $guias->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>