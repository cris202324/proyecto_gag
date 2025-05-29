<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.html"); // Ajusta esta ruta según tu estructura
    exit();
}

// Incluir el archivo de conexión y obtener el ID del usuario
include 'conexion.php'; // Este archivo debe definir $pdo
$id_usuario_actual = $_SESSION['id_usuario'];

$cultivos_usuario = [];
$mensaje_error = '';
$mensaje_exito = ''; // Para mensajes de éxito (ej. cultivo borrado)

// Verificar si hay un mensaje de la acción de borrar (si implementaste borrado)
if (isset($_SESSION['mensaje_borrado'])) {
    $mensaje_exito = $_SESSION['mensaje_borrado'];
    unset($_SESSION['mensaje_borrado']);
}
if (isset($_SESSION['error_borrado'])) {
    $mensaje_error = $_SESSION['error_borrado'];
    unset($_SESSION['error_borrado']);
}


if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        $sql = "SELECT
                    c.id_cultivo,
                    c.fecha_inicio,
                    c.fecha_fin AS fecha_fin_registrada,
                    c.area_hectarea,
                    tc.nombre_cultivo,
                    tc.tiempo_estimado_frutos,
                    m.nombre AS nombre_municipio
                FROM cultivos c
                JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                JOIN municipio m ON c.id_municipio = m.id_municipio
                WHERE c.id_usuario = :id_usuario
                ORDER BY c.fecha_inicio DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_usuario', $id_usuario_actual);
        $stmt->execute();
        $cultivos_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los cultivos: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Mis Cultivos - GAG</title>
    <!-- <link rel="stylesheet" href="../css/estilos.css"> --> <!-- Comentado ya que los estilos van inline -->
    <style>
        /* Estilos generales */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            font-size: 16px; 
        }

        /* Cabecera */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            background-color: #e0e0e0;
            border-bottom: 2px solid #ccc;
            position: relative; 
        }

        .logo img {
            height: 70px; 
            transition: height 0.3s ease;
        }

        .menu {
            display: flex;
            align-items: center;
        }

        .menu a {
            margin: 0 5px;
            text-decoration: none;
            color: black; 
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            transition: background-color 0.3s, color 0.3s, padding 0.3s ease;
            white-space: nowrap;
            font-size: 0.9em;
        }

        .menu a.active,
        .menu a:hover {
            background-color: #88c057; 
            color: white !important;    
            border-color: #70a845;   
        }

        .menu a.exit { 
            background-color: #ff4d4d;
            color: white !important;
            border: 1px solid #cc0000;
        }
        .menu a.exit:hover {
            background-color: #cc0000;
            color: white !important;
        }

        /* Botón del menú hamburguesa */
        .menu-toggle {
            display: none; 
            background: none;
            border: none;
            font-size: 1.8rem; 
            color: #333;     
            cursor: pointer;
            padding: 5px;
        }
        
        /* Contenedor principal de la página */
        .page-container { 
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .page-container > h2.page-title { 
            text-align: center;
            color: #4caf50; 
            margin-bottom: 25px;
            font-size: 1.8em;
        }

        /* Grid para las tarjetas de cultivo */
        .cultivos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
            gap: 25px; 
        }

        .cultivo-card { 
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .cultivo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        .cultivo-card h3 {
            margin-top: 0;
            margin-bottom: 12px;
            color: #0056b3; 
            font-size: 1.3em; 
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .cultivo-card .info-section p {
            font-size: 0.95em; 
            color: #444;
            margin-bottom: 8px;
            line-height: 1.6;
        }
        .cultivo-card .info-section strong { color: #111; }
        .cultivo-card .status-section { 
            font-size: 0.85em; 
            color: #555; 
            display: block;
            margin-top: 15px; 
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }
        .cultivo-card .status-section strong { color: #333; }
        .cultivo-actions { margin-top: 15px; text-align: right; }
        .btn-delete-cultivo {
            background-color: #e74c3c; 
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.85em;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-delete-cultivo:hover { background-color: #c0392b; }
        
        .no-cultivos {
            text-align: center; width: 100%; padding: 40px 20px; 
            font-size: 1.2em; color: #666; background-color: #fff; 
            border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .no-cultivos p { margin-bottom: 15px; }
        .no-cultivos a { color: #4caf50; font-weight: bold; text-decoration: none; }
        .no-cultivos a:hover { text-decoration: underline; }
        
        .error-message { 
            color: #D8000C; text-align: center; width: 100%; padding: 15px; 
            background-color: #FFD2D2; border: 1px solid #D8000C; 
            border-radius: 5px; margin-bottom: 20px; 
        }
        .success-message { 
            color: #270; background-color: #DFF2BF; border: 1px solid #4F8A10; 
            padding: 15px; margin-bottom: 20px; text-align: center; border-radius: 5px; 
        }

        /* --- INICIO DE ESTILOS RESPONSIVOS --- */
        @media (max-width: 991px) { /* Breakpoint para tablets y móviles grandes */
            .menu-toggle {
                display: block; 
            }
            .menu {
                display: none; 
                flex-direction: column; align-items: stretch; position: absolute;
                top: 100%; left: 0; width: 100%;
                background-color: #e9e9e9; padding: 0;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1000; border-top: 1px solid #ccc;
            }
            .menu.active { display: flex; }
            .menu a {
                margin: 0; padding: 15px 20px; width: 100%; text-align: left;
                border: none; border-bottom: 1px solid #d0d0d0; border-radius: 0;
                color: #333;
            }
            .menu a:last-child { border-bottom: none; }
            .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: transparent; }
            .menu a.exit, .menu a.exit:hover { background-color: #ff4d4d; color: white !important; }

            /* Ajustes para miscultivos en tabletas */
            .page-container { padding: 15px; }
            .page-container > h2.page-title { font-size: 1.6em; margin-bottom: 20px; }
            .cultivos-grid { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; } /* Ajustar minmax y gap */
            .cultivo-card { padding: 15px; }
            .cultivo-card h3 { font-size: 1.2em; }
        }

        @media (max-width: 767px) { /* Móviles */
            .logo img { height: 60px; }
            .menu-toggle { font-size: 1.6rem; }

            .page-container > h2.page-title { font-size: 1.5em; }
            .cultivos-grid { grid-template-columns: 1fr; } /* Una columna en móviles */
        }

        @media (max-width: 480px) { /* Móviles pequeños */
            .logo img { height: 50px; }
            .page-container > h2.page-title { font-size: 1.4em; }
            .cultivo-card h3 { font-size: 1.1em; }
            .cultivo-card .info-section p, .cultivo-card .status-section { font-size: 0.9em; }
        }
        /* --- FIN DE ESTILOS RESPONSIVOS --- */
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo GAG" /> <!-- Ajusta ruta -->
        </div>
        <!-- Botón Hamburguesa -->
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">
            ☰ <!-- Icono de hamburguesa -->
        </button>
        <nav class="menu" id="mainMenu"> <!-- Contenedor del menú con ID -->
            <a href="index.php">Inicio</a>
            <a href="miscultivos.php" class="active">Mis Cultivos</a> <!-- Asumiendo que esta es miscultivos.php -->
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario.php">Calendario</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h2 class="page-title">Mis Cultivos Registrados</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_exito)): ?>
            <p class="success-message"><?php echo htmlspecialchars($mensaje_exito); ?></p>
        <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($cultivos_usuario)): ?>
            <div class="no-cultivos">
                <p>Aún no has registrado ningún cultivo.</p>
                <p><a href="crearcultivos.php">¡Registra tu primer cultivo aquí!</a></p>
            </div>
        <?php elseif (!empty($cultivos_usuario)): ?>
            <div class="cultivos-grid">
                <?php foreach ($cultivos_usuario as $cultivo): ?>
                    <div class="cultivo-card">
                        <h3><?php echo htmlspecialchars($cultivo['nombre_cultivo']); ?> en <?php echo htmlspecialchars($cultivo['nombre_municipio']); ?></h3>
                        <div class="info-section">
                            <p>
                                <strong>Inicio:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_inicio']))); ?><br>
                                <strong>Área:</strong> <?php echo htmlspecialchars($cultivo['area_hectarea']); ?> ha<br>
                            </p>
                        </div>
                        <div class="status-section">
                            <?php
                            $hoy = new DateTime();
                            $fechaInicioObj = new DateTime($cultivo['fecha_inicio']);
                            $mensajeCosecha = "Fecha de cosecha no determinada.";

                            if (!empty($cultivo['fecha_fin_registrada'])) {
                                $fechaFinEstimadaObj = new DateTime($cultivo['fecha_fin_registrada']);
                            } elseif (!empty($cultivo['tiempo_estimado_frutos'])) {
                                $fechaFinEstimadaObj = clone $fechaInicioObj;
                                $fechaFinEstimadaObj->add(new DateInterval('P' . $cultivo['tiempo_estimado_frutos'] . 'D'));
                            } else {
                                $fechaFinEstimadaObj = null;
                            }

                            if ($fechaFinEstimadaObj) {
                                $hoy->setTime(0,0,0); // Comparar solo fechas
                                $fechaComparar = clone $fechaFinEstimadaObj;
                                $fechaComparar->setTime(0,0,0);

                                if ($fechaComparar < $hoy) {
                                    $mensajeCosecha = "Cosechado/Finalizado el " . $fechaFinEstimadaObj->format('d/m/Y');
                                } else {
                                    $diferencia = $hoy->diff($fechaComparar);
                                    $diasRestantes = $diferencia->days;
                                     if ($diferencia->invert == 0 && $diasRestantes == 0) { // Invert 0 significa futuro o hoy
                                       $mensajeCosecha = "Cosecha estimada: ¡Hoy!";
                                    } elseif ($diferencia->invert == 0 && $diasRestantes == 1) {
                                       $mensajeCosecha = "Cosecha estimada: Mañana (1 día).";
                                    } elseif ($diferencia->invert == 0 && $diasRestantes > 1) {
                                       $mensajeCosecha = "Cosecha estimada: En {$diasRestantes} días (" . $fechaFinEstimadaObj->format('d/m/Y') . ").";
                                    } else { // Si invert es 1, ya pasó (cubierto por la primera condición < $hoy)
                                        // Este else podría ser para casos no previstos o si la lógica de invert es confusa
                                        $mensajeCosecha = "Cosecha estimada: " . $fechaFinEstimadaObj->format('d/m/Y');
                                    }
                                }
                            }
                            echo "<strong>Cosecha:</strong> " . htmlspecialchars($mensajeCosecha) . "<br>";

                            $progresoAbono = "Abono: No hay datos.";
                            if (isset($pdo)) { 
                                try {
                                    $sql_abono = "SELECT tipo_tratamiento, producto_usado, etapas, id_tratamiento
                                                  FROM tratamiento_cultivo
                                                  WHERE id_cultivo = :id_cultivo
                                                    AND (LOWER(tipo_tratamiento) LIKE '%abono%' OR LOWER(tipo_tratamiento) LIKE '%fertilizante%')
                                                  ORDER BY id_tratamiento DESC LIMIT 1"; 
                                    $stmt_abono = $pdo->prepare($sql_abono);
                                    $stmt_abono->bindParam(':id_cultivo', $cultivo['id_cultivo']);
                                    $stmt_abono->execute();
                                    $ultimo_abono = $stmt_abono->fetch(PDO::FETCH_ASSOC);

                                    if ($ultimo_abono) {
                                        $progresoAbono = "Último abono: " . htmlspecialchars($ultimo_abono['tipo_tratamiento']) . " (" . htmlspecialchars($ultimo_abono['producto_usado']) . ") - Etapa: " . htmlspecialchars($ultimo_abono['etapas']);
                                    } else {
                                        $progresoAbono = "Abono: Ningún tratamiento de abono/fertilizante registrado.";
                                    }
                                } catch (PDOException $e) {
                                    $progresoAbono = "Abono: Error al consultar tratamientos.";
                                }
                            }
                            echo "<strong>" . htmlspecialchars($progresoAbono) . "</strong>";
                            ?>
                        </div>
                        <div class="cultivo-actions">
                            <a href="borrar_cultivo.php?id_cultivo=<?php echo $cultivo['id_cultivo']; ?>"
                               class="btn-delete-cultivo"
                               onclick="return confirm('¿Estás seguro de que deseas borrar este cultivo? Esta acción no se puede deshacer.');">
                                Borrar Cultivo
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div> <!-- Fin .cultivos-grid -->
        <?php endif; ?>
    </div> <!-- Fin .page-container -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- LÓGICA PARA EL MENÚ HAMBURGUESA ---
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');

            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    const isExpanded = mainMenu.classList.contains('active');
                    menuToggleBtn.setAttribute('aria-expanded', isExpanded);
                });
            }
        });
    </script>
</body>
</html>