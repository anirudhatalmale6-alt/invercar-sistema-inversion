<?php
/**
 * InverCar — Proxy endpoint to publish vehicle to MultiCar
 *
 * Receives vehicle ID via AJAX, fetches vehicle data from InverCar DB,
 * and sends it to MultiCar's import API. Keeps API key server-side.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$vehiculoId = (int)($input['vehiculo_id'] ?? 0);

if ($vehiculoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de vehículo inválido.']);
    exit;
}

$db = getDB();

// Fetch vehicle from InverCar
$stmt = $db->prepare("SELECT * FROM vehiculos WHERE id = ?");
$stmt->execute([$vehiculoId]);
$v = $stmt->fetch();

if (!$v) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Vehículo no encontrado.']);
    exit;
}

// Fetch additional photos
$stmtFotos = $db->prepare("SELECT foto FROM vehiculo_fotos WHERE vehiculo_id = ? ORDER BY orden ASC");
$stmtFotos->execute([$vehiculoId]);
$fotos = $stmtFotos->fetchAll(PDO::FETCH_COLUMN);

// Build photo URLs
$photoUrls = [];
if (!empty($v['foto'])) {
    $photoUrls[] = SITE_URL . '/' . $v['foto'];
}
foreach ($fotos as $foto) {
    $photoUrls[] = SITE_URL . '/' . $foto;
}

// Map InverCar data to MultiCar format
$payload = [
    'brand'        => $v['marca'],
    'model'        => $v['modelo'],
    'version'      => $v['version'] ?? '',
    'year'         => (int)$v['anio'],
    'price'        => floatval($v['valor_venta_previsto'] ?: $v['precio_venta_real'] ?: 0),
    'mileage'      => (int)($v['kilometros'] ?? 0),
    'fuel'         => 'gasolina',
    'transmission' => 'manual',
    'body_type'    => 'sedan',
    'description'  => $v['notas'] ?? '',
    'photo_urls'   => $photoUrls
];

// MultiCar API configuration
$multicarUrl = getConfig('multicar_api_url', 'https://multicar.autos/api/import_vehicle.php');
$multicarKey = getConfig('multicar_api_key', '3fe89860d7ea9fc224aed84cdbb78504d707116cdb5227058a8157d772a934d6');

// Call MultiCar API
$ch = curl_init($multicarUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-Key: ' . $multicarKey
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión con MultiCar: ' . $curlError]);
    exit;
}

$result = json_decode($response, true);

if ($httpCode === 200 && !empty($result['success'])) {
    echo json_encode([
        'success' => true,
        'message' => 'Vehículo publicado en MultiCar como borrador.',
        'multicar_id' => $result['vehicle_id'] ?? null,
        'photos_imported' => $result['photos_imported'] ?? 0
    ]);
} else {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de MultiCar: ' . ($result['message'] ?? 'Sin respuesta')
    ]);
}
