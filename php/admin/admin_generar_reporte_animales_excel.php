<?php
session_start();
require_once 'conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    die("Acceso no autorizado.");
}
if (!isset($pdo)) { die("Error: Conexión a la base de datos no disponible."); }

$filtro_tipo_animal = isset($_GET['tipo_animal']) ? trim($_GET['tipo_animal']) : null;
$titulo_extra_reporte = "";
$condiciones_sql_extra = "";

if ($filtro_tipo_animal) {
    $titulo_extra_reporte = " (Tipo: " . htmlspecialchars($filtro_tipo_animal) . ")";
    $condiciones_sql_extra = " WHERE a.tipo_animal = :tipo_animal_filtro";
}

try {
    $sql_reporte = "SELECT
                        a.id_animal, a.nombre_animal, a.tipo_animal, a.cantidad, a.raza,
                        a.fecha_nacimiento, a.sexo, a.identificador_unico, a.fecha_registro,
                        u.nombre AS nombre_usuario, u.email AS email_usuario
                    FROM animales a
                    JOIN usuarios u ON a.id_usuario = u.id_usuario
                    " . $condiciones_sql_extra . "
                    ORDER BY a.tipo_animal ASC, u.nombre ASC, a.fecha_registro DESC";

    $stmt_reporte = $pdo->prepare($sql_reporte);
    if ($filtro_tipo_animal) {
        $stmt_reporte->bindParam(':tipo_animal_filtro', $filtro_tipo_animal);
    }
    $stmt_reporte->execute();
    $animales_para_reporte = $stmt_reporte->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte Animales');
    
    // --- ESTILOS ---
    $header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '28a745']] // Verde
    ];
    $sub_header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3498db']] // Azul
    ];
    $data_style_array = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]
    ];

    $fila_actual = 1;
    $sheet->mergeCells('A'.$fila_actual.':K'.$fila_actual); // 11 columnas
    $sheet->setCellValue('A'.$fila_actual, 'REPORTE DE ANIMALES REGISTRADOS - GAG' . $titulo_extra_reporte);
    $sheet->getStyle('A'.$fila_actual)->applyFromArray($header_style_array);
    $sheet->getRowDimension($fila_actual)->setRowHeight(25);
    $fila_actual += 2;

    if (empty($animales_para_reporte)) {
        $sheet->setCellValue('A'.$fila_actual, 'No hay animales para los criterios seleccionados.');
    } else {
        $header_tabla = ['ID', 'Nombre', 'Tipo', 'Cantidad', 'Raza', 'Usuario', 'Email Usuario', 'F. Nacimiento', 'Sexo', 'ID Único', 'F. Registro'];
        $col_letra = 'A';
        foreach ($header_tabla as $header_col) {
            $sheet->setCellValue($col_letra.$fila_actual, $header_col);
            $col_letra++;
        }
        $ultima_col_header = chr(ord('A') + count($header_tabla) - 1);
        $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($sub_header_style_array);
        $fila_actual++;

        foreach ($animales_para_reporte as $animal_item) {
            $sheet->setCellValue('A'.$fila_actual, $animal_item['id_animal']);
            $sheet->setCellValue('B'.$fila_actual, $animal_item['nombre_animal'] ?: 'N/A');
            $sheet->setCellValue('C'.$fila_actual, $animal_item['tipo_animal']);
            $sheet->setCellValue('D'.$fila_actual, $animal_item['cantidad'] ?: 1);
            $sheet->setCellValue('E'.$fila_actual, $animal_item['raza'] ?: 'N/A');
            $sheet->setCellValue('F'.$fila_actual, $animal_item['nombre_usuario']);
            $sheet->setCellValue('G'.$fila_actual, $animal_item['email_usuario']);
            $sheet->setCellValue('H'.$fila_actual, $animal_item['fecha_nacimiento'] ? date("d/m/Y", strtotime($animal_item['fecha_nacimiento'])) : 'N/A');
            $sheet->setCellValue('I'.$fila_actual, $animal_item['sexo']);
            $sheet->setCellValue('J'.$fila_actual, $animal_item['identificador_unico'] ?: 'N/A');
            $sheet->setCellValue('K'.$fila_actual, date("d/m/Y H:i", strtotime($animal_item['fecha_registro'])));
            
            $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($data_style_array);
            $fila_actual++;
        }
    }

    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $nombre_archivo_sufijo = $filtro_tipo_animal ? '_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $filtro_tipo_animal) : '_General';
    $nombre_archivo = 'Reporte_Animales_GAG' . $nombre_archivo_sufijo . '_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log("Error al generar reporte de animales: " . $e->getMessage());
    die("Error al generar el reporte. Por favor, contacte al administrador.");
}
?>