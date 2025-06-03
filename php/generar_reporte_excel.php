<?php
session_start();
// No envíes headers HTTP antes de la depuración del autoloader si este falla.
// Los headers para la descarga del Excel se pondrán después si todo va bien.

require_once 'conexion.php'; // $pdo

// --- INICIO DEPURACIÓN AUTOLOADER ---
$autoloader_path = __DIR__ . '/../vendor/autoload.php'; // Asume: generar_reporte_excel.php está en php/ y vendor/ en la raíz del proyecto
                                                    // Si generar_reporte_excel.php está en la raíz, sería: __DIR__ . '/vendor/autoload.php'
                                                    // Si generar_reporte_excel.php está en php/admin/ y vendor en la raíz: __DIR__ . '/../../vendor/autoload.php'

echo "<!DOCTYPE html><html><head><title>Depuración Autoloader</title></head><body>"; // Empezar HTML para mensajes
echo "<h3>Depuración de Carga de PhpSpreadsheet:</h3>";
echo "<p>Directorio actual del script (generar_reporte_excel.php): " . __DIR__ . "</p>";
echo "<p>Ruta calculada para autoload.php: " . htmlspecialchars($autoloader_path) . "</p>";
echo "<p>Ruta absoluta resuelta para autoload.php: " . (file_exists($autoloader_path) ? realpath($autoloader_path) : "NO ENCONTRADO") . "</p>";

if (file_exists($autoloader_path)) {
    require_once $autoloader_path;
    echo "<p style='color:green;'><strong>ÉXITO:</strong> Autoloader de Composer incluido desde '" . realpath($autoloader_path) . "'.</p>";
    
    // Intentar verificar si la clase Spreadsheet existe ahora
    if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        echo "<p style='color:green;'><strong>VERIFICACIÓN:</strong> La clase 'PhpOffice\\PhpSpreadsheet\\Spreadsheet' SÍ existe después de incluir el autoloader.</p>";
    } else {
        echo "<p style='color:red;'><strong>ERROR DE VERIFICACIÓN:</strong> La clase 'PhpOffice\\PhpSpreadsheet\\Spreadsheet' NO existe después de incluir el autoloader. Esto sugiere un problema con la instalación de PhpSpreadsheet vía Composer o con el propio autoloader.</p>";
    }

} else {
    echo "<p style='color:red;'><strong>ERROR CRÍTICO: El archivo autoload.php de Composer NO SE ENCONTRÓ en la ruta esperada: " . htmlspecialchars($autoloader_path) . "</strong></p>";
    echo "<p><strong>Posibles Soluciones:</strong></p>";
    echo "<ul>";
    echo "<li>Asegúrate de haber ejecutado <code>composer require phpoffice/phpspreadsheet</code> en la raíz de tu proyecto (probablemente <code>C:\\xampp\\htdocs\\proyecto_gag\\</code>).</li>";
    echo "<li>Verifica que la variable <code>\$autoloader_path</code> en este script (<code>generar_reporte_excel.php</code>) apunte correctamente a tu carpeta <code>vendor/autoload.php</code>.</li>";
    echo "<li>Comprueba que la carpeta <code>vendor</code> y sus contenidos existan en la raíz de tu proyecto.</li>";
    echo "</ul>";
    echo "</body></html>";
    die(); // Detener la ejecución si no se encuentra el autoloader
}
// --- FIN DEPURACIÓN AUTOLOADER ---


// Si el autoloader se cargó, los 'use' statements deberían funcionar
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    // Si llegamos aquí después de la depuración, significa que el autoloader funcionó
    // pero el acceso no es autorizado. No deberíamos enviar un Excel.
    echo "<p style='color:red;'>Acceso no autorizado para generar el reporte.</p></body></html>";
    exit();
}

if (!isset($pdo)) {
    echo "<p style='color:red;'>Error crítico: La conexión a la base de datos no está disponible.</p></body></html>";
    exit();
}

// Si todo está bien hasta aquí, podemos intentar generar el Excel.
// Para evitar "headers already sent" por los echos de depuración,
// comentaremos temporalmente la generación y descarga del Excel.
// DESCOMENTA EL SIGUIENTE BLOQUE CUANDO EL AUTOLOADER FUNCIONE Y LA CLASE SEA ENCONTRADA.

