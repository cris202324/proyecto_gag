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

// Verificar si el usuario es admin (id_rol = 1)
include 'conexion.php';
$stmt = $pdo->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = :id_usuario");
$stmt->bindParam(':id_usuario', $_SESSION['id_usuario']);
$stmt->execute();
$rol = $stmt->fetchColumn();

if ($rol != 1) {
    header("Location: index.php"); // Redirigir a los no admins
    exit();
}

// Obtener estadísticas básicas
$total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id_rol = 2")->fetchColumn(); // Solo usuarios no admins
$total_cultivos = $pdo->query("SELECT COUNT(*) FROM cultivos")->fetchColumn();
$total_animales = $pdo->query("SELECT COUNT(*) FROM animales")->fetchColumn();

// Obtener el nombre del municipio del usuario para el clima (puedes ajustarlo según tu BD)
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
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        /* Estilos para la tarjeta del clima personalizada (copiados de index.php) */
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
        /* Estilo adicional para estadísticas */
        .stat-card {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: 150px;
        height: 150px;
        background: linear-gradient(to bottom, #88c057, #6da944);
        border: 2px solid #ccc;
        border-radius: 10px;
        box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
        font-size: 16px;
        font-weight: bold;
        color: white;
        transition: transform 0.3s, background-color 0.3s, color 0.3s;
        padding: 10px; /* Añade padding interno */
        }

        .stat-card:hover {
            transform: scale(1.05);
            background-color: #6da944;
        }

        .stat-card span {
            display: block;
            font-size: 24px;
            margin-top: 10px; /* Espacio entre el texto y el número */
            line-height: 1.2; /* Mejora la alineación vertical */
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo" />
        </div>
        <div class="menu">
            <a href="admin_dashboard.php" class="active">Inicio</a>
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="view_all_animals.php">Ver Animales</a>
            <a href="manage_users.php">Gestionar Usuarios</a>
            <a href="manage_animals.php">Gestionar Animales</a>
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </div>
    </div>

    <div class="content">
    <a href="view_users.php" class="card">Ver Usuarios</a>
    <a href="view_all_crops.php" class="card">Ver Cultivos</a>
    <a href="view_all_animals.php" class="card">Ver Animales</a>
    <a href="manage_users.php" class="card">Gestionar Usuarios</a>
    <a href="manage_animals.php" class="card">Gestionar Animales</a>
    <div class="card" style="background: linear-gradient(to bottom, #4a90e2, #357abd);">
        <div class="card-text">Usuarios Totales</div>
        <div class="card-number"><?php echo htmlspecialchars($total_usuarios); ?></div>
    </div>
    <div class="card" style="background: linear-gradient(to bottom, #4a90e2, #357abd);">
        <div class="card-text">Cultivos Totales</div>
        <div class="card-number"><?php echo htmlspecialchars($total_cultivos); ?></div>
    </div>
    <div class="card" style="background: linear-gradient(to bottom, #4a90e2, #357abd);">
        <div class="card-text">Animales Totales</div>
        <div class="card-number"><?php echo htmlspecialchars($total_animales); ?></div>
    </div>
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