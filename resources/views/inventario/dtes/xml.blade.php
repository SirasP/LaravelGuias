<x-app-layout>
    <x-slot name="header">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Ver XML completo — Gmail ID: {{ $messageId }}
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4">

            <div class="mb-4 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                <div><span class="font-semibold">Asunto:</span> {{ $subject }}</div>
                <div><span class="font-semibold">Desde:</span> {{ $from }}</div>
                <div><span class="font-semibold">Fecha:</span> {{ $date }}</div>
                @if(!empty($filename))
                    <div><span class="font-semibold">Archivo:</span> {{ $filename }}</div>
                @endif
            </div>

            <div class="mb-4 flex gap-2">
                <button onclick="copiar()" class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm">
                    Copiar XML
                </button>

                <a href="{{ route('inventario.dtes.gmail') }}"
                    class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-sm">
                    Volver
                </a>
            </div>

            <pre class="p-4 rounded-xl border border-gray-200 dark:border-gray-700
                        bg-gray-50 dark:bg-gray-950 text-xs overflow-auto">
<code class="language-xml">{{ $xml }}</code>
            </pre>
        </div>
    </div>

    <!-- Highlight.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/xml.min.js"></script>

    <script>
        hljs.highlightAll();


        function copiar() {
            const xml = {!! \Illuminate\Support\Js::from($xml, JSON_INVALID_UTF8_SUBSTITUTE) !!};
            navigator.clipboard.writeText(xml);
            alert('XML copiado ✅');
        }


    </script>
</x-app-layout>