/* // ---- INICIO BLOQUE DE GENERACIÓN DE EXCEL (COMENTADO PARA DEPURACIÓN INICIAL DEL AUTOLOADER) ----
try {
    // --- 1. OBTENER TODOS LOS USUARIOS (NO ADMINS) ---
    $stmt_usuarios = $pdo->query("SELECT u.id_usuario, u.nombre AS nombre_usuario, u.email, r.rol AS nombre_rol, e.descripcion AS nombre_estado
                                  FROM usuarios u
                                  JOIN rol r ON u.id_rol = r.id_rol
                                  JOIN estado e ON u.id_estado = e.id_estado
                                  WHERE u.id_rol != 1 
                                  ORDER BY u.nombre ASC");
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet(); // Esta es la línea 36 original del error
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte General GAG');

    $header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']]
    ];
    $sub_header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '88C057']]
    ];
    $data_style_array = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]
    ];

    $sheet->mergeCells('A1:K1');
    $sheet->setCellValue('A1', 'REPORTE GENERAL - GESTIÓN AGRÍCOLA Y GANADERA (GAG)');
    $sheet->getStyle('A1')->applyFromArray($header_style_array);
    $sheet->getStyle('A1')->getFont()->setSize(16);
    $sheet->getRowDimension(1)->setRowHeight(30);
    
    $fila_actual = 3;

    if (empty($usuarios)) {
        $sheet->setCellValue('A'.$fila_actual, 'No hay usuarios (no administradores) para reportar.');
        $fila_actual++;
    }

    foreach ($usuarios as $usuario) {
        $sheet->mergeCells('A'.$fila_actual.':K'.$fila_actual);
        $sheet->setCellValue('A'.$fila_actual, 'Usuario: ' . htmlspecialchars($usuario['nombre_usuario']) . ' (ID: ' . htmlspecialchars($usuario['id_usuario']) . ')');
        $sheet->getStyle('A'.$fila_actual)->applyFromArray($sub_header_style_array);
        $sheet->getStyle('A'.$fila_actual)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $fila_actual++;

        $sheet->setCellValue('A'.$fila_actual, 'Email:');
        $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($usuario['email']));
        $sheet->setCellValue('C'.$fila_actual, 'Rol:');
        $sheet->setCellValue('D'.$fila_actual, htmlspecialchars($usuario['nombre_rol']));
        $sheet->setCellValue('E'.$fila_actual, 'Estado:');
        $sheet->setCellValue('F'.$fila_actual, htmlspecialchars($usuario['nombre_estado']));
        $sheet->getStyle('A'.$fila_actual.':F'.$fila_actual)->getFont()->setBold(true);
        $fila_actual++;
        $fila_actual++; 

        $stmt_cultivos = $pdo->prepare("SELECT tc.nombre_cultivo, c.fecha_inicio, c.fecha_fin, c.area_hectarea, m.nombre AS nombre_municipio
                                        FROM cultivos c
                                        JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                                        JOIN municipio m ON c.id_municipio = m.id_municipio
                                        WHERE c.id_usuario = :id_usuario ORDER BY c.fecha_inicio DESC");
        $stmt_cultivos->bindParam(':id_usuario', $usuario['id_usuario'], PDO::PARAM_STR);
        $stmt_cultivos->execute();
        $cultivos_del_usuario = $stmt_cultivos->fetchAll(PDO::FETCH_ASSOC);

        if ($cultivos_del_usuario) {
            $sheet->setCellValue('A'.$fila_actual, 'CULTIVOS DEL USUARIO');
            $sheet->getStyle('A'.$fila_actual)->getFont()->setBold(true)->setSize(12);
            $fila_actual++;
            $sheet->setCellValue('A'.$fila_actual, 'Nombre Cultivo');
            $sheet->setCellValue('B'.$fila_actual, 'Fecha Inicio');
            $sheet->setCellValue('C'.$fila_actual, 'Fecha Fin Estimada');
            $sheet->setCellValue('D'.$fila_actual, 'Área (ha)');
            $sheet->setCellValue('E'.$fila_actual, 'Municipio');
            $sheet->getStyle('A'.$fila_actual.':E'.$fila_actual)->applyFromArray($sub_header_style_array);
            $fila_actual++;

            foreach ($cultivos_del_usuario as $cultivo) {
                $sheet->setCellValue('A'.$fila_actual, htmlspecialchars($cultivo['nombre_cultivo']));
                $sheet->setCellValue('B'.$fila_actual, htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_inicio']))));
                $sheet->setCellValue('C'.$fila_actual, htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_fin']))));
                $sheet->setCellValue('D'.$fila_actual, htmlspecialchars($cultivo['area_hectarea']));
                $sheet->setCellValue('E'.$fila_actual, htmlspecialchars($cultivo['nombre_municipio']));
                $sheet->getStyle('A'.$fila_actual.':E'.$fila_actual)->applyFromArray($data_style_array);
                $fila_actual++;
            }
        } else {
            $sheet->setCellValue('A'.$fila_actual, 'Este usuario no tiene cultivos registrados.');
            $fila_actual++;
        }
        $fila_actual++; 

        $stmt_animales = $pdo->prepare("SELECT nombre_animal, tipo_animal, raza, fecha_nacimiento, sexo, identificador_unico, DATE_FORMAT(fecha_registro, '%d/%m/%Y') as fecha_reg
                                       FROM animales 
                                       WHERE id_usuario = :id_usuario ORDER BY fecha_registro DESC");
        $stmt_animales->bindParam(':id_usuario', $usuario['id_usuario'], PDO::PARAM_STR);
        $stmt_animales->execute();
        $animales_del_usuario = $stmt_animales->fetchAll(PDO::FETCH_ASSOC);

        if ($animales_del_usuario) {
            $sheet->setCellValue('A'.$fila_actual, 'ANIMALES DEL USUARIO');
            $sheet->getStyle('A'.$fila_actual)->getFont()->setBold(true)->setSize(12);
            $fila_actual++;
            $sheet->setCellValue('A'.$fila_actual, 'Nombre Animal');
            $sheet->setCellValue('B'.$fila_actual, 'Tipo');
            $sheet->setCellValue('C'.$fila_actual, 'Raza');
            $sheet->setCellValue('D'.$fila_actual, 'F. Nacimiento');
            $sheet->setCellValue('E'.$fila_actual, 'Sexo');
            $sheet->setCellValue('F'.$fila_actual, 'ID Único');
            $sheet->setCellValue('G'.$fila_actual, 'F. Registro');
            $sheet->getStyle('A'.$fila_actual.':G'.$fila_actual)->applyFromArray($sub_header_style_array);
            $fila_actual++;

            foreach ($animales_del_usuario as $animal) {
                $sheet->setCellValue('A'.$fila_actual, htmlspecialchars($animal['nombre_animal'] ?: 'N/A'));
                $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($animal['tipo_animal']));
                $sheet->setCellValue('C'.$fila_actual, htmlspecialchars($animal['raza'] ?: 'N/A'));
                $sheet->setCellValue('D'.$fila_actual, $animal['fecha_nacimiento'] ? htmlspecialchars(date("d/m/Y", strtotime($animal['fecha_nacimiento']))) : 'N/A');
                $sheet->setCellValue('E'.$fila_actual, htmlspecialchars($animal['sexo']));
                $sheet->setCellValue('F'.$fila_actual, htmlspecialchars($animal['identificador_unico'] ?: 'N/A'));
                $sheet->setCellValue('G'.$fila_actual, htmlspecialchars($animal['fecha_reg']));
                $sheet->getStyle('A'.$fila_actual.':G'.$fila_actual)->applyFromArray($data_style_array);
                $fila_actual++;
            }
        } else {
            $sheet->setCellValue('A'.$fila_actual, 'Este usuario no tiene animales registrados.');
            $fila_actual++;
        }
        $fila_actual++; 
        $fila_actual++; 
    }

    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    $nombre_archivo = 'Reporte_GAG_' . date('Ymd_His') . '.xlsx';
    
    // Limpiar cualquier salida previa si los echos de depuración estuvieron activos
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    // Limpiar cualquier salida previa si los echos de depuración estuvieron activos
    if (ob_get_length()) ob_end_clean();
    echo "<!DOCTYPE html><html><head><title>Error Reporte</title></head><body>";
    echo "<p style='color:red;'>Error de base de datos al generar el reporte: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</body></html>";
    exit;
} catch (Exception $e) { // Captura excepciones de PhpSpreadsheet también
    if (ob_get_length()) ob_end_clean();
    echo "<!DOCTYPE html><html><head><title>Error Reporte</title></head><body>";
    echo "<p style='color:red;'>Error general al generar el reporte: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</body></html>";
    exit;
}
// ---- FIN BLOQUE DE GENERACIÓN DE EXCEL ---- */

echo "</body></html>"; // Cerrar HTML si la generación de Excel está comentada
?>