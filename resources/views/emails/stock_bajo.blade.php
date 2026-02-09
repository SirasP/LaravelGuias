<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerta de Stock Bajo</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f7;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f4f4f7;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <!-- Contenedor principal -->
                <table role="presentation"
                    style="width: 600px; max-width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">

                    <!-- Header -->
                    <tr>
                        <td
                            style="background: linear-gradient(135deg, {{ $nivelCritico ? '#dc2626' : '#f59e0b' }} 0%, {{ $nivelCritico ? '#991b1b' : '#d97706' }} 100%); padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">
                                {{ $nivelCritico ? '🚨' : '⚠️' }} Alerta de Stock
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.95;">
                                {{ $nivelCritico ? 'NIVEL CRÍTICO' : 'Stock Bajo' }}
                            </p>
                        </td>
                    </tr>

                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 40px 30px;">

                            <!-- Mensaje principal -->
                            <p style="margin: 0 0 25px 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                El siguiente producto requiere atención inmediata debido a su bajo nivel de inventario:
                            </p>

                            <!-- Card del producto -->
                            <table role="presentation"
                                style="width: 100%; border-collapse: collapse; background-color: #f9fafb; border-radius: 8px; overflow: hidden; margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 25px;">

                                        <!-- Nombre del producto -->
                                        <h2
                                            style="margin: 0 0 20px 0; color: #111827; font-size: 20px; font-weight: 600;">
                                            {{ $producto }}
                                        </h2>

                                        <!-- Info adicional -->
                                        @if($codigoProducto)
                                            <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">
                                                <strong>Código:</strong> {{ $codigoProducto }}
                                            </p>
                                        @endif

                                        @if($categoria)
                                            <p style="margin: 0 0 20px 0; color: #6b7280; font-size: 14px;">
                                                <strong>Categoría:</strong> {{ $categoria }}
                                            </p>
                                        @endif

                                        <!-- Divisor -->
                                        <div style="height: 1px; background-color: #e5e7eb; margin: 20px 0;"></div>

                                        <!-- Métricas de stock -->
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="padding: 12px 0; width: 50%;">
                                                    <p
                                                        style="margin: 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                                                        Stock Actual
                                                    </p>
                                                    <p
                                                        style="margin: 8px 0 0 0; color: {{ $nivelCritico ? '#dc2626' : '#f59e0b' }}; font-size: 32px; font-weight: 700; line-height: 1;">
                                                        {{ number_format($stock, 2) }}
                                                    </p>
                                                </td>
                                                <td style="padding: 12px 0; width: 50%; text-align: right;">
                                                    <p
                                                        style="margin: 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                                                        Stock Mínimo
                                                    </p>
                                                    <p
                                                        style="margin: 8px 0 0 0; color: #111827; font-size: 32px; font-weight: 700; line-height: 1;">
                                                        {{ number_format($stockMinimo, 2) }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Barra de progreso -->
                                        <div style="margin-top: 20px;">
                                            <div
                                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                <span style="color: #6b7280; font-size: 13px; font-weight: 500;">Nivel
                                                    de stock</span>
                                                <span
                                                    style="color: {{ $nivelCritico ? '#dc2626' : '#f59e0b' }}; font-size: 16px; font-weight: 700;">{{ $porcentajeStock }}%</span>
                                            </div>
                                            <div
                                                style="width: 100%; height: 12px; background-color: #e5e7eb; border-radius: 6px; overflow: hidden;">
                                                <div
                                                    style="width: {{ min($porcentajeStock, 100) }}%; height: 100%; background: linear-gradient(90deg, {{ $nivelCritico ? '#dc2626' : '#f59e0b' }}, {{ $nivelCritico ? '#991b1b' : '#d97706' }}); transition: width 0.3s ease;">
                                                </div>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            </table>

                            <!-- Mensaje de acción -->
                            @if($nivelCritico)
                                <div
                                    style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px 20px; border-radius: 4px; margin-bottom: 25px;">
                                    <p style="margin: 0; color: #991b1b; font-size: 14px; line-height: 1.6;">
                                        <strong>⚠️ Acción requerida:</strong> El stock ha alcanzado un nivel crítico. Se
                                        recomienda realizar un pedido urgente para evitar desabastecimiento.
                                    </p>
                                </div>
                            @else
                                <div
                                    style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px 20px; border-radius: 4px; margin-bottom: 25px;">
                                    <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.6;">
                                        <strong>📋 Recomendación:</strong> Considere realizar un pedido próximamente para
                                        mantener los niveles óptimos de inventario.
                                    </p>
                                </div>
                            @endif

                            <!-- Botón de acción -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td align="center" style="padding: 10px 0;">
                                        <a href="{{ config('app.url') }}"
                                            style="display: inline-block; padding: 14px 32px; background-color: {{ $nivelCritico ? '#dc2626' : '#f59e0b' }}; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; transition: background-color 0.2s;">
                                            Ver en el Sistema
                                        </a>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 25px 30px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding-bottom: 15px;">
                                        <p style="margin: 0; color: #6b7280; font-size: 13px;">
                                            <strong>📅 Fecha de alerta:</strong> {{ $fechaAlerta }}
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.6;">
                                            Este es un mensaje automático generado por el sistema FuelControl. Por favor
                                            no responda a este correo.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

                <!-- Footer adicional -->
                <table role="presentation"
                    style="width: 600px; max-width: 100%; border-collapse: collapse; margin-top: 20px;">
                    <tr>
                        <td align="center" style="padding: 0 30px;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                © {{ date('Y') }} FuelControl. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>

</html>