<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.html");
    exit();
}

// Verificar el rol del usuario y redirigir si es admin
if (isset($_SESSION['rol']) && $_SESSION['rol'] == 1) {
    header("Location: admin_dashboard.php");
    exit();
}

// Obtener el nombre del municipio del usuario para el clima
$nombre_municipio_usuario = "Ibagué"; // Esto debería venir de la BD eventualmente
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Panel de Usuario</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        /* Estilos para la tarjeta del clima personalizada */
        .weather-display-card {
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            color: #333;
            margin: 10px;
            width: 220px;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .weather-display-card h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #0056b3;
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
        .content {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
            margin: 30px;
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
            <a href="miscultivos.php">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario.php">Calendario y Horarios</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </div>
    </div>

    <div class="content">
        <a href="crearcultivos.php" class="card">Nuevos Cultivos</a>
        <a href="animales/crear_animales.php" class="card">Nuevos Animales</a>
        <a href="calendario.php" class="card">Ver Calendario</a>
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

        let ciudadParaClima = "<?php echo htmlspecialchars(addslashes($nombre_municipio_usuario . ',CO')); ?>";

        function cargarClima() {
            const urlApiLocal = `api_clima.php?ciudad=${encodeURIComponent(ciudadParaClima)}`;

            fetch(urlApiLocal)
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errData => {
                            let errorMsg = `Error HTTP: ${response.status}`;
                            if (errData && errData.error) {
                                errorMsg = errData.error;
                            } else if (errData && errData.message) {
                                errorMsg = `API Clima: ${errData.message}`;
                            }
                            throw new Error(errorMsg);
                        }).catch(() => {
                            throw new Error(`Error HTTP: ${response.status} al contactar el servicio de clima local.`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.cod && data.cod.toString() !== "200") {
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
                    } else if (data.pop !== undefined) {
                        lluviaInfo = `Prob. lluvia: ${(data.pop * 100).toFixed(0)}%`;
                    }
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
    });
    </script>
</body>
</html>