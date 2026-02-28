-- Migration: Add MultiCar integration settings
-- These are used by admin/publicar_multicar.php to push vehicles to MultiCar

INSERT INTO configuracion (clave, valor) VALUES ('multicar_api_url', 'https://multicar.autos/api/import_vehicle.php')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

INSERT INTO configuracion (clave, valor) VALUES ('multicar_api_key', '3fe89860d7ea9fc224aed84cdbb78504d707116cdb5227058a8157d772a934d6')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);
