<?php
// api_clima.php
header('Content-Type: application/json'); // Indicar que la respuesta será JSON
header("Access-Control-Allow-Origin: *"); // Permitir solicitudes desde cualquier origen (ajusta si es necesario por seguridad)

$apiKey = "4405641ea6971a9209c0435c2d95fb04"; // ¡¡REEMPLAZA ESTO CON TU API KEY REAL!!
$ciudad = isset($_GET['ciudad']) ? trim($_GET['ciudad']) : "Ibagué,CO"; // Ciudad por defecto o la que se pida
$unidades = "metric"; // Celsius
$idioma = "es";

// Endpoint para clima actual
$apiUrl = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($ciudad) . "&appid=" . $apiKey . "&units=" . $unidades . "&lang=" . $idioma;

// Alternativa: Endpoint para pronóstico 5 días / 3 horas
// $apiUrl = "https://api.openweathermap.org/data/2.5/forecast?q=" . urlencode($ciudad) . "&appid=" . $apiKey . "&units=" . $unidades . "&lang=" . $idioma;

// --- Opción 1: Usar file_get_contents (más simple si allow_url_fopen está activado en php.ini) ---
$responseJson = @file_get_contents($apiUrl); // Usar @ para suprimir warnings si la URL falla

if ($responseJson === FALSE) {
    // Error al obtener los datos
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'No se pudo conectar con el servicio de clima o la ciudad no fue encontrada.']);
    exit;
}

// --- Opción 2: Usar cURL (más robusto y configurable) ---
/*
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_URL, $apiUrl);
// Opcional: Añadir timeout
// curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 segundos para conectar
// curl_setopt($ch, CURLOPT_TIMEOUT, 10);      // 10 segundos para la respuesta total
$responseJson = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($responseJson === false || $httpCode >= 400) {
    http_response_code($httpCode >= 400 ? $httpCode : 500);
    echo json_encode(['error' => 'Error al obtener datos del clima. Código: ' . $httpCode, 'raw_response' => $responseJson]);
    exit;
}
*/

// No necesitas decodificar y recodificar si ya es JSON y solo lo pasas.
// Pero si quisieras procesarlo en PHP antes de enviarlo:
// $data = json_decode($responseJson, true);
// if (json_last_error() !== JSON_ERROR_NONE) {
//     http_response_code(500);
//     echo json_encode(['error' => 'Error al decodificar la respuesta JSON del servicio de clima.']);
//     exit;
// }
// // Aquí podrías manipular $data si es necesario
// echo json_encode($data);

// Simplemente pasar la respuesta JSON tal cual la recibimos
echo $responseJson;
?>