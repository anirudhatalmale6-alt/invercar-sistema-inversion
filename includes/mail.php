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
        error_log("SMTP Exception: " . $e->getMessage());
        @fclose($socket);
        return false;
    }
}

/**
 * Enviar email genérico (intenta SMTP primero, luego mail())
 */
function enviarEmail($destinatario, $asunto, $mensajeHtml) {
    error_log("InverCar Email: Enviando a $destinatario - Asunto: $asunto");

    // Intentar primero con SMTP
    $enviado = enviarEmailSMTP($destinatario, $asunto, $mensajeHtml);

    if ($enviado) {
        error_log("InverCar Email: OK enviado via SMTP a $destinatario");
        return true;
    }

    // Si falla SMTP, intentar con mail() nativo
    error_log("InverCar Email: SMTP falló para $destinatario, intentando mail() nativo...");

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

/**
 * Enviar email de notificación de nuevo vehículo a clientes
 */
function enviarEmailNuevoVehiculo($cliente, $vehiculo) {
    $email = $cliente['email'];
    $nombre = $cliente['nombre'];

    $asunto = "Nuevo vehículo disponible en InverCar";

    // Calcular datos del vehículo
    $diasPrevistos = intval($vehiculo['dias_previstos'] ?? 75);
    $fechaCompra = !empty($vehiculo['fecha_compra']) ? new DateTime($vehiculo['fecha_compra']) : new DateTime();
    $fechaPrevista = (clone $fechaCompra)->modify("+{$diasPrevistos} days");

    $fotoUrl = !empty($vehiculo['foto']) ? SITE_URL . '/' . $vehiculo['foto'] : '';
    $logoUrl = SITE_URL . '/assets/images/logo-invercar.png';

    $mensaje = "
    <html>
    <head>
        <style>
            body { font-family: 'Raleway', Arial, sans-serif; background-color: #1a1a2e; padding: 20px; margin: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #16213e; border-radius: 0; padding: 40px; border: 1px solid #d4a84b; }
            .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(212, 168, 75, 0.3); }
            .header img { max-width: 200px; height: auto; }
            p { color: #ffffff; line-height: 1.6; }
            .vehicle-card { background: #1a1a2e; border: 1px solid rgba(212, 168, 75, 0.3); margin: 20px 0; overflow: hidden; }
            .vehicle-status { background: #f97316; color: #fff; padding: 5px 15px; font-size: 12px; font-weight: 600; text-transform: uppercase; display: inline-block; }
            .vehicle-image { width: 100%; height: 200px; background: #0a0a14; }
            .vehicle-image img { width: 100%; height: 200px; object-fit: cover; }
            .vehicle-body { padding: 20px; }
            .vehicle-title { font-size: 18px; font-weight: 700; color: #ffffff; margin-bottom: 5px; }
            .vehicle-subtitle { font-size: 13px; color: #888; margin-bottom: 15px; }
            .vehicle-prices { display: table; width: 100%; }
            .vehicle-price-item { display: table-cell; width: 50%; text-align: center; padding: 10px; }
            .vehicle-price-label { font-size: 11px; color: #888; margin-bottom: 5px; }
            .vehicle-price-value { font-size: 16px; font-weight: 700; }
            .vehicle-price-value.venta { color: #22c55e; }
            .vehicle-price-value.fecha { color: #ffffff; }
            .vehicle-timeline { padding: 15px 20px; border-top: 1px solid rgba(212, 168, 75, 0.3); }
            .vehicle-days { font-size: 14px; font-weight: 600; color: #22c55e; }
            .vehicle-expected { font-size: 12px; color: #888; }
            .btn { display: inline-block; background: linear-gradient(135deg, #d4a84b 0%, #c9a227 100%); color: #1a1a2e !important; padding: 15px 30px; text-decoration: none; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
            .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(212, 168, 75, 0.3); color: #888; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='{$logoUrl}' alt='InverCar' onerror=\"this.style.display='none';this.parentNode.innerHTML='<span style=color:#d4a84b;font-size:28px;font-weight:bold;>INVERCAR</span>'\" />
            </div>
            <p>Hola <strong style='color: #d4a84b;'>{$nombre}</strong>,</p>
            <p>Te informamos que hay un nuevo vehículo disponible en nuestra cartera de inversión:</p>

            <div class='vehicle-card'>
                <div style='padding: 10px;'>
                    <span class='vehicle-status'>ESPERA</span>
                </div>
                " . ($fotoUrl ? "<div class='vehicle-image'><img src='{$fotoUrl}' alt='{$vehiculo['marca']} {$vehiculo['modelo']}' /></div>" : "") . "
                <div class='vehicle-body'>
                    <div class='vehicle-title'>{$vehiculo['marca']} {$vehiculo['modelo']}</div>
                    <div class='vehicle-subtitle'>
                        " . ($vehiculo['version'] ?? '') . " · {$vehiculo['anio']}" . ($vehiculo['kilometros'] ? " · " . number_format($vehiculo['kilometros'], 0, ',', '.') . " km" : "") . "
                    </div>
                    <div class='vehicle-prices'>
                        <div class='vehicle-price-item'>
                            <div class='vehicle-price-label'>Venta Prevista</div>
                            <div class='vehicle-price-value venta'>" . number_format($vehiculo['valor_venta_previsto'], 2, ',', '.') . " €</div>
                        </div>
                        <div class='vehicle-price-item'>
                            <div class='vehicle-price-label'>Fecha Prevista</div>
                            <div class='vehicle-price-value fecha'>" . $fechaPrevista->format('d/m/Y') . "</div>
                        </div>
                    </div>
                </div>
                <div class='vehicle-timeline'>
                    <span class='vehicle-days'>0 días</span>
                    <span class='vehicle-expected' style='float: right;'>Previsto: {$diasPrevistos} días</span>
                </div>
            </div>

            <p style='text-align: center; margin: 30px 0;'>
                <a href='" . SITE_URL . "/cliente/panel.php' class='btn'>Ver en Mi Panel</a>
            </p>

            <div class='footer'>
                <p>Si no deseas recibir más notificaciones de nuevos vehículos, puedes desactivarlas en tu panel de configuración.</p>
                <p>&copy; " . date('Y') . " InverCar. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return enviarEmail($email, $asunto, $mensaje);
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

    foreach ($clientes as $cliente) {
        if (enviarEmailNuevoVehiculo($cliente, $vehiculo)) {
            $enviados++;
        } else {
            $errores++;
        }
    }

    return ['enviados' => $enviados, 'errores' => $errores, 'total' => count($clientes)];
}
