<?php
/**
 * InverCar - Sistema de envío de emails
 * Compatible con Hostalia (mail() nativo o SMTP)
 */

if (!defined('INVERCAR')) {
    exit('Acceso no permitido');
}

/**
 * Enviar email de verificación
 */
function enviarEmailVerificacion($email, $nombre, $token) {
    $enlace = SITE_URL . "/cliente/verificar.php?token=" . urlencode($token);

    $asunto = "Verifica tu cuenta en InverCar";

    $mensaje = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 0; padding: 40px; }
            .header { text-align: center; margin-bottom: 30px; }
            .logo { font-size: 28px; font-weight: bold; color: #1a2332; }
            .logo span { color: #0d9b5c; }
            .btn { display: inline-block; background: #0d9b5c; color: white; padding: 15px 30px; text-decoration: none; border-radius: 0; font-weight: bold; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>Inver<span>Car</span></div>
            </div>
            <p>Hola <strong>{$nombre}</strong>,</p>
            <p>Gracias por registrarte en InverCar. Para completar tu registro y acceder a tu cuenta, por favor verifica tu email haciendo clic en el siguiente botón:</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='{$enlace}' class='btn'>Verificar mi cuenta</a>
            </p>
            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p style='word-break: break-all; color: #0d9b5c;'>{$enlace}</p>
            <p>Este enlace expirará en 24 horas.</p>
            <p>Si no has creado una cuenta en InverCar, puedes ignorar este mensaje.</p>
            <div class='footer'>
                <p>&copy; " . date('Y') . " InverCar. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return enviarEmail($email, $asunto, $mensaje);
}

/**
 * Enviar email genérico
 */
function enviarEmail($destinatario, $asunto, $mensajeHtml) {
    // Headers para email HTML
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . '>',
        'Reply-To: ' . SMTP_FROM,
        'X-Mailer: PHP/' . phpversion()
    ];

    // Intentar enviar con mail() nativo (compatible con Hostalia)
    $enviado = @mail($destinatario, $asunto, $mensajeHtml, implode("\r\n", $headers));

    if (!$enviado && DEBUG_MODE) {
        error_log("Error enviando email a: $destinatario");
    }

    return $enviado;
}

/**
 * Enviar email de recuperación de contraseña
 */
function enviarEmailRecuperacion($email, $nombre, $token) {
    $enlace = SITE_URL . "/cliente/recuperar.php?token=" . urlencode($token);

    $asunto = "Recupera tu contraseña - InverCar";

    $mensaje = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 0; padding: 40px; }
            .header { text-align: center; margin-bottom: 30px; }
            .logo { font-size: 28px; font-weight: bold; color: #1a2332; }
            .logo span { color: #0d9b5c; }
            .btn { display: inline-block; background: #0d9b5c; color: white; padding: 15px 30px; text-decoration: none; border-radius: 0; font-weight: bold; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>Inver<span>Car</span></div>
            </div>
            <p>Hola <strong>{$nombre}</strong>,</p>
            <p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente botón para crear una nueva contraseña:</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='{$enlace}' class='btn'>Restablecer contraseña</a>
            </p>
            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p style='word-break: break-all; color: #0d9b5c;'>{$enlace}</p>
            <p>Este enlace expirará en 24 horas.</p>
            <p>Si no has solicitado restablecer tu contraseña, puedes ignorar este mensaje.</p>
            <div class='footer'>
                <p>&copy; " . date('Y') . " InverCar. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return enviarEmail($email, $asunto, $mensaje);
}
