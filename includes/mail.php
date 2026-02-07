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
 * Enviar email de notificación al administrador
 */
function enviarEmailAdminNuevoCliente($nombreCliente, $emailCliente, $capital) {
    $emailAdmin = SMTP_FROM; // El admin recibe emails en el mismo email de envío

    $asunto = "Nuevo cliente registrado en InverCar";

    $mensaje = getEmailTemplate('Administrador',
        "Se ha registrado un nuevo cliente en InverCar que está pendiente de activación:<br><br>
        <strong>Nombre:</strong> {$nombreCliente}<br>
        <strong>Email:</strong> {$emailCliente}<br>
        <strong>Capital previsto:</strong> " . number_format($capital, 0, ',', '.') . " €<br><br>
        El cliente ya ha completado el proceso de registro y está esperando que lo actives desde el panel de administración.",
        SITE_URL . "/admin/clientes.php",
        "Ver en Panel Admin",
        "Este es un mensaje automático del sistema InverCar."
    );

    return enviarEmail($emailAdmin, $asunto, $mensaje);
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
    $logoUrl = SITE_URL . '/assets/images/logo-invercar.png';
    return "
    <html>
    <head>
        <style>
            body { font-family: 'Raleway', Arial, sans-serif; background-color: #1a1a2e; padding: 20px; margin: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #16213e; border-radius: 0; padding: 40px; border: 1px solid #d4a84b; }
            .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(212, 168, 75, 0.3); font-size: 28px; font-weight: bold; color: #d4a84b; }
            .header img { max-width: 200px; height: auto; }
            p { color: #ffffff; line-height: 1.6; }
            .btn { display: inline-block; background: linear-gradient(135deg, #d4a84b 0%, #c9a227 100%); color: #1a1a2e !important; padding: 15px 30px; text-decoration: none; border-radius: 0; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
            .btn:hover { background: linear-gradient(135deg, #c9a227 0%, #d4a84b 100%); }
            .link { color: #d4a84b; word-break: break-all; }
            .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(212, 168, 75, 0.3); color: #888; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='{$logoUrl}' alt='InverCar' onerror=\"this.style.display='none';this.parentNode.innerHTML='<span style=color:#d4a84b;font-size:28px;font-weight:bold;>INVERCAR</span>'\" />
            </div>
            <p>Hola <strong style='color: #d4a84b;'>{$nombre}</strong>,</p>
            <p>{$texto}</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='{$enlace}' class='btn'>{$botonTexto}</a>
            </p>
            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p style='word-break: break-all;' class='link'>{$enlace}</p>
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
    $smtpHost = SMTP_HOST;
    $port = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $username = SMTP_USER;
    $password = SMTP_PASS;
    $from = SMTP_FROM;
    $fromName = SMTP_FROM_NAME;

    $errno = 0;
    $errstr = '';

    // Puerto 465 = SSL directo, Puerto 587 = STARTTLS
    if ($port == 465) {
        $host = 'ssl://' . $smtpHost;
    } else {
        $host = $smtpHost;
    }

    // Conectar al servidor SMTP (timeout 5s)
    $socket = @stream_socket_client("$host:$port", $errno, $errstr, 5);

    if (!$socket) {
        error_log("SMTP Error: No se pudo conectar a $host:$port - $errstr ($errno)");
        return false;
    }

    // Función para leer respuesta
    $getResponse = function() use ($socket) {
        $response = '';
        stream_set_timeout($socket, 5);
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
        if ($expectedCode && substr($response, 0, 3) != $expectedCode) {
            error_log("SMTP Command: " . (strpos($command, 'AUTH') !== false || strlen($command) > 100 ? substr($command, 0, 30) . '...' : $command));
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

        // STARTTLS para puerto 587
        if ($port == 587) {
            $response = $sendCommand("STARTTLS", '220');
            if (substr($response, 0, 3) != '220') {
                throw new Exception("STARTTLS failed: $response");
            }
            // Activar TLS
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                throw new Exception("TLS encryption failed");
            }
            // Re-enviar EHLO después de TLS
            $response = $sendCommand("EHLO " . gethostname(), '250');
            if (substr($response, 0, 3) != '250') {
                throw new Exception("EHLO after TLS failed: $response");
            }
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
        error_log("SMTP Exception para $destinatario: " . $e->getMessage());
        @fclose($socket);
        return false;
    }
}

/**
 * Función de diagnóstico para verificar la conexión SMTP
 * Usar: testSMTPConnection() desde un script PHP
 */
function testSMTPConnection() {
    $smtpHost = SMTP_HOST;
    $port = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $username = SMTP_USER;

    error_log("=== TEST SMTP ===");
    error_log("Host: $smtpHost");
    error_log("Puerto: $port");
    error_log("Usuario: $username");

    $errno = 0;
    $errstr = '';

    if ($port == 465) {
        $host = 'ssl://' . $smtpHost;
    } else {
        $host = $smtpHost;
    }

    error_log("Conectando a: $host:$port");

    $socket = @stream_socket_client("$host:$port", $errno, $errstr, 10);

    if (!$socket) {
        error_log("ERROR de conexión: $errstr ($errno)");
        return ['success' => false, 'error' => "No se pudo conectar: $errstr ($errno)"];
    }

    error_log("Conexión establecida, leyendo saludo...");

    stream_set_timeout($socket, 10);
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) == ' ' || substr($line, 3, 1) == "\r") break;
    }
    error_log("Saludo: " . trim($response));

    fwrite($socket, "EHLO " . gethostname() . "\r\n");
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) == ' ' || substr($line, 3, 1) == "\r") break;
    }
    error_log("EHLO response: " . trim($response));

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    error_log("=== FIN TEST SMTP ===");
    return ['success' => true, 'message' => 'Conexión SMTP OK'];
}

/**
 * Enviar email genérico (intenta SMTP primero, luego mail())
 */
function enviarEmail($destinatario, $asunto, $mensajeHtml) {
    error_log("InverCar Email: Enviando a $destinatario - Asunto: $asunto");

    // Verificar que el email sea válido
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        error_log("InverCar Email: Email inválido: $destinatario");
        return false;
    }

    // Intentar primero con SMTP (con reintento)
    $maxReintentos = 2;
    $enviado = false;

    for ($intento = 1; $intento <= $maxReintentos; $intento++) {
        error_log("InverCar Email: Intento SMTP $intento de $maxReintentos para $destinatario");
        $enviado = enviarEmailSMTP($destinatario, $asunto, $mensajeHtml);

        if ($enviado) {
            error_log("InverCar Email: OK enviado via SMTP a $destinatario (intento $intento)");
            return true;
        }

        // Si falló y hay más intentos, esperar 1 segundo
        if ($intento < $maxReintentos) {
            error_log("InverCar Email: SMTP falló, esperando 1s antes del reintento...");
            sleep(1);
        }
    }

    // Si falla SMTP después de reintentos, intentar con mail() nativo
    error_log("InverCar Email: SMTP falló después de $maxReintentos intentos para $destinatario, intentando mail() nativo...");

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . '>',
        'Reply-To: ' . SMTP_FROM,
        'X-Mailer: PHP/' . phpversion()
    ];
    $enviado = @mail($destinatario, $asunto, $mensajeHtml, implode("\r\n", $headers));

    if ($enviado) {
        error_log("InverCar Email: OK enviado via mail() nativo a $destinatario");
    } else {
        error_log("InverCar Email: mail() nativo también falló para: $destinatario");
    }

    return $enviado;
}

