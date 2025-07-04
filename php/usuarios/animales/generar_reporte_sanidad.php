<?php
session_start();
require_once '../../conexion.php'; // Sube dos niveles
require_once __DIR__ . '/../../../vendor/autoload.php'; // Sube tres niveles

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 1. --- VERIFICACIÓN DE SEGURIDAD Y PARÁMETROS ---
if (!isset($_SESSION['id_usuario'])) {
    die("Acceso no autorizado.");
}
$id_usuario_actual = $_SESSION['id_usuario'];

if (!isset($_GET['id_animal']) || !is_numeric($_GET['id_animal'])) {
    die("ID de animal no válido.");
}
$id_animal_reporte = (int)$_GET['id_animal'];

if (!isset($pdo)) {
    die("Error de conexión a la BD.");
}

try {
    // 2. --- VALIDACIÓN DE PERTENENCIA DEL ANIMAL ---
    $stmt_animal = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal FROM animales WHERE id_animal = :id_animal AND id_usuario = :id_usuario");
    $stmt_animal->bindParam(':id_animal', $id_animal_reporte, PDO::PARAM_INT);
    $stmt_animal->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_animal->execute();
    $animal_info = $stmt_animal->fetch(PDO::FETCH_ASSOC);

    if (!$animal_info) {
        die("Animal no encontrado o no tienes permiso para este reporte.");
    }
    
    // 3. --- OBTENCIÓN DE DATOS PARA EL REPORTE ---
    $sql_reporte = "SELECT 
                        fecha_aplicacion, nombre_producto_aplicado, tipo_aplicacion_registrada, 
                        dosis_aplicada, via_administracion, responsable_aplicacion, 
                        observaciones, fecha_proxima_dosis_sugerida
                    FROM registro_sanitario_animal
                    WHERE id_animal = :id_animal
                    ORDER BY fecha_aplicacion DESC";

    $stmt_reporte = $pdo->prepare($sql_reporte);
    $stmt_reporte->bindParam(':id_animal', $id_animal_reporte, PDO::PARAM_INT);
    $stmt_reporte->execute();
    $sanidad_para_reporte = $stmt_reporte->fetchAll(PDO::FETCH_ASSOC);

    // 4. --- CREACIÓN Y CONFIGURACIÓN DEL DOCUMENTO EXCEL ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Historial Sanitario');

    // 5. --- DEFINICIÓN DE ESTILOS ---
    $header_style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'dc3545']] // Rojo Sanidad
    ];
    $sub_header_style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '343a40']] // Gris oscuro
    ];
    $data_style = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]
    ];
    
    $fila_actual = 1;

    // 6. --- ESCRITURA DE DATOS EN LA HOJA DE CÁLCULO ---
    $nombre_completo_animal = $animal_info['tipo_animal'] . (!empty($animal_info['nombre_animal']) ? ' "' . $animal_info['nombre_animal'] . '"' : '');
    $sheet->mergeCells('A'.$fila_actual.':H'.$fila_actual); // Ajustado a 8 columnas (A-H)
    $sheet->setCellValue('A'.$fila_actual, 'HISTORIAL SANITARIO - ' . strtoupper(htmlspecialchars($nombre_completo_animal)));
    $sheet->getStyle('A'.$fila_actual)->applyFromArray($header_style);
    $sheet->getRowDimension($fila_actual)->setRowHeight(25);
    $fila_actual += 2;

    if (empty($sanidad_para_reporte)) {
        $sheet->setCellValue('A'.$fila_actual, 'No hay registros sanitarios para reportar.');
    } else {
        // Encabezados de la tabla
        $header_tabla = ['Fecha Aplicación', 'Producto', 'Tipo', 'Dosis', 'Vía', 'Responsable', 'Próx. Dosis', 'Observaciones'];
        $col_letra = 'A';
        foreach ($header_tabla as $header_col) {
            $sheet->setCellValue($col_letra.$fila_actual, $header_col);
            $col_letra++;
        }
        $ultima_col_header = chr(ord('A') + count($header_tabla) - 1);
        $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($sub_header_style);
        $fila_actual++;

        // Datos del historial sanitario
        foreach ($sanidad_para_reporte as $reg) {
            $sheet->setCellValue('A'.$fila_actual, htmlspecialchars(date("d/m/Y", strtotime($reg['fecha_aplicacion']))));
            $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($reg['nombre_producto_aplicado']));
            $sheet->setCellValue('C'.$fila_actual, htmlspecialchars($reg['tipo_aplicacion_registrada']));
            $sheet->setCellValue('D'.$fila_actual, htmlspecialchars($reg['dosis_aplicada'] ?: '-'));
            $sheet->setCellValue('E'.$fila_actual, htmlspecialchars($reg['via_administracion'] ?: '-'));
            $sheet->setCellValue('F'.$fila_actual, htmlspecialchars($reg['responsable_aplicacion'] ?: '-'));
            $sheet->setCellValue('G'.$fila_actual, $reg['fecha_proxima_dosis_sugerida'] ? htmlspecialchars(date("d/m/Y", strtotime($reg['fecha_proxima_dosis_sugerida']))) : '-');
            $sheet->setCellValue('H'.$fila_actual, htmlspecialchars($reg['observaciones'] ?: '-'));
            
            $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($data_style);
            $fila_actual++;
        }
    }

    // 7. --- AJUSTES FINALES Y DESCARGA ---
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $nombre_archivo_base = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre_completo_animal);
    $nombre_archivo = 'Reporte_Sanidad_' . $nombre_archivo_base . '_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log("Error en generar_reporte_sanidad.php: " . $e->getMessage());
    die("Error general al generar el reporte.");
}
?>