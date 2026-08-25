<x-mail-layout :titulo="$titulo">
    <p style="margin:0 0 14px;font-size:16px;font-weight:bold;">{{ $titulo }}</p>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
        {{ filled($notifiable->name ?? null) ? 'Hola '.$notifiable->name.':' : 'Hola:' }}
        {{ $mensaje }}
    </p>

    @if($purchaseRequest)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;margin:0 0 18px;">
            <tr>
                <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;">
                    <span style="color:#64748b;">Borrador</span><br><strong>{{ $purchaseRequest->folio }}</strong>
                </td>
            </tr>
            <tr>
                <td style="padding:10px 14px;font-size:13px;">
                    <span style="color:#64748b;">Partidas reconocidas</span><br>{{ $purchaseRequest->items()->count() }}
                </td>
            </tr>
        </table>
    @endif

    @if(filled($avisos))
        <p style="margin:0 0 8px;font-size:13px;font-weight:bold;">Revisa estos puntos</p>
        <ul style="margin:0 0 18px;padding-left:18px;font-size:13px;line-height:1.7;color:#334155;">
            @foreach($avisos as $aviso)
                <li>{{ $aviso }}</li>
            @endforeach
        </ul>
    @endif

    <p style="margin:0 0 8px;font-size:13px;color:#334155;line-height:1.6;">
        El asistente sólo prepara un borrador. Nada se envía a revisión hasta que tú lo confirmes.
    </p>

    <p style="margin:14px 0 20px;">
        <a href="{{ $url }}"
            style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:8px;font-size:14px;font-weight:bold;">
            {{ $purchaseRequest ? 'Revisar el borrador' : 'Ver el documento' }}
        </a>
    </p>

    <p style="margin:0;font-size:12px;color:#64748b;line-height:1.5;word-break:break-all;">
        Si el botón no funciona, copia este enlace: {{ $url }}
    </p>
</x-mail-layout>