/**
 * Enviar email de notificación de nuevo vehículo a clientes
 * Usa imágenes embebidas (CID) para máxima compatibilidad
 */
function enviarEmailNuevoVehiculo($cliente, $vehiculo) {
    $email = $cliente['email'];
    $nombre = $cliente['nombre'];

    // Sin acentos para evitar problemas de encoding
    $asunto = "Nuevo vehiculo disponible en InverCar";

    // Calcular datos del vehículo
    $diasPrevistos = intval($vehiculo['dias_previstos'] ?? 75);
    $fechaCompra = !empty($vehiculo['fecha_compra']) ? new DateTime($vehiculo['fecha_compra']) : new DateTime();
    $fechaPrevista = (clone $fechaCompra)->modify("+{$diasPrevistos} days");

    // Preparar imágenes para embeber
    $logoPath = ROOT_PATH . '/assets/images/logo-invercar.png';
    $fotoPath = !empty($vehiculo['foto']) ? ROOT_PATH . '/' . $vehiculo['foto'] : '';

    // Usar CID para imágenes embebidas
    $logoSrc = 'cid:logo_invercar';
    $fotoSrc = !empty($fotoPath) && file_exists($fotoPath) ? 'cid:foto_vehiculo' : '';

    $mensaje = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background-color: #1a1a2e; padding: 20px; margin: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #16213e; padding: 40px; border: 1px solid #d4a84b; }
            .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(212, 168, 75, 0.3); }
            .header img { max-width: 200px; height: auto; }
            p { color: #ffffff; line-height: 1.6; }
            .vehicle-card { background: #1a1a2e; border: 1px solid rgba(212, 168, 75, 0.3); margin: 20px 0; overflow: hidden; }
            .vehicle-status { background: #f97316; color: #fff; padding: 5px 15px; font-size: 12px; font-weight: 600; text-transform: uppercase; display: inline-block; }
            .vehicle-image { width: 100%; background: #0a0a14; }
            .vehicle-image img { width: 100%; height: auto; display: block; }
            .vehicle-body { padding: 20px; }
            .vehicle-title { font-size: 18px; font-weight: 700; color: #ffffff; margin-bottom: 5px; }
            .vehicle-subtitle { font-size: 13px; color: #888; margin-bottom: 15px; }
            .vehicle-prices { width: 100%; }
            .vehicle-price-item { display: inline-block; width: 48%; text-align: center; padding: 10px 0; }
            .vehicle-price-label { font-size: 11px; color: #888; margin-bottom: 5px; }
            .vehicle-price-value { font-size: 16px; font-weight: 700; }
            .vehicle-price-value.venta { color: #22c55e; }
            .vehicle-price-value.fecha { color: #ffffff; }
            .vehicle-timeline { padding: 15px 20px; border-top: 1px solid rgba(212, 168, 75, 0.3); }
            .vehicle-days { font-size: 14px; font-weight: 600; color: #22c55e; }
            .vehicle-expected { font-size: 12px; color: #888; float: right; }
            .btn { display: inline-block; background: #d4a84b; color: #1a1a2e !important; padding: 15px 30px; text-decoration: none; font-weight: bold; text-transform: uppercase; }
            .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(212, 168, 75, 0.3); color: #888; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='{$logoSrc}' alt='InverCar' width='200' />
            </div>
            <p>Hola <strong style='color: #d4a84b;'>{$nombre}</strong>,</p>
            <p>Te informamos que hay un nuevo vehiculo disponible en nuestra cartera de inversion:</p>

            <div class='vehicle-card'>
                <div style='padding: 10px;'>
                    <span class='vehicle-status'>ESPERA</span>
                </div>
                " . ($fotoSrc ? "<div class='vehicle-image'><img src='{$fotoSrc}' alt='{$vehiculo['marca']} {$vehiculo['modelo']}' /></div>" : "") . "
                <div class='vehicle-body'>
                    <div class='vehicle-title'>{$vehiculo['marca']} {$vehiculo['modelo']}</div>
                    <div class='vehicle-subtitle'>
                        " . ($vehiculo['version'] ?? '') . " - {$vehiculo['anio']}" . ($vehiculo['kilometros'] ? " - " . number_format($vehiculo['kilometros'], 0, ',', '.') . " km" : "") . "
                    </div>
                    <div class='vehicle-prices'>
                        <div class='vehicle-price-item'>
                            <div class='vehicle-price-label'>Venta Prevista</div>
                            <div class='vehicle-price-value venta'>" . number_format($vehiculo['valor_venta_previsto'], 2, ',', '.') . " EUR</div>
                        </div>
                        <div class='vehicle-price-item'>
                            <div class='vehicle-price-label'>Fecha Prevista</div>
                            <div class='vehicle-price-value fecha'>" . $fechaPrevista->format('d/m/Y') . "</div>
                        </div>
                    </div>
                </div>
                <div class='vehicle-timeline'>
                    <span class='vehicle-days'>0 dias</span>
                    <span class='vehicle-expected'>Previsto: {$diasPrevistos} dias</span>
                </div>
            </div>

            <p style='text-align: center; margin: 30px 0;'>
                <a href='" . SITE_URL . "/cliente/panel.php' class='btn'>Ver en Mi Panel</a>
            </p>

            <div class='footer'>
                <p>Si no deseas recibir mas notificaciones de nuevos vehiculos, puedes desactivarlas en tu panel de configuracion.</p>
                <p>" . date('Y') . " InverCar. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Preparar adjuntos embebidos
    $adjuntos = [];
    if (file_exists($logoPath)) {
        $adjuntos[] = [
            'path' => $logoPath,
            'cid' => 'logo_invercar',
            'name' => 'logo-invercar.png',
            'type' => 'image/png'
        ];
    }
    if (!empty($fotoPath) && file_exists($fotoPath)) {
        $ext = strtolower(pathinfo($fotoPath, PATHINFO_EXTENSION));
        $mimeType = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
        $adjuntos[] = [
            'path' => $fotoPath,
            'cid' => 'foto_vehiculo',
            'name' => 'vehiculo.' . $ext,
            'type' => $mimeType
        ];
    }

    return enviarEmailConAdjuntos($email, $asunto, $mensaje, $adjuntos);
}

/**
 * Enviar email con imágenes embebidas (MIME multipart/related)
 */
function enviarEmailConAdjuntos($destinatario, $asunto, $mensajeHtml, $adjuntos = []) {
    error_log("InverCar Email: Enviando con adjuntos a $destinatario - Asunto: $asunto");

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        error_log("InverCar Email: Email invalido: $destinatario");
        return false;
    }

    // Si no hay adjuntos, usar el método normal
    if (empty($adjuntos)) {
        return enviarEmail($destinatario, $asunto, $mensajeHtml);
    }

    // Crear email MIME multipart/related
    $boundary = 'INVERCAR_' . md5(time() . rand());
    $boundaryAlt = 'INVERCAR_ALT_' . md5(time() . rand());

    $from = SMTP_FROM;
    $fromName = SMTP_FROM_NAME;

    // Construir mensaje MIME
    $mensaje = "From: {$fromName} <{$from}>\r\n";
    $mensaje .= "To: {$destinatario}\r\n";
    $mensaje .= "Subject: {$asunto}\r\n";
    $mensaje .= "MIME-Version: 1.0\r\n";
    $mensaje .= "Content-Type: multipart/related; boundary=\"{$boundary}\"\r\n";
    $mensaje .= "\r\n";

    // Parte HTML
    $mensaje .= "--{$boundary}\r\n";
    $mensaje .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mensaje .= "Content-Transfer-Encoding: base64\r\n";
    $mensaje .= "\r\n";
    $mensaje .= chunk_split(base64_encode($mensajeHtml));
    $mensaje .= "\r\n";

    // Adjuntos embebidos
    foreach ($adjuntos as $adj) {
        if (file_exists($adj['path'])) {
            $contenido = file_get_contents($adj['path']);
            $mensaje .= "--{$boundary}\r\n";
            $mensaje .= "Content-Type: {$adj['type']}; name=\"{$adj['name']}\"\r\n";
            $mensaje .= "Content-Transfer-Encoding: base64\r\n";
            $mensaje .= "Content-ID: <{$adj['cid']}>\r\n";
            $mensaje .= "Content-Disposition: inline; filename=\"{$adj['name']}\"\r\n";
            $mensaje .= "\r\n";
            $mensaje .= chunk_split(base64_encode($contenido));
            $mensaje .= "\r\n";
        }
    }

    $mensaje .= "--{$boundary}--\r\n";

    // Enviar via SMTP directo (sin base64 adicional en el cuerpo)
    return enviarEmailSMTPRaw($destinatario, $mensaje);
}

/**
 * Enviar mensaje SMTP raw (ya formateado con MIME)
 */
function enviarEmailSMTPRaw($destinatario, $mensajeCompleto) {
    $smtpHost = SMTP_HOST;
    $port = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $username = SMTP_USER;
    $password = SMTP_PASS;
    $from = SMTP_FROM;

    $errno = 0;
    $errstr = '';

    if ($port == 465) {
        $host = 'ssl://' . $smtpHost;
    } else {
        $host = $smtpHost;
    }

    $socket = @stream_socket_client("$host:$port", $errno, $errstr, 10);

    if (!$socket) {
        error_log("SMTP Raw Error: No se pudo conectar a $host:$port - $errstr ($errno)");
        return false;
    }

    $getResponse = function() use ($socket) {
        $response = '';
        stream_set_timeout($socket, 10);
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ' || substr($line, 3, 1) == "\r") break;
        }
        return trim($response);
    };

    $sendCommand = function($command, $expectedCode = null) use ($socket, $getResponse) {
        fwrite($socket, $command . "\r\n");
        $response = $getResponse();
        if ($expectedCode && substr($response, 0, 3) != $expectedCode) {
            error_log("SMTP Raw: $command -> $response");
        }
        return $response;
    };

    try {
        $response = $getResponse();
        if (substr($response, 0, 3) != '220') {
            throw new Exception("Greeting failed: $response");
        }

        $response = $sendCommand("EHLO " . gethostname(), '250');
        if (substr($response, 0, 3) != '250') {
            throw new Exception("EHLO failed: $response");
        }

        if ($port == 587) {
            $response = $sendCommand("STARTTLS", '220');
            if (substr($response, 0, 3) != '220') {
                throw new Exception("STARTTLS failed: $response");
            }
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                throw new Exception("TLS encryption failed");
            }
            $response = $sendCommand("EHLO " . gethostname(), '250');
        }

        $response = $sendCommand("AUTH LOGIN", '334');
        if (substr($response, 0, 3) != '334') {
            throw new Exception("AUTH failed: $response");
        }

        $response = $sendCommand(base64_encode($username), '334');
        if (substr($response, 0, 3) != '334') {
            throw new Exception("Username failed: $response");
        }

        $response = $sendCommand(base64_encode($password), '235');
        if (substr($response, 0, 3) != '235') {
            throw new Exception("Password failed: $response");
        }

        $response = $sendCommand("MAIL FROM:<$from>", '250');
        if (substr($response, 0, 3) != '250') {
            throw new Exception("MAIL FROM failed: $response");
        }

        $response = $sendCommand("RCPT TO:<$destinatario>", '250');
        if (substr($response, 0, 3) != '250') {
            throw new Exception("RCPT TO failed: $response");
        }

        $response = $sendCommand("DATA", '354');
        if (substr($response, 0, 3) != '354') {
            throw new Exception("DATA failed: $response");
        }

        // Enviar mensaje completo (ya formateado)
        fwrite($socket, $mensajeCompleto . "\r\n.\r\n");
        $response = $getResponse();
        if (substr($response, 0, 3) != '250') {
            throw new Exception("Message send failed: $response");
        }

        $sendCommand("QUIT");
        fclose($socket);

        error_log("InverCar Email: OK enviado con adjuntos a $destinatario");
        return true;

    } catch (Exception $e) {
        error_log("SMTP Raw Exception para $destinatario: " . $e->getMessage());
        @fclose($socket);
        return false;
    }
}

/**
 * Notificar a todos los clientes activos sobre un nuevo vehículo
 */
function notificarNuevoVehiculoAClientes($vehiculo) {
    $db = getDB();

    // Obtener clientes activos con registro completo y que acepten notificaciones
    $clientes = $db->query("
        SELECT id, nombre, email
        FROM clientes
        WHERE activo = 1
        AND registro_completo = 1
        AND email_verificado = 1
        AND (recibir_notificaciones = 1 OR recibir_notificaciones IS NULL)
    ")->fetchAll();

    $enviados = 0;
    $errores = 0;
    $detallesErrores = [];

    error_log("InverCar: Iniciando envío de notificación de vehículo a " . count($clientes) . " clientes");

    foreach ($clientes as $index => $cliente) {
        error_log("InverCar: Enviando email " . ($index + 1) . "/" . count($clientes) . " a " . $cliente['email']);

        if (enviarEmailNuevoVehiculo($cliente, $vehiculo)) {
            $enviados++;
            error_log("InverCar: OK - Email enviado a " . $cliente['email']);
        } else {
            $errores++;
            $detallesErrores[] = $cliente['email'];
            error_log("InverCar: ERROR - Falló envío a " . $cliente['email']);
        }

        // Pausa de 2 segundos entre emails para evitar rate limiting del servidor SMTP
        if ($index < count($clientes) - 1) {
            sleep(2);
        }
    }

    error_log("InverCar: Envío completado. Enviados: $enviados, Errores: $errores");
    if (!empty($detallesErrores)) {
        error_log("InverCar: Emails fallidos: " . implode(', ', $detallesErrores));
    }

    return ['enviados' => $enviados, 'errores' => $errores, 'total' => count($clientes)];
}
