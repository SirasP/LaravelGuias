<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nueva {{ ucfirst($comentario->tipo) }}</title>
  <style>
    body { margin:0; padding:0; background:#f3f4f6; font-family:'Segoe UI',system-ui,sans-serif; }
    .wrap { max-width:580px; margin:32px auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.07); }
    .top { background:linear-gradient(135deg,#166534,#22c55e); padding:32px 36px 28px; }
    .top h1 { margin:0; color:#fff; font-size:22px; font-weight:700; letter-spacing:-.3px; }
    .top p  { margin:6px 0 0; color:#bbf7d0; font-size:13px; }
    .body   { padding:28px 36px; }
    .badge  { display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700; margin-bottom:20px; }
    .badge.sugerencia { background:#dbeafe; color:#1e40af; }
    .badge.reclamo    { background:#fee2e2; color:#991b1b; }
    .label  { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#9ca3af; margin-bottom:6px; }
    .box    { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:16px 18px; font-size:14px; color:#374151; line-height:1.7; white-space:pre-wrap; }
    .meta   { margin-top:20px; display:flex; gap:24px; }
    .meta-item { font-size:12px; color:#6b7280; }
    .meta-item strong { display:block; color:#111827; font-size:13px; margin-bottom:1px; }
    .btn-wrap { text-align:center; margin:28px 0 8px; }
    .btn    { display:inline-block; padding:12px 28px; background:#166534; color:#fff !important; text-decoration:none; border-radius:9px; font-size:13px; font-weight:700; letter-spacing:.2px; }
    .footer { background:#f9fafb; border-top:1px solid #e5e7eb; padding:16px 36px; font-size:11px; color:#9ca3af; text-align:center; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <h1>{{ $comentario->tipo === 'sugerencia' ? '💡' : '📢' }} Nueva {{ ucfirst($comentario->tipo) }}</h1>
      <p>Huerto Agrícola EHE &mdash; {{ $comentario->created_at->format('d/m/Y \a \l\a\s H:i') }}</p>
    </div>
    <div class="body">
      <span class="badge {{ $comentario->tipo }}">{{ strtoupper($comentario->tipo) }}</span>

      <div class="label">Mensaje recibido</div>
      <div class="box">{{ $comentario->comentario }}</div>

      <div class="meta">
        <div class="meta-item">
          <strong>#{{ $comentario->id }}</strong>
          ID de registro
        </div>
        <div class="meta-item">
          <strong>{{ $comentario->created_at->format('d/m/Y') }}</strong>
          Fecha
        </div>
        <div class="meta-item">
          <strong>{{ $comentario->created_at->format('H:i') }}</strong>
          Hora
        </div>
      </div>

      <div class="btn-wrap">
        <a href="{{ url('/sugerencias/admin') }}" class="btn">Ver panel de administración →</a>
      </div>
    </div>
    <div class="footer">
      Este correo fue generado automáticamente por el sistema de Huerto Agrícola EHE.<br>
      No responder a este mensaje.
    </div>
  </div>
</body>
</html>
