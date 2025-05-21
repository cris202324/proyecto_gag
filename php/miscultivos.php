<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Incluir el archivo de conexión y obtener el ID del usuario
include 'conexion.php'; // Este archivo debe definir $pdo
$id_usuario_actual = $_SESSION['id_usuario'];

$cultivos_usuario = [];
$mensaje_error = '';

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
        // error_log("Error en mis_cultivos.php: " . $e->getMessage());
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
    <title>Mis Cultivos</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .content {
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 20px;
            width: 300px;
            min-height: 150px;
            display: flex;
            flex-direction: column;
        }
        .card h3 {
            margin-top: 0;
            color: #333;
        }
        .card p {
            font-size: 0.9em;
            color: #555;
            margin-bottom: 8px;
            flex-grow: 1;
        }
        .card small {
            font-size: 0.8em;
            color: #777;
            display: block;
            margin-top: auto;
        }
        .no-cultivos {
            text-align: center;
            width: 100%;
            padding: 30px;
            font-size: 1.2em;
            color: #777;
        }
        .error-message {
            color: red;
            text-align: center;
            width: 100%;
            padding: 15px;
            background-color: #fdd;
            border: 1px solid #fbb;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="logo" />
        </div>
        <div class="menu">
            <a href="index.php">Inicio</a>
            <a href="miscultivos.php" class="active">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario.php">Calendario y Horarios</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar sesión</a>
        </div>
    </div>

    <div class="content">
        <h2>Mis Cultivos Registrados</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($cultivos_usuario)): ?>
            <div class="no-cultivos">
                <p>Aún no has registrado ningún cultivo.</p>
                <p><a href="crearcultivos.php">¡Registra tu primer cultivo aquí!</a></p>
            </div>
        <?php elseif (!empty($cultivos_usuario)): ?>
            <?php foreach ($cultivos_usuario as $cultivo): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($cultivo['nombre_cultivo']); ?> en <?php echo htmlspecialchars($cultivo['nombre_municipio']); ?></h3>
                    <p>
                        <strong>Inicio:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_inicio']))); ?><br>
                        <strong>Área:</strong> <?php echo htmlspecialchars($cultivo['area_hectarea']); ?> ha<br>
                    </p>
                    <small>
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
                            if ($fechaFinEstimadaObj < $hoy) {
                                $mensajeCosecha = "Cosechado/Finalizado el " . $fechaFinEstimadaObj->format('d/m/Y');
                            } else {
                                $diferencia = $hoy->diff($fechaFinEstimadaObj);
                                $diasRestantes = $diferencia->days;
                                if ($diferencia->invert == 1 && $diasRestantes > 0) {
                                    $mensajeCosecha = "Cosechado/Finalizado el " . $fechaFinEstimadaObj->format('d/m/Y');
                                } elseif ($diasRestantes == 0) {
                                    $mensajeCosecha = "Cosecha estimada: ¡Hoy!";
                                } elseif ($diasRestantes == 1) {
                                    $mensajeCosecha = "Cosecha estimada: Mañana (1 día).";
                                } else {
                                    $mensajeCosecha = "Cosecha estimada: En {$diasRestantes} días (" . $fechaFinEstimadaObj->format('d/m/Y') . ").";
                                }
                            }
                        }
                        echo "<strong>Cosecha:</strong> " . htmlspecialchars($mensajeCosecha) . "<br>";

                        $progresoAbono = "Abono: No hay datos.";
                        if (isset($pdo)) {
                            try {
                                $sql_abono = "SELECT tipo_tratamiento, producto_usado, etapas
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
                                // error_log("Error consultando abono para cultivo ".$cultivo['id_cultivo'].": ".$e->getMessage());
                            }
                        }
                        echo "<strong>" . htmlspecialchars($progresoAbono) . "</strong>";
                        ?>
                    </small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>