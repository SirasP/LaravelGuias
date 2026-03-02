{{--
    x-section-label — Separador de sección con punto de color y línea

    Props:
      dot  string — Clase Tailwind del punto de color (ej. "bg-blue-500")
--}}
@props([
    'dot' => 'bg-slate-400',
])

<div class="section-label">
    <span class="inline-flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full {{ $dot }} flex-shrink-0"></span>
        {{ $slot }}
    </span>
</div>
