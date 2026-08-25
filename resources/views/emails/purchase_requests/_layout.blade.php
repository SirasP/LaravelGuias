{{-- Layout de correo del módulo. Sin imágenes externas ni CSS remoto: los
     clientes de correo los bloquean y el mensaje debe leerse igual. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'Solicitud de compra' }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">

                    <tr>
                        <td style="padding:20px 24px;border-bottom:1px solid #e2e8f0;">
                            <p style="margin:0;font-size:15px;font-weight:bold;color:#0f172a;">Agrícola EHE SpA</p>
                            <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Solicitudes de compra</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px;">
                            {{ $slot }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                            <p style="margin:0;font-size:11px;color:#64748b;line-height:1.5;">
                                Este es un aviso automático del sistema interno de Agrícola EHE SpA.
                                No respondas a este correo: para comentar la solicitud, entra al sistema.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
