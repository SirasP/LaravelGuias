{{--
    x-breadcrumbs — Breadcrumbs de navegación

    Props:
      items  array  — Array de ['label' => string, 'url' => string|null]
                      El último item no necesita url (es la página actual)

    Uso:
      <x-breadcrumbs :items="[
          ['label' => 'Dashboard', 'url' => route('index')],
          ['label' => 'Agrak'],
      ]" />
--}}
@props(['items' => []])

@if(count($items) > 1)
    <nav class="flex items-center gap-1.5 text-xs text-gray-400 mt-0.5" aria-label="Breadcrumb">
        @foreach($items as $i => $item)
            @if($i > 0)
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            @endif

            @if(isset($item['url']) && $i < count($items) - 1)
                <a
                    href="{{ $item['url'] }}"
                    class="hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                >{{ $item['label'] }}</a>
            @else
                <span class="text-gray-600 dark:text-gray-300 font-medium">{{ $item['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif
