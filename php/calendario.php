<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

include 'conexion.php'; // $pdo
$id_usuario_actual = $_SESSION['id_usuario'];
$eventos_calendario = [];
$mensaje_error = '';

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // 1. Obtener todos los cultivos activos del usuario
        $sql_cultivos = "SELECT c.id_cultivo, tc.nombre_cultivo, c.fecha_inicio
                         FROM cultivos c
                         JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                         WHERE c.id_usuario = :id_usuario";
        $stmt_cultivos = $pdo->prepare($sql_cultivos);
        $stmt_cultivos->bindParam(':id_usuario', $id_usuario_actual);
        $stmt_cultivos->execute();
        $cultivos = $stmt_cultivos->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cultivos as $cultivo) {
            // 2. Obtener tratamientos para cada cultivo
            $sql_tratamientos = "SELECT tipo_tratamiento, producto_usado, fecha_aplicacion_estimada
                                 FROM tratamiento_cultivo
                                 WHERE id_cultivo = :id_cultivo AND fecha_aplicacion_estimada IS NOT NULL";
            $stmt_tratamientos = $pdo->prepare($sql_tratamientos);
            $stmt_tratamientos->bindParam(':id_cultivo', $cultivo['id_cultivo']);
            $stmt_tratamientos->execute();
            $tratamientos = $stmt_tratamientos->fetchAll(PDO::FETCH_ASSOC);

            foreach ($tratamientos as $trat) {
                $eventos_calendario[] = [
                    'title' => $trat['tipo_tratamiento'] . " (" . $cultivo['nombre_cultivo'] . ")",
                    'start' => $trat['fecha_aplicacion_estimada'],
                    'description' => $trat['producto_usado'] . " para " . $cultivo['nombre_cultivo'],
                    'className' => 'evento-tratamiento',
                    'cultivo_id' => $cultivo['id_cultivo']
                ];
            }

            // 3. Calcular próximo riego para cada cultivo (simplificado)
            $sql_riego = "SELECT frecuencia_riego, fecha_ultimo_riego
                          FROM riego
                          WHERE id_cultivo = :id_cultivo
                          ORDER BY fecha_ultimo_riego DESC LIMIT 1";
            $stmt_riego = $pdo->prepare($sql_riego);
            $stmt_riego->bindParam(':id_cultivo', $cultivo['id_cultivo']);
            $stmt_riego->execute();
            $ultimo_riego = $stmt_riego->fetch(PDO::FETCH_ASSOC);

            if ($ultimo_riego && $ultimo_riego['fecha_ultimo_riego']) {
                $proximo_riego_estimado = null;
                try {
                    $fechaUltRiegoObj = new DateTime($ultimo_riego['fecha_ultimo_riego']);
                    $frecuencia = strtolower($ultimo_riego['frecuencia_riego']);
                    $intervalo = null;

                    if (strpos($frecuencia, 'diario') !== false) {
                        $intervalo = 'P1D';
                    } elseif (preg_match('/cada (\d+) d[ií]as/', $frecuencia, $matches)) {
                        $intervalo = 'P' . $matches[1] . 'D';
                    } elseif (strpos($frecuencia, 'semanal') !== false) {
                        $intervalo = 'P7D';
                    }

                    if ($intervalo) {
                        $fechaUltRiegoObj->add(new DateInterval($intervalo));
                        $proximo_riego_estimado = $fechaUltRiegoObj->format('Y-m-d');

                        $hoy = new DateTime();
                        $diff = $hoy->diff($fechaUltRiegoObj);
                        if ($fechaUltRiegoObj >= $hoy || ($diff->invert && $diff->days < 7)) {
                            $eventos_calendario[] = [
                                'title' => "Riego Estimado (" . $cultivo['nombre_cultivo'] . ")",
                                'start' => $proximo_riego_estimado,
                                'description' => "Según frecuencia: " . $ultimo_riego['frecuencia_riego'],
                                'className' => 'evento-riego',
                                'cultivo_id' => $cultivo['id_cultivo']
                            ];
                        }
                    }
                } catch (Exception $e) {
                    // error_log("Error calculando próximo riego para cultivo " . $cultivo['id_cultivo'] . ": " . $e->getMessage());
                }
            }
        }
    } catch (PDOException $e) {
        $mensaje_error = "Error al cargar datos para el calendario: " . $e->getMessage();
        // error_log($mensaje_error);
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
    <title>Calendario General de Actividades</title>
    <link rel="stylesheet" href="../css/estilos.css"> <!-- Ajustada ruta -->
    <style>
        /* Estilos específicos del calendario */
        .calendario {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .calendario-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .calendario-header button {
            background: none;
            border: 1px solid #ccc;
            padding: 8px 15px;
            cursor: pointer;
            border-radius: 4px;
        }
        .calendario-header button:hover {
            background-color: #f0f0f0;
        }
        .calendario-header h3 {
            margin: 0;
            font-size: 1.6em;
            color: #333;
        }
        .calendario-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: #e0e0e0;
            border: 1px solid #e0e0e0;
        }
        .dia-semana, .dia-calendario {
            background-color: #fff;
            padding: 8px 5px;
            text-align: center;
            min-height: 90px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            font-size: 0.85em;
            position: relative;
        }
        .dia-semana {
            font-weight: bold;
            background-color: #f8f9fa;
            min-height: auto;
            padding: 10px 5px;
            color: #495057;
        }
        .dia-numero {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1.1em;
            color: #6c757d;
        }
        .dia-calendario.otro-mes .dia-numero {
            color: #ced4da;
        }
        .dia-calendario.hoy .dia-numero {
            background-color: #88c057;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            line-height: 28px;
            display: inline-block;
            padding: 0;
            font-size: 1em;
        }
        .evento-calendario {
            font-size: 0.78em;
            padding: 3px 5px;
            border-radius: 4px;
            margin-top: 3px;
            width: 95%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            border-left: 3px solid;
            text-align: left;
            cursor: default;
        }
        .evento-tratamiento {
            border-left-color: #3498db;
            background-color: #eaf5fc;
            color: #2980b9;
        }
        .evento-riego {
            border-left-color: #1abc9c;
            background-color: #e8f8f5;
            color: #16a085;
        }
        .leyenda {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .leyenda h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }
        .leyenda-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.9em;
            color: #555;
        }
        .leyenda-color {
            width: 15px;
            height: 15px;
            margin-right: 10px;
            border-radius: 3px;
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
            <a href="miscultivos.php">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario.php" class="active">Calendario y Horarios</a>
            <a href="configuracion.php" class="card">Configuración</a>
              <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar sesión</a>
        </div>
    </div>

    <div class="content calendario">
        <h2>Calendario General de Actividades</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <div id="calendario-container">
            <div class="calendario-header">
                <button id="mes-anterior">&lt; Anterior</button>
                <h3 id="mes-anio-actual"></h3>
                <button id="mes-siguiente">Siguiente &gt;</button>
            </div>
            <div class="calendario-grid">
                <div class="dia-semana">Dom</div>
                <div class="dia-semana">Lun</div>
                <div class="dia-semana">Mar</div>
                <div class="dia-semana">Mié</div>
                <div class="dia-semana">Jue</div>
                <div class="dia-semana">Vie</div>
                <div class="dia-semana">Sáb</div>
            </div>
            <div class="calendario-grid" id="dias-calendario-grid">
                <!-- Días generados por JS -->
            </div>
        </div>
        <div class="leyenda">
            <h4>Leyenda:</h4>
            <div class="leyenda-item"><span class="leyenda-color" style="background-color: #eaf5fc; border-left: 3px solid #3498db;"></span> Tratamiento Programado</div>
            <div class="leyenda-item"><span class="leyenda-color" style="background-color: #e8f8f5; border-left: 3px solid #1abc9c;"></span> Riego Estimado</div>
        </div>
    </div>

    <script>
        const eventosDesdePHP = <?php echo json_encode($eventos_calendario); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const calendarioGrid = document.getElementById('dias-calendario-grid');
            const mesAnioActualLabel = document.getElementById('mes-anio-actual');
            const btnMesAnterior = document.getElementById('mes-anterior');
            const btnMesSiguiente = document.getElementById('mes-siguiente');

            let fechaActualVisualizada = new Date();

            function formatearFecha(dateObj) {
                const anio = dateObj.getFullYear();
                const mes = String(dateObj.getMonth() + 1).padStart(2, '0');
                const dia = String(dateObj.getDate()).padStart(2, '0');
                return `${anio}-${mes}-${dia}`;
            }

            function renderizarCalendario() {
                calendarioGrid.innerHTML = '';
                const anio = fechaActualVisualizada.getFullYear();
                const mes = fechaActualVisualizada.getMonth();

                mesAnioActualLabel.textContent = `${fechaActualVisualizada.toLocaleString('es-ES', { month: 'long' })} ${anio}`;

                const primerDiaDelMes = new Date(anio, mes, 1).getDay();
                const diasEnMes = new Date(anio, mes + 1, 0).getDate();
                const offsetPrimerDia = primerDiaDelMes;

                for (let i = 0; i < offsetPrimerDia; i++) {
                    calendarioGrid.appendChild(document.createElement('div')).classList.add('dia-calendario', 'otro-mes');
                }

                const hoyStr = formatearFecha(new Date());
                for (let dia = 1; dia <= diasEnMes; dia++) {
                    const celdaDia = document.createElement('div');
                    celdaDia.classList.add('dia-calendario');
                    
                    const fechaCeldaStr = formatearFecha(new Date(anio, mes, dia));
                    
                    const diaNumero = document.createElement('span');
                    diaNumero.classList.add('dia-numero');
                    diaNumero.textContent = dia;
                    celdaDia.appendChild(diaNumero);

                    if (fechaCeldaStr === hoyStr) {
                        celdaDia.classList.add('hoy');
                    }

                    eventosDesdePHP.filter(e => e.start === fechaCeldaStr).forEach(evento => {
                        const divEvento = document.createElement('div');
                        divEvento.classList.add('evento-calendario');
                        if (evento.className) {
                            divEvento.classList.add(evento.className);
                        }
                        divEvento.textContent = evento.title;
                        divEvento.title = evento.description || evento.title;
                        celdaDia.appendChild(divEvento);
                    });
                    calendarioGrid.appendChild(celdaDia);
                }
            }

            btnMesAnterior.addEventListener('click', () => {
                fechaActualVisualizada.setMonth(fechaActualVisualizada.getMonth() - 1);
                renderizarCalendario();
            });

            btnMesSiguiente.addEventListener('click', () => {
                fechaActualVisualizada.setMonth(fechaActualVisualizada.getMonth() + 1);
                renderizarCalendario();
            });
            
            renderizarCalendario();
        });
    </script>
</body>
</html>