<?php
// Envía un correo HTML vía SendGrid API (cURL). Retorna true si 202 Accepted.
function enviar_correo(string $to, string $to_nombre, string $subject, string $html): bool {
    $api_key    = get_env('SENDGRID_API_KEY');
    $from_email = get_env('SENDGRID_FROM', 'no-reply@breadcontrol.adso.pro');
    $from_name  = get_env('SENDGRID_FROM_NOMBRE', 'BreadControl');

    if (!$api_key) {
        log_error(['msg' => 'mailer: SENDGRID_API_KEY no configurada']);
        return false;
    }

    $payload = json_encode([
        'personalizations' => [['to' => [['email' => $to, 'name' => $to_nombre]]]],
        'from'             => ['email' => $from_email, 'name'  => $from_name],
        'subject'          => $subject,
        'content'          => [['type' => 'text/html', 'value' => $html]],
    ]);

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ],
    ]);

    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($status !== 202) {
        log_error(['msg' => 'mailer: SendGrid error', 'status' => $status, 'response' => $body, 'curl_error' => $error, 'to' => $to]);
        return false;
    }

    return true;
}

// Template HTML consistente para todos los correos de BreadControl
function correo_html(string $titulo, string $cuerpo_html, string $pie = ''): string {
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#faf3ea;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#faf3ea;padding:30px 10px;">
<tr><td align="center">
<table width="100%" style="max-width:500px;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(148,91,53,.12);">
  <tr><td style="background:linear-gradient(135deg,#945b35,#c67124);padding:28px 30px;text-align:center;">
    <div style="font-size:26px;font-weight:800;color:#fff;letter-spacing:.02em;">BreadControl</div>
    <div style="font-size:13px;color:rgba(255,255,255,.75);margin-top:4px;">' . htmlspecialchars($titulo) . '</div>
  </td></tr>
  <tr><td style="background:#fff;padding:28px 30px;border:1px solid #e8ddd0;border-top:none;">
    ' . $cuerpo_html . '
    ' . ($pie ? '<hr style="border:none;border-top:1px solid #ede7df;margin:20px 0;"><p style="font-size:11px;color:#b0967e;text-align:center;margin:0;">' . $pie . '</p>' : '') . '
  </td></tr>
</table>
</td></tr></table>
</body></html>';
}

// Template específico para correos de código de verificación
function correo_codigo_html(string $nombre, string $codigo, string $motivo, int $minutos = 10): string {
    $cuerpo = '
    <p style="color:#3d2010;font-size:15px;margin:0 0 12px;">Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
    <p style="color:#6b4c30;font-size:14px;line-height:1.6;margin:0 0 20px;">' . htmlspecialchars($motivo) . '</p>
    <div style="background:#faf3ea;border:2px solid #c67124;border-radius:12px;padding:20px;text-align:center;margin:0 0 20px;">
      <div style="font-size:38px;font-weight:800;letter-spacing:10px;color:#945b35;font-family:\'Courier New\',monospace;">' . htmlspecialchars($codigo) . '</div>
    </div>
    <p style="color:#999;font-size:12px;text-align:center;margin:0;">Este código expira en <strong>' . $minutos . ' minutos</strong>. No lo compartas con nadie.</p>';

    $pie = 'Si no solicitaste este cambio, ignora este mensaje. — BreadControl';
    return correo_html('Código de verificación', $cuerpo, $pie);
}
