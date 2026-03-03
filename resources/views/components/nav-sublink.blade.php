{{--
    x-nav-sublink — Enlace interno para x-nav-item

    Props:
      href   string — URL del enlace
      active bool   — Si el enlace coincide con la ruta actual
      color  string — Color (ej. 'indigo', 'violet')
--}}
@props([
    'href'   => '#',
    'active' => false,
    'color'  => 'gray',
])

<a href="{{ $href }}" @click="mobileOpen = false"
    class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors
           @if($active)
               text-{{$color}}-700 dark:text-{{$color}}-300 font-semibold bg-{{$color}}-50 dark:bg-{{$color}}-900/20
           @else
               text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50
           @endif"
>
    {{ $slot }}
</a>
