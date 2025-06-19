<?php
session_start();
require_once 'conexion.php'; // Asegúrate que esta ruta es correcta desde donde guardes este archivo
require_once __DIR__ . '/../vendor/autoload.php'; // Ajusta la ruta al autoload de Composer si es necesario

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    die("Acceso no autorizado. Por favor, inicie sesión.");
}
$id_usuario_actual = $_SESSION['id_usuario'];

if (!isset($_GET['id_cultivo']) || !is_numeric($_GET['id_cultivo'])) {
    die("ID de cultivo no válido o no proporcionado.");
}
$id_cultivo_reporte = (int)$_GET['id_cultivo'];

if (!isset($pdo)) {
    die("Error crítico: La conexión a la base de datos no está disponible.");
}

try {
    // 1. OBTENER DATOS PRINCIPALES DEL CULTIVO
    $sql_cultivo_detalle = "SELECT
                                c.id_cultivo, c.fecha_inicio, c.fecha_fin AS fecha_fin_registrada,
                                c.area_hectarea, tc.nombre_cultivo,
                                m.nombre AS nombre_municipio,
                                ecd.nombre_estado AS estado_actual_cultivo,
                                u.nombre AS nombre_propietario, u.email AS email_propietario
                            FROM cultivos c
                            JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                            JOIN municipio m ON c.id_municipio = m.id_municipio
                            JOIN usuarios u ON c.id_usuario = u.id_usuario
                            LEFT JOIN estado_cultivo_definiciones ecd ON c.id_estado_cultivo = ecd.id_estado_cultivo
                            WHERE c.id_cultivo = :id_cultivo AND c.id_usuario = :id_usuario_actual_param";
    $stmt_cultivo = $pdo->prepare($sql_cultivo_detalle);
    $stmt_cultivo->bindParam(':id_cultivo', $id_cultivo_reporte, PDO::PARAM_INT);
    $stmt_cultivo->bindParam(':id_usuario_actual_param', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_cultivo->execute();
    $cultivo_info = $stmt_cultivo->fetch(PDO::FETCH_ASSOC);

    if (!$cultivo_info) {
        die("Cultivo no encontrado o no tiene permiso para generar este reporte.");
    }

    // 2. OBTENER TRATAMIENTOS DEL CULTIVO
    $sql_tratamientos_detalle = "SELECT tipo_tratamiento, producto_usado, etapas, dosis, 
                                     DATE_FORMAT(fecha_aplicacion_estimada, '%d/%m/%Y') as fecha_estimada_f,
                                     DATE_FORMAT(fecha_realizacion_real, '%d/%m/%Y') as fecha_real_f,
                                     estado_tratamiento, observaciones_realizacion, observaciones AS observaciones_plan
                               FROM tratamiento_cultivo 
                               WHERE id_cultivo = :id_cultivo ORDER BY fecha_aplicacion_estimada ASC";
    $stmt_tratamientos = $pdo->prepare($sql_tratamientos_detalle);
    $stmt_tratamientos->bindParam(':id_cultivo', $id_cultivo_reporte, PDO::PARAM_INT);
    $stmt_tratamientos->execute();
    $tratamientos_info = $stmt_tratamientos->fetchAll(PDO::FETCH_ASSOC);

    // Aquí podrías añadir consultas para RIEGO, ANÁLISIS DE SUELO, PRODUCCIÓN si las tuvieras y quisieras incluirlas.

    // --- CREAR EL ARCHIVO EXCEL ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte Cultivo ' . htmlspecialchars($cultivo_info['nombre_cultivo']));

    // --- ESTILOS (puedes copiar los de tu otro script de reporte) ---
    $header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
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
    $title_style_array = [
        'font' => ['bold' => true, 'size' => 12],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
    ];


    $fila_actual = 1;

    // Título del Reporte
    $sheet->mergeCells('A'.$fila_actual.':G'.$fila_actual); // Ajusta G al número de columnas máximo que uses
    $sheet->setCellValue('A'.$fila_actual, 'REPORTE DEL CULTIVO: ' . strtoupper(htmlspecialchars($cultivo_info['nombre_cultivo'])));
    $sheet->getStyle('A'.$fila_actual)->applyFromArray($header_style_array);
    $sheet->getRowDimension($fila_actual)->setRowHeight(25);
    $fila_actual += 2; // Espacio

    // Sección de Información General del Cultivo
    $sheet->setCellValue('A'.$fila_actual, 'INFORMACIÓN GENERAL DEL CULTIVO');
    $sheet->getStyle('A'.$fila_actual)->applyFromArray($title_style_array);
    $fila_actual++;
    $info_general_headers = [
        'Propietario:', htmlspecialchars($cultivo_info['nombre_propietario'] . ' (' . $cultivo_info['email_propietario'] . ')'),
        'Tipo de Cultivo:', htmlspecialchars($cultivo_info['nombre_cultivo']),
        'Fecha Inicio:', htmlspecialchars(date("d/m/Y", strtotime($cultivo_info['fecha_inicio']))),
        'Fecha Fin (Estimada/Real):', htmlspecialchars(date("d/m/Y", strtotime($cultivo_info['fecha_fin_registrada']))),
        'Área (ha):', htmlspecialchars($cultivo_info['area_hectarea']),
        'Municipio:', htmlspecialchars($cultivo_info['nombre_municipio']),
        'Estado Actual:', htmlspecialchars($cultivo_info['estado_actual_cultivo'] ?: 'No definido')
    ];
    for ($i = 0; $i < count($info_general_headers); $i += 2) {
        $sheet->setCellValue('A'.$fila_actual, $info_general_headers[$i]);
        $sheet->getStyle('A'.$fila_actual)->getFont()->setBold(true);
        $sheet->mergeCells('B'.$fila_actual.':D'.$fila_actual); // Unir celdas para el valor
        $sheet->setCellValue('B'.$fila_actual, $info_general_headers[$i+1]);
        $sheet->getStyle('A'.$fila_actual.':D'.$fila_actual)->applyFromArray($data_style_array);
        $fila_actual++;
    }
    $fila_actual++; // Espacio

    // Sección de Tratamientos
    $sheet->setCellValue('A'.$fila_actual, 'PLAN DE TRATAMIENTOS');
    $sheet->getStyle('A'.$fila_actual)->applyFromArray($title_style_array);
    $fila_actual++;
    if (!empty($tratamientos_info)) {
        $header_tratamientos = ['Tipo', 'Producto', 'Etapas', 'Dosis', 'F. Estimada', 'F. Realizada', 'Estado', 'Obs. Plan', 'Obs. Realización'];
        $col_letra = 'A';
        foreach ($header_tratamientos as $header) {
            $sheet->setCellValue($col_letra.$fila_actual, $header);
            $col_letra++;
        }
        $sheet->getStyle('A'.$fila_actual.':' . chr(ord($col_letra)-1) . $fila_actual)->applyFromArray($sub_header_style_array);
        $fila_actual++;

        foreach ($tratamientos_info as $trat) {
            $sheet->setCellValue('A'.$fila_actual, htmlspecialchars($trat['tipo_tratamiento']));
            $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($trat['producto_usado']));
            $sheet->setCellValue('C'.$fila_actual, htmlspecialchars($trat['etapas']));
            $sheet->setCellValue('D'.$fila_actual, htmlspecialchars($trat['dosis']));
            $sheet->setCellValue('E'.$fila_actual, htmlspecialchars($trat['fecha_estimada_f'] ?: '-'));
            $sheet->setCellValue('F'.$fila_actual, htmlspecialchars($trat['fecha_real_f'] ?: '-'));
            $sheet->setCellValue('G'.$fila_actual, htmlspecialchars($trat['estado_tratamiento']));
            $sheet->setCellValue('H'.$fila_actual, htmlspecialchars($trat['observaciones_plan'] ?: '-'));
            $sheet->setCellValue('I'.$fila_actual, htmlspecialchars($trat['observaciones_realizacion'] ?: '-'));
            $sheet->getStyle('A'.$fila_actual.':I'.$fila_actual)->applyFromArray($data_style_array);
            $fila_actual++;
        }
    } else {
        $sheet->setCellValue('A'.$fila_actual, 'No hay tratamientos registrados para este cultivo.');
        $fila_actual++;
    }
    $fila_actual++; // Espacio

    // Aquí irían las secciones para RIEGO, SUELO, PRODUCCIÓN si las añades...

    // Ajustar ancho de columnas
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Nombre del archivo
    $nombre_archivo = 'Reporte_Cultivo_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $cultivo_info['nombre_cultivo']) . '_' . $id_cultivo_reporte . '_' . date('Ymd_His') . '.xlsx';

    // Headers para la descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');
    // Si usas IE sobre SSL, puede ser necesario:
    // header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Fecha en el pasado
    // header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // Siempre modificado
    // header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
    // header('Pragma: public'); // HTTP/1.0

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    die("Error de base de datos al generar el reporte: " . $e->getMessage());
} catch (Exception $e) {
    die("Error general al generar el reporte: " . $e->getMessage() . " en la línea " . $e->getLine());
}
?>