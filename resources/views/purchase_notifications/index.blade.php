<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Avisos de solicitudes</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Lo que necesita tu atención</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-8xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav', ['status' => null])

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">
                <div>
                    <h2 class="font-extrabold text-slate-900 dark:text-white">
                        Avisos
                        @if($unreadCount > 0)
                            <span class="ml-1 rounded-full bg-blue-600 px-2 py-0.5 text-xs font-bold text-white">{{ $unreadCount }} sin leer</span>
                        @endif
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Sólo dentro del sistema. Este módulo no envía correos.
                    </p>
                </div>

                @if($unreadCount > 0)
                    <form method="POST" action="{{ route('purchase_notifications.read_all') }}">
                        @csrf
                        <button type="submit"
                            class="min-h-11 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                            Marcar todo como leído
                        </button>
                    </form>
                @endif
            </div>

            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($notifications as $notification)
                    @php
                        $sinLeer = $notification->read_at === null;
                        $datos = $notification->data;
                        $esDecision = data_get($datos, 'kind') === 'purchase_request.reviewed';
                    @endphp
                    <li class="{{ $sinLeer ? 'bg-blue-50/60 dark:bg-blue-950/20' : '' }}">
                        <form method="POST" action="{{ route('purchase_notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="flex w-full items-start gap-3 px-4 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:px-5">
                                <span class="mt-1 flex h-2.5 w-2.5 shrink-0 rounded-full {{ $sinLeer ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600' }}"
                                    aria-hidden="true"></span>

                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-bold text-slate-900 dark:text-white">
                                            {{ data_get($datos, 'title', 'Aviso') }}
                                        </span>
                                        @if($sinLeer)
                                            <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">Sin leer</span>
                                        @endif
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                            {{ $esDecision ? 'Revisión' : 'Pendiente de decisión' }}
                                        </span>
                                    </span>

                                    <span class="mt-1 block text-sm text-slate-600 dark:text-slate-300">
                                        {{ data_get($datos, 'message') }}
                                    </span>

                                    <span class="mt-1 block text-xs text-slate-400 dark:text-slate-500">
                                        {{ $notification->created_at?->format('d-m-Y H:i') }}
                                        @if(filled(data_get($datos, 'folio'))) · {{ data_get($datos, 'folio') }} @endif
                                    </span>
                                </span>

                                <span class="mt-1 shrink-0 text-xs font-bold text-blue-600 dark:text-blue-400" aria-hidden="true">Ver →</span>
                            </button>
                        </form>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        No tienes avisos por ahora.
                    </li>
                @endforelse
            </ul>

            @if($notifications->hasPages())
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                    {{ $notifications->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
