========================================
INVERCAR - GUÍA DE INSTALACIÓN
========================================

1. REQUISITOS
-------------
- PHP 7.4+ (recomendado 8.0+)
- MySQL 5.7+ o MariaDB 10.x
- Servidor Apache con mod_rewrite

2. SUBIR ARCHIVOS
-----------------
Sube toda la carpeta "invercar" a tu hosting en Hostalia.
Puedes subirla a la raíz (public_html) o a una subcarpeta.

3. CREAR BASE DE DATOS
----------------------
- Accede al panel de Hostalia
- Crea una base de datos MySQL
- Crea un usuario y asígnalo a la base de datos
- Anota: nombre de BD, usuario y contraseña

4. IMPORTAR ESTRUCTURA
----------------------
- Accede a phpMyAdmin desde el panel de Hostalia
- Selecciona tu base de datos
- Ve a "Importar"
- Sube el archivo: database/schema.sql
- Ejecutar

5. CONFIGURAR CONEXIÓN
----------------------
Edita el archivo: includes/config.php

Cambia estos valores:
- DB_HOST: normalmente 'localhost' en Hostalia
- DB_NAME: el nombre de tu base de datos
- DB_USER: tu usuario de MySQL
- DB_PASS: tu contraseña de MySQL
- SITE_URL: la URL de tu sitio (ej: https://tu-dominio.com)

Configura el email:
- SMTP_HOST: 'smtp.hostalia.com'
- SMTP_PORT: 587
- SMTP_USER: tu email
- SMTP_PASS: contraseña del email
- SMTP_FROM: email de remitente

6. PERMISOS DE CARPETAS
-----------------------
Asegúrate de que estas carpetas tengan permisos de escritura (755 o 775):
- assets/uploads/
- assets/uploads/vehiculos/

7. ACCESO AL SISTEMA
--------------------
Admin:
- URL: tu-dominio.com/admin/login.php
- Usuario: admin
- Contraseña: admin123 (¡CAMBIAR INMEDIATAMENTE!)

Clientes:
- URL: tu-dominio.com/cliente/login.php
- Los clientes se registran desde la landing page

8. CAMBIAR CONTRASEÑA ADMIN
---------------------------
IMPORTANTE: Cambia la contraseña del admin después de instalar.

Opción 1: Desde phpMyAdmin
- Genera un hash en: https://www.bcrypt-generator.com/
- Actualiza en la tabla 'administradores'

Opción 2: Crear un archivo temporal cambiar_password.php:
<?php
$nuevo_password = 'TU_NUEVA_CONTRASEÑA';
echo password_hash($nuevo_password, PASSWORD_DEFAULT, ['cost' => 12]);
?>
- Copia el hash generado y actualízalo en la BD
- Borra el archivo inmediatamente

9. PRODUCCIÓN
-------------
En includes/config.php cambia:
- DEBUG_MODE: false

En .htaccess descomenta la línea de HTTPS si tienes SSL.

10. SOPORTE
-----------
Si tienes problemas con la instalación, contacta conmigo.

========================================
