<div class="text-left">

    <h2 class="text-lg font-semibold mb-2">
        📄 XML recibido
    </h2>

    @if($movimiento->requiere_revision)
        <div class="mb-3 p-2 bg-red-100 text-red-700 rounded text-sm">
            ⚠ Este XML requiere revisión (Ley 18.502)
        </div>
    @endif

    <pre class="bg-gray-900 text-green-300 text-xs p-3 rounded max-h-[60vh] overflow-auto">
{{ htmlspecialchars($xml) }}
    </pre>

</div>
