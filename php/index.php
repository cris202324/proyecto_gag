<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    // Ajustar la ruta a login.html si index.php está en la carpeta php/
    // Si login.html está en la raíz del proyecto (un nivel arriba de php/):
    header("Location: ../login.html"); 
    // Si login.html está en el mismo nivel que proyecto/ (lo cual es raro):
    // header("Location: http://localhost/proyecto/login.html");
    exit();
}

// Determinar el rol del usuario
$is_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] == 1);
$title = $is_admin ? "Interfaz Admin" : "Panel de Usuario";

// Obtener el nombre del municipio del usuario si está disponible (para el clima)
// Esto es un ejemplo, necesitarías obtenerlo de la BD si el usuario tiene un municipio asociado
$nombre_municipio_usuario = "Ibagué"; // Valor por defecto o para pruebas
// Si tienes una forma de obtener el municipio del usuario desde la sesión o BD:
// include_once 'conexion.php'; // Asegúrate que $pdo esté disponible
// if (isset($pdo) && isset($_SESSION['id_usuario_municipio'])) { /* Lógica para obtenerlo */ }

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="../css/estilos.css"> <!-- Asumiendo que index.php está en /php/ y css en /css/ -->
    <style>
        /* Estilos para la tarjeta del clima personalizada */
        .weather-display-card {
            padding: 15px;
            background-color: #fff; /* Fondo blanco */
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            color: #333;
            margin: 10px; /* Para que encaje con las otras .card */
            width: 220px; /* Ajusta según necesites */
            min-height: 150px; /* Similar a .card */
            display: flex;
            flex-direction: column;
            /* justify-content: center; /* Comentado para alinear arriba */
            align-items: center;
            text-align: center;
        }
        .weather-display-card h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #0056b3; /* Un azul para el título */
            font-size: 1.1em;
            width: 100%;
        }
        .weather-display-card p {
            margin: 4px 0;
            font-size: 0.85em;
        }
        .weather-display-card #clima-icono img {
            width: 50px;
            height: 50px;
        }
        .weather-display-card #clima-descripcion {
            text-transform: capitalize;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .content { /* Asegurar que content permita envolver y alinear */
            display: flex;
            justify-content: center; /* O flex-start si prefieres alinear a la izquierda */
            align-items: flex-start; /* Para alinear arriba tarjetas de diferentes alturas */
            flex-wrap: wrap;
            gap: 20px; /* Ya lo tenías */
            margin: 30px; /* Reducido de 50px */
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo" /> 
        </div>
        <div class="menu">
            <a href="index.php" class="active">Inicio</a> 
            <a href="miscultivos.php">Mis cultivos</a>
            <a href="animales/mis_animales.php">Mis animales</a>
            <a href="calendario.php">Calendario y horario</a> 
             <a href="configuracion.php">Configuración</a>
             <a href="ayuda.php">Ayuda</a> 
            <a href="cerrar_sesion.php">Cerrar sesión</a>
        </div>
    </div>

    <div class="content">
        <a href="crearcultivos.php" class="card">Nuevos cultivos</a>
        <a href="animales/crear_animales.php" class="card">Nuevos animales</a>
        <a href="calendario.php" class="card">Ver calendario</a>
        <a href="configuracion.php" class="card">Configuración</a>
        <a href="ayuda.php" class="card">Ayuda</a>

        <!-- Tarjeta para mostrar el Clima -->
        <div class="weather-display-card">
            <h4>Clima en <span id="clima-ciudad">Cargando...</span></h4>
            <div id="clima-icono"></div>
            <p id="clima-descripcion"></p>
            <p><strong>Temp:</strong> <span id="clima-temp">--</span> °C</p>
            <p><strong>Humedad:</strong> <span id="clima-humedad">--</span> %</p>
            <p id="clima-lluvia-pop"></p>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const climaCiudadEl = document.getElementById('clima-ciudad');
    const climaIconoEl = document.getElementById('clima-icono');
    const climaDescripcionEl = document.getElementById('clima-descripcion');
    const climaTempEl = document.getElementById('clima-temp');
    const climaHumedadEl = document.getElementById('clima-humedad');
    const climaLluviaPopEl = document.getElementById('clima-lluvia-pop');

    // Usar el nombre del municipio del PHP si está disponible, sino un valor por defecto
    let ciudadParaClima = "<?php echo htmlspecialchars(addslashes($nombre_municipio_usuario . ',CO')); // Añadir ',CO' para Colombia si aplica ?>";

    function cargarClima() {
        // api_clima.php está en el mismo directorio que index.php (ambos en php/)
        const urlApiLocal = `api_clima.php?ciudad=${encodeURIComponent(ciudadParaClima)}`;

        fetch(urlApiLocal)
            .then(response => {
                if (!response.ok) {
                    // Intentar obtener el mensaje de error del JSON de respuesta si es posible
                    return response.json().then(errData => {
                        let errorMsg = `Error HTTP: ${response.status}`;
                        if (errData && errData.error) {
                            errorMsg = errData.error;
                        } else if (errData && errData.message) { // Mensaje de error de OpenWeatherMap
                            errorMsg = `API Clima: ${errData.message}`;
                        }
                        throw new Error(errorMsg);
                    }).catch(() => { // Si la respuesta no es JSON
                        throw new Error(`Error HTTP: ${response.status} al contactar el servicio de clima local.`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.cod && data.cod.toString() !== "200") { // Error devuelto por OpenWeatherMap API
                    climaCiudadEl.textContent = ciudadParaClima.split(',')[0];
                    climaDescripcionEl.textContent = `Error: ${data.message || 'No se pudo obtener el clima.'}`;
                    climaIconoEl.innerHTML = '';
                    climaTempEl.textContent = '--';
                    climaHumedadEl.textContent = '--';
                    climaLluviaPopEl.textContent = '';
                    return;
                }

                climaCiudadEl.textContent = data.name;
                climaIconoEl.innerHTML = `<img src="https://openweathermap.org/img/wn/${data.weather[0].icon}.png" alt="${data.weather[0].description}">`;
                climaDescripcionEl.textContent = data.weather[0].description;
                climaTempEl.textContent = data.main.temp.toFixed(1);
                climaHumedadEl.textContent = data.main.humidity;

                let lluviaInfo = "Prob. lluvia no disponible.";
                if (data.rain && data.rain['1h']) {
                    lluviaInfo = `Lluvia (1h): ${data.rain['1h']} mm`;
                } else if (data.pop !== undefined) { // 'pop' a veces está en current data
                    lluviaInfo = `Prob. lluvia: ${(data.pop * 100).toFixed(0)}%`;
                }
                // Para un 'pop' más fiable, necesitarías el endpoint de pronóstico /forecast
                // y mirar en data.list[0].pop para las próximas 3h.
                climaLluviaPopEl.textContent = lluviaInfo;

            })
            .catch(error => {
                console.error('Error al cargar datos del clima:', error);
                climaCiudadEl.textContent = ciudadParaClima.split(',')[0];
                climaDescripcionEl.textContent = error.message.includes("API Clima:") ? error.message : "No se pudo cargar el clima.";
                climaIconoEl.innerHTML = '';
                climaTempEl.textContent = '--';
                climaHumedadEl.textContent = '--';
                climaLluviaPopEl.textContent = '';
            });
    }

    cargarClima();
    // Opcional: recargar cada cierto tiempo (ej. cada 15 minutos)
    // setInterval(cargarClima, 15 * 60 * 1000);
});
</script>
</body>
</html>