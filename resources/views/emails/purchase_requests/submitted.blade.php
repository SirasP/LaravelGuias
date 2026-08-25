@php
    $titulo = $esCancelacion ? 'Piden anular una solicitud' : 'Solicitud pendiente de revisión';
@endphp
<x-mail-layout :titulo="$titulo">
    <p style="margin:0 0 14px;font-size:16px;font-weight:bold;">{{ $titulo }}</p>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
        {{ filled($notifiable->name ?? null) ? 'Hola '.$notifiable->name.':' : 'Hola:' }}
        @if($esCancelacion)
            <strong>{{ $actor->name }}</strong> pide anular la solicitud
            <strong>{{ $purchaseRequest->folio }}</strong>. Necesita tu decisión.
        @else
            <strong>{{ $actor->name }}</strong> envió una solicitud de compra que espera tu revisión.
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="border:1px solid #e2e8f0;border-radius:8px;margin:0 0 20px;">
        <tr>
            <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;">
                <span style="color:#64748b;">Folio</span><br>
                <strong>{{ $purchaseRequest->folio }}</strong>
            </td>
        </tr>
        <tr>
            <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;">
                <span style="color:#64748b;">Área</span><br>
                {{ $purchaseRequest->department }}
            </td>
        </tr>
        <tr>
            <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;">
                <span style="color:#64748b;">Fecha requerida</span><br>
                {{ optional($purchaseRequest->required_date)->format('d-m-Y') }}
                @if($purchaseRequest->priority === 'urgent')
                    <strong style="color:#b91c1c;">· URGENTE</strong>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:10px 14px;font-size:13px;">
                <span style="color:#64748b;">Motivo</span><br>
                {{ \Illuminate\Support\Str::limit($purchaseRequest->reason, 220) }}
            </td>
        </tr>
    </table>

    <p style="margin:0 0 20px;">
        <a href="{{ $url }}"
            style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:8px;font-size:14px;font-weight:bold;">
            Revisar la solicitud
        </a>
    </p>

    <p style="margin:0;font-size:12px;color:#64748b;line-height:1.5;word-break:break-all;">
        Si el botón no funciona, copia este enlace: {{ $url }}
    </p>
</x-mail-layout>
