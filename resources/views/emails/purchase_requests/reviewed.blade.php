<x-mail-layout titulo="Resultado de tu solicitud">
    <p style="margin:0 0 14px;font-size:16px;font-weight:bold;">
        Tu solicitud {{ $purchaseRequest->folio }} fue {{ $outcome }}
    </p>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
        {{ filled($notifiable->name ?? null) ? 'Hola '.$notifiable->name.':' : 'Hola:' }} <strong>{{ $actor->name }}</strong> revisó tu solicitud de compra
        y la marcó como <strong>{{ $outcome }}</strong>.
    </p>

    @if(filled($comment))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
            <tr>
                <td style="padding:12px 14px;background:#f8fafc;border-left:3px solid #94a3b8;font-size:13px;line-height:1.6;">
                    <span style="color:#64748b;display:block;margin-bottom:4px;">Comentario de quien revisó</span>
                    {{ $comment }}
                </td>
            </tr>
        </table>
    @endif

    @if(filled($purchaseRequest->requested_corrections))
        <p style="margin:0 0 8px;font-size:13px;font-weight:bold;">Puntos a corregir</p>
        <ul style="margin:0 0 18px;padding-left:18px;font-size:13px;line-height:1.7;color:#334155;">
            @foreach($purchaseRequest->requested_corrections as $punto)
                <li>{{ \App\Enums\PurchaseRequestCorrection::labelFor($punto) }}</li>
            @endforeach
        </ul>
    @endif

    <p style="margin:0 0 20px;">
        <a href="{{ $url }}"
            style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:8px;font-size:14px;font-weight:bold;">
            Ver la solicitud
        </a>
    </p>

    <p style="margin:0;font-size:12px;color:#64748b;line-height:1.5;word-break:break-all;">
        Si el botón no funciona, copia este enlace: {{ $url }}
    </p>
</x-mail-layout>
