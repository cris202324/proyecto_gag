<?php
session_start();
// 1. --- INCLUSIÓN DE DEPENDENCIAS ---
require_once '../../conexion.php'; // Sube dos niveles para la conexión
require_once __DIR__ . '/../../../vendor/autoload.php'; // Sube tres niveles para vendor

// 2. --- IMPORTACIÓN DE CLASES DE PHPSPREADSHEET ---
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 3. --- VERIFICACIÓN DE SEGURIDAD Y PARÁMETROS ---
if (!isset($_SESSION['id_usuario'])) {
    die("Acceso no autorizado. Por favor, inicie sesión.");
}
$id_usuario_actual = $_SESSION['id_usuario'];
// Asumimos que guardas el nombre en la sesión al hacer login. Si no, puedes quitarlo o hacer una consulta para obtenerlo.
$nombre_usuario_actual = $_SESSION['nombre_usuario'] ?? 'Usuario'; 

if (!isset($_GET['id_animal']) || !is_numeric($_GET['id_animal'])) {
    die("ID de animal no válido o no proporcionado.");
}
$id_animal_reporte = (int)$_GET['id_animal'];

if (!isset($pdo)) {
    die("Error crítico: La conexión a la base de datos no está disponible.");
}

try {
    // 4. --- VALIDACIÓN DE PERTENENCIA DEL ANIMAL ---
    $stmt_animal = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal FROM animales WHERE id_animal = :id_animal AND id_usuario = :id_usuario");
    $stmt_animal->bindParam(':id_animal', $id_animal_reporte, PDO::PARAM_INT);
    $stmt_animal->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_animal->execute();
    $animal_info = $stmt_animal->fetch(PDO::FETCH_ASSOC);

    if (!$animal_info) {
        die("Animal no encontrado o no tienes permiso para generar este reporte.");
    }
    
    // 5. --- OBTENCIÓN DE DATOS PARA EL REPORTE ---
    $sql_reporte = "SELECT 
                        tipo_alimento, cantidad_diaria, unidad_cantidad, 
                        frecuencia_alimentacion, fecha_registro_alimentacion, observaciones
                    FROM alimentacion
                    WHERE id_animal = :id_animal
                    ORDER BY fecha_registro_alimentacion DESC";

    $stmt_reporte = $pdo->prepare($sql_reporte);
    $stmt_reporte->bindParam(':id_animal', $id_animal_reporte, PDO::PARAM_INT);
    $stmt_reporte->execute();
    $alimentacion_para_reporte = $stmt_reporte->fetchAll(PDO::FETCH_ASSOC);

    // 6. --- CREACIÓN Y CONFIGURACIÓN DEL DOCUMENTO EXCEL ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Historial Alimentación');

    // 7. --- DEFINICIÓN DE ESTILOS PARA LAS CELDAS (AHORA INCLUIDOS) ---
    $header_style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17a2b8']] // Cian para Alimentación
    ];
    $sub_header_style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6c757d']] // Gris oscuro
    ];
    $data_style = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]
    ];
    
    $fila_actual = 1;

    // 8. --- ESCRITURA DE DATOS EN LA HOJA DE CÁLCULO ---
    // Título principal del reporte
    $nombre_completo_animal = $animal_info['tipo_animal'] . (!empty($animal_info['nombre_animal']) ? ' "' . $animal_info['nombre_animal'] . '"' : '');
    $sheet->mergeCells('A'.$fila_actual.':F'.$fila_actual);
    $sheet->setCellValue('A'.$fila_actual, 'HISTORIAL DE ALIMENTACIÓN - ' . strtoupper(htmlspecialchars($nombre_completo_animal)));
    $sheet->getStyle('A'.$fila_actual)->applyFromArray($header_style);
    $sheet->getRowDimension($fila_actual)->setRowHeight(25);
    $fila_actual += 2;

    if (empty($alimentacion_para_reporte)) {
        $sheet->setCellValue('A'.$fila_actual, 'No hay pautas de alimentación registradas para reportar.');
    } else {
        // Encabezados de la tabla
        $header_tabla = ['Fecha Pauta', 'Tipo Alimento', 'Cantidad Diaria', 'Unidad', 'Frecuencia', 'Observaciones'];
        $col_letra = 'A';
        foreach ($header_tabla as $header_col) {
            $sheet->setCellValue($col_letra.$fila_actual, $header_col);
            $col_letra++;
        }
        $ultima_col_header = chr(ord('A') + count($header_tabla) - 1);
        $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($sub_header_style);
        $fila_actual++;

        // Bucle para escribir cada fila de datos del historial
        foreach ($alimentacion_para_reporte as $pauta) {
            $sheet->setCellValue('A'.$fila_actual, htmlspecialchars(date("d/m/Y", strtotime($pauta['fecha_registro_alimentacion']))));
            $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($pauta['tipo_alimento']));
            $sheet->setCellValue('C'.$fila_actual, $pauta['cantidad_diaria']); // Para números, no usar htmlspecialchars
            $sheet->setCellValue('D'.$fila_actual, htmlspecialchars($pauta['unidad_cantidad']));
            $sheet->setCellValue('E'.$fila_actual, htmlspecialchars($pauta['frecuencia_alimentacion']));
            $sheet->setCellValue('F'.$fila_actual, htmlspecialchars($pauta['observaciones'] ?: '-'));
            
            $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($data_style);
            $fila_actual++;
        }
    }

    // 9. --- AJUSTES FINALES Y DESCARGA ---
    // Ajustar el ancho de todas las columnas automáticamente al contenido.
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Preparar un nombre de archivo dinámico y seguro.
    $nombre_archivo_base = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre_completo_animal);
    $nombre_archivo = 'Reporte_Alimentacion_' . $nombre_archivo_base . '_' . date('Ymd_His') . '.xlsx';

    // 10. --- ENVÍO DE HEADERS HTTP PARA LA DESCARGA ---
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');

    // 11. --- GUARDAR Y ENVIAR EL ARCHIVO ---
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    // Captura de errores generales. Es importante loguearlos para depuración.
    error_log("Error en generar_reporte_alimentacion.php: " . $e->getMessage());
    die("Error general al generar el reporte. Por favor, contacte a soporte.");
}
?>