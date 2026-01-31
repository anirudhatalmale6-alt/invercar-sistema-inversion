<?php
/**
 * InverCar - Sistema de envío de emails
 * Compatible con Hostalia (SMTP con autenticación SSL)
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

    $mensaje = getEmailTemplate($nombre,
        "Gracias por registrarte en InverCar. Para completar tu registro y acceder a tu cuenta, por favor verifica tu email haciendo clic en el siguiente botón:",
        $enlace,
        "Verificar mi cuenta",
        "Este enlace expirará en 24 horas. Si no has creado una cuenta en InverCar, puedes ignorar este mensaje."
    );

    return enviarEmail($email, $asunto, $mensaje);
}

/**
 * Enviar email de recuperación de contraseña
 */
function enviarEmailRecuperacion($email, $nombre, $token) {
    $enlace = SITE_URL . "/cliente/recuperar.php?token=" . urlencode($token);

    $asunto = "Recupera tu contraseña - InverCar";

    $mensaje = getEmailTemplate($nombre,
        "Has solicitado restablecer tu contraseña. Haz clic en el siguiente botón para crear una nueva contraseña:",
        $enlace,
        "Restablecer contraseña",
        "Este enlace expirará en 24 horas. Si no has solicitado restablecer tu contraseña, puedes ignorar este mensaje."
    );

    return enviarEmail($email, $asunto, $mensaje);
}

/**
 * Template HTML para emails
 */
function getEmailTemplate($nombre, $texto, $enlace, $botonTexto, $textoFinal) {
    return "
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
            <p>{$texto}</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='{$enlace}' class='btn'>{$botonTexto}</a>
            </p>
            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p style='word-break: break-all; color: #0d9b5c;'>{$enlace}</p>
            <p>{$textoFinal}</p>
            <div class='footer'>
                <p>&copy; " . date('Y') . " InverCar. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Enviar email usando SMTP con SSL (puerto 465)
 */
function enviarEmailSMTP($destinatario, $asunto, $mensajeHtml) {
    $host = 'ssl://' . SMTP_HOST;
    $port = 465; // Puerto SSL
    $username = SMTP_USER;
    $password = SMTP_PASS;
    $from = SMTP_FROM;
    $fromName = SMTP_FROM_NAME;

    $errno = 0;
    $errstr = '';

    // Conectar al servidor SMTP con SSL
    $socket = @stream_socket_client("$host:$port", $errno, $errstr, 30);

    if (!$socket) {
        if (DEBUG_MODE) {
            error_log("SMTP Error: No se pudo conectar a $host:$port - $errstr ($errno)");
        }
        return false;
    }

    // Función para leer respuesta
    $getResponse = function() use ($socket) {
        $response = '';
        stream_set_timeout($socket, 10);
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ' || substr($line, 3, 1) == "\r") break;
        }
        return trim($response);
    };

    // Función para enviar comando
    $sendCommand = function($command, $expectedCode = null) use ($socket, $getResponse) {
        fwrite($socket, $command . "\r\n");
        $response = $getResponse();
        if (DEBUG_MODE && $expectedCode && substr($response, 0, 3) != $expectedCode) {
            error_log("SMTP Command: $command");
            error_log("SMTP Response: $response");
        }
        return $response;
    };

    try {
        // Leer saludo inicial
        $response = $getResponse();
        if (substr($response, 0, 3) != '220') {
            throw new Exception("Greeting failed: $response");
        }

        // EHLO
        $response = $sendCommand("EHLO " . gethostname(), '250');
        if (substr($response, 0, 3) != '250') {
            throw new Exception("EHLO failed: $response");
        }

        // AUTH LOGIN
        $response = $sendCommand("AUTH LOGIN", '334');
        if (substr($response, 0, 3) != '334') {
            throw new Exception("AUTH failed: $response");
        }

        // Enviar usuario
        $response = $sendCommand(base64_encode($username), '334');
        if (substr($response, 0, 3) != '334') {
            throw new Exception("Username failed: $response");
        }

        // Enviar contraseña
        $response = $sendCommand(base64_encode($password), '235');
        if (substr($response, 0, 3) != '235') {
            throw new Exception("Password failed: $response");
        }

        // MAIL FROM
        $response = $sendCommand("MAIL FROM:<$from>", '250');
        if (substr($response, 0, 3) != '250') {
            throw new Exception("MAIL FROM failed: $response");
        }

        // RCPT TO
        $response = $sendCommand("RCPT TO:<$destinatario>", '250');
        if (substr($response, 0, 3) != '250') {
            throw new Exception("RCPT TO failed: $response");
        }

        // DATA
        $response = $sendCommand("DATA", '354');
        if (substr($response, 0, 3) != '354') {
            throw new Exception("DATA failed: $response");
        }

        // Construir mensaje
        $message = "From: $fromName <$from>\r\n";
        $message .= "To: $destinatario\r\n";
        $message .= "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($mensajeHtml));
        $message .= "\r\n.";

        $response = $sendCommand($message, '250');
        if (substr($response, 0, 3) != '250') {
            throw new Exception("Message send failed: $response");
        }

        // QUIT
        $sendCommand("QUIT");
        fclose($socket);

        return true;

    } catch (Exception $e) {
        if (DEBUG_MODE) {
            error_log("SMTP Exception: " . $e->getMessage());
        }
        @fclose($socket);
        return false;
    }
}

/**
 * Enviar email genérico (intenta SMTP primero, luego mail())
 */
function enviarEmail($destinatario, $asunto, $mensajeHtml) {
    // Intentar primero con SMTP SSL
    $enviado = enviarEmailSMTP($destinatario, $asunto, $mensajeHtml);

    // Si falla SMTP, intentar con mail() nativo
    if (!$enviado) {
        if (DEBUG_MODE) {
            error_log("SMTP falló, intentando mail() nativo...");
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . '>',
            'Reply-To: ' . SMTP_FROM,
            'X-Mailer: PHP/' . phpversion()
        ];
        $enviado = @mail($destinatario, $asunto, $mensajeHtml, implode("\r\n", $headers));

        if (!$enviado && DEBUG_MODE) {
            error_log("mail() nativo también falló para: $destinatario");
        }
    }

    return $enviado;
}
