<?php
require_once 'conexion.php'; // $pdo (asegúrate que esté disponible)

// --- CONFIGURACIÓN ---
$claveAPI_losprecios = "dgfkol3498hskjJt"; // <<<--- CLAVE API ACTUALIZADA AQUÍ

// Mapeo de tus tipos de cultivo a los IDs de producto de la API "losprecios.co"
// !! NECESITAS CONSEGUIR ESTOS IDs DE LA DOCUMENTACIÓN DE losprecios.co !!
$mapeo_productos_api = [
    // id_tipo_cultivo_local => ['id_api' => ID_PRODUCTO_EN_LOSPRECIOS.CO, 'nombre_referencia' => 'Tu Nombre', 'nombre_api_exacto' => 'Nombre en API']
    1 => ['id_api' => 123, 'nombre_referencia' => 'Arroz', 'nombre_api_exacto' => 'Arroz Paddy Seco API'], 
    2 => ['id_api' => 456, 'nombre_referencia' => 'Café', 'nombre_api_exacto' => 'Cafe Pergamino API'],
    3 => ['id_api' => 789, 'nombre_referencia' => 'Plátano', 'nombre_api_exacto' => 'Platano Harton API'],
];

// Mapeo de tus municipios a los IDs de municipio de la API "losprecios.co"
// !! NECESITAS CONSEGUIR ESTOS IDs DE LA DOCUMENTACIÓN DE losprecios.co !!
$mapeo_municipios_api = [
    // id_municipio_local => MunicipioID_EN_LOSPRECIOS.CO
    1 => 901, // Ejemplo: Tu id_municipio para Ibagué
    2 => 902, // Ejemplo: Tu id_municipio para Espinal
];

echo "<pre>"; 

if (!isset($pdo)) {
    die("Error crítico: No hay conexión a la BD para obtener la lista de cultivos/municipios a procesar.");
}

try {
    foreach ($mapeo_productos_api as $id_tipo_cultivo_local => $producto_info_api) {
        foreach ($mapeo_municipios_api as $id_municipio_local => $municipio_id_api) {
            
            $id_producto_api = $producto_info_api['id_api'];
            $nombre_referencia_cultivo = $producto_info_api['nombre_referencia'];

            echo "Procesando: " . htmlspecialchars($nombre_referencia_cultivo) . " en Municipio API ID: " . htmlspecialchars($municipio_id_api) . "\n";

            // CONSTRUCCIÓN DE LA URL DE LA API
            $url_api = "https://losprecios.co/producto/detalles?ID=" . $id_producto_api . "&ClaveAPI=" . $claveAPI_losprecios . "&MunicipioID=" . $municipio_id_api;
            
            echo "  URL API: " . htmlspecialchars($url_api) . "\n";

            // --- LLAMADA A LA API ---
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url_api);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            // curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json')); // Descomenta si la API lo requiere
            $respuesta_json = curl_exec($ch);
            
            if (curl_errno($ch)) {
                echo "  ERROR cURL para " . htmlspecialchars($nombre_referencia_cultivo) . ": " . curl_error($ch) . "\n";
                curl_close($ch);
                sleep(1); // Pausa antes de la siguiente iteración
                continue;
            }
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code >= 400) {
                echo "  ERROR HTTP " . $http_code . " para " . htmlspecialchars($nombre_referencia_cultivo) . "\n";
                echo "  Respuesta cruda: " . htmlspecialchars($respuesta_json) . "\n";
                sleep(1);
                continue;
            }

            $datos_precio = json_decode($respuesta_json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "  ERROR al decodificar JSON para " . htmlspecialchars($nombre_referencia_cultivo) . ". Error: " . json_last_error_msg() . "\n";
                echo "  Respuesta cruda: " . htmlspecialchars($respuesta_json) . "\n";
                sleep(1);
                continue;
            }

            // --- PROCESAR Y GUARDAR DATOS (AJUSTA ESTAS CLAVES SEGÚN LA RESPUESTA REAL DE LA API) ---
            if (isset($datos_precio['precioPromedio']) && isset($datos_precio['unidad']) && isset($datos_precio['fechaActualizacion'])) {
                // ... (resto de la lógica de procesamiento y guardado en BD como en la respuesta anterior) ...
                // Ejemplo:
                $precio_prom = (float)$datos_precio['precioPromedio'];
                $unidad_api = trim($datos_precio['unidad']);
                $fecha_api = $datos_precio['fechaActualizacion'];
                // ... y así sucesivamente ...
                // ... luego el INSERT/UPDATE a tu tabla precios_cultivos_actuales ...
                echo "  DATOS PROCESADOS (ejemplo): Precio=" . $precio_prom . ", Unidad=" . $unidad_api . ", Fecha=" . $fecha_api . "\n";
            } else {
                echo "  Datos de precio incompletos o en formato inesperado de la API para " . htmlspecialchars($nombre_referencia_cultivo) . ".\n";
                echo "  Respuesta JSON recibida para análisis:\n";
                print_r($datos_precio); 
            }
            echo "\n";
            sleep(1); 
        } 
    } 
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

echo "</pre>";
echo "Proceso de actualización de precios completado.\n";
?>