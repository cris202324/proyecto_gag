<?php
session_start();
require_once '../../conexion.php'; // Sube dos niveles para la conexión
require_once __DIR__ . '/../../../vendor/autoload.php'; // Sube tres niveles para vendor

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
$nombre_usuario_actual = $_SESSION['nombre_usuario']; // Asumimos que guardas el nombre en la sesión

if (!isset($pdo)) {
    die("Error crítico: La conexión a la base de datos no está disponible.");
}

try {
    // CONSULTA PARA OBTENER TODOS LOS ANIMALES DEL USUARIO
    $sql_reporte = "SELECT
                        id_animal, nombre_animal, tipo_animal, raza, fecha_nacimiento,
                        sexo, identificador_unico, cantidad, fecha_registro
                    FROM animales
                    WHERE id_usuario = :id_usuario
                    ORDER BY tipo_animal, fecha_registro DESC";

    $stmt_reporte = $pdo->prepare($sql_reporte);
    $stmt_reporte->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_reporte->execute();
    $animales_para_reporte = $stmt_reporte->fetchAll(PDO::FETCH_ASSOC);

    // --- CREAR EL ARCHIVO EXCEL ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Mis Animales');

    // --- ESTILOS (Puedes copiar/reutilizar de tus otros reportes) ---
    $header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0056b3']] // Azul para animales
    ];
    $sub_header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6c757d']] // Gris
    ];
    $data_style_array = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]
    ];
    
    $fila_actual = 1;

    // Título del Reporte
    $sheet->mergeCells('A'.$fila_actual.':H'.$fila_actual); // Ajusta H al número de columnas
    $sheet->setCellValue('A'.$fila_actual, 'REPORTE DE MIS ANIMALES - Usuario: ' . htmlspecialchars($nombre_usuario_actual));
    $sheet->getStyle('A'.$fila_actual)->applyFromArray($header_style_array);
    $sheet->getRowDimension($fila_actual)->setRowHeight(25);
    $fila_actual += 2;

    if (empty($animales_para_reporte)) {
        $sheet->setCellValue('A'.$fila_actual, 'No tienes animales registrados para reportar.');
    } else {
        // Encabezados de la tabla
        $header_tabla = ['Tipo Animal', 'Nombre/Lote', 'ID Adicional', 'Raza', 'Sexo', 'F. Nacimiento', 'Cantidad', 'F. Registro'];
        $col_letra = 'A';
        foreach ($header_tabla as $header_col) {
            $sheet->setCellValue($col_letra.$fila_actual, $header_col);
            $col_letra++;
        }
        $ultima_col_header = chr(ord('A') + count($header_tabla) - 1);
        $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($sub_header_style_array);
        $fila_actual++;

        // Datos de los animales
        foreach ($animales_para_reporte as $animal_item) {
            $sheet->setCellValue('A'.$fila_actual, htmlspecialchars($animal_item['tipo_animal']));
            $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($animal_item['nombre_animal'] ?: 'N/A'));
            $sheet->setCellValue('C'.$fila_actual, htmlspecialchars($animal_item['identificador_unico'] ?: 'N/A'));
            $sheet->setCellValue('D'.$fila_actual, htmlspecialchars($animal_item['raza'] ?: 'N/A'));
            $sheet->setCellValue('E'.$fila_actual, htmlspecialchars($animal_item['sexo']));
            $sheet->setCellValue('F'.$fila_actual, $animal_item['fecha_nacimiento'] ? htmlspecialchars(date("d/m/Y", strtotime($animal_item['fecha_nacimiento']))) : 'N/A');
            $sheet->setCellValue('G'.$fila_actual, htmlspecialchars($animal_item['cantidad']));
            $sheet->setCellValue('H'.$fila_actual, htmlspecialchars(date("d/m/Y", strtotime($animal_item['fecha_registro']))));
            
            $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($data_style_array);
            $fila_actual++;
        }
    }

    // Ajustar ancho de columnas
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Nombre del archivo
    $nombre_archivo = 'Reporte_Mis_Animales_' . date('Ymd_His') . '.xlsx';

    // Headers para la descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    // Loguear el error para depuración
    error_log("Error en generar_reporte_mis_animales.php: " . $e->getMessage());
    die("Error general al generar el reporte. Por favor, contacte a soporte si el problema persiste.");
}
?>