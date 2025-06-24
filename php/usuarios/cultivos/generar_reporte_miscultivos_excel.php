<?php
session_start();
require_once '../../conexion.php'; // Ajusta la ruta si es necesario
require_once 'C:/xampp/htdocs/proyecto_gag/vendor/autoload.php';

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

// Determinar el estado de los cultivos a reportar
$estado_param = isset($_GET['estado']) ? $_GET['estado'] : 'en_progreso'; // 'en_progreso' o 'terminado'

// IDs de estado de tu base de datos
$id_estado_en_progreso_db = 1;
$id_estado_terminado_db = 2;

$id_estado_filtro_db = ($estado_param === 'terminado') ? $id_estado_terminado_db : $id_estado_en_progreso_db;
$titulo_reporte_estado = ($estado_param === 'terminado') ? 'Terminados' : 'En Progreso';

if (!isset($pdo)) {
    die("Error crítico: La conexión a la base de datos no está disponible.");
}

try {
    // 1. OBTENER DATOS DEL USUARIO (para el encabezado del reporte)
    $stmt_usuario = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id_usuario = :id_usuario");
    $stmt_usuario->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_usuario->execute();
    $info_usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    $nombre_propietario = $info_usuario ? $info_usuario['nombre'] : 'Usuario Desconocido';

    // 2. OBTENER CULTIVOS DEL USUARIO SEGÚN EL ESTADO
    $sql_cultivos_reporte = "SELECT
                                c.id_cultivo, c.fecha_inicio, c.fecha_fin AS fecha_fin_registrada,
                                c.area_hectarea, tc.nombre_cultivo,
                                m.nombre AS nombre_municipio,
                                ecd.nombre_estado AS estado_actual_cultivo
                            FROM cultivos c
                            JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                            JOIN municipio m ON c.id_municipio = m.id_municipio
                            LEFT JOIN estado_cultivo_definiciones ecd ON c.id_estado_cultivo = ecd.id_estado_cultivo
                            WHERE c.id_usuario = :id_usuario_param AND c.id_estado_cultivo = :id_estado_filtro
                            ORDER BY c.fecha_inicio DESC";
    $stmt_cultivos = $pdo->prepare($sql_cultivos_reporte);
    $stmt_cultivos->bindParam(':id_usuario_param', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_cultivos->bindParam(':id_estado_filtro', $id_estado_filtro_db, PDO::PARAM_INT);
    $stmt_cultivos->execute();
    $cultivos_data = $stmt_cultivos->fetchAll(PDO::FETCH_ASSOC);

    // --- CREAR EL ARCHIVO EXCEL ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Cultivos ' . $titulo_reporte_estado);

    // --- ESTILOS (puedes copiar/reutilizar de tus otros reportes) ---
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

    $fila_actual = 1;

    // Título del Reporte
    $sheet->mergeCells('A'.$fila_actual.':F'.$fila_actual); // Ajusta F al número de columnas que uses
    $sheet->setCellValue('A'.$fila_actual, 'REPORTE DE CULTIVOS ' . strtoupper($titulo_reporte_estado) . ' - Usuario: ' . htmlspecialchars($nombre_propietario));
    $sheet->getStyle('A'.$fila_actual)->applyFromArray($header_style_array);
    $sheet->getRowDimension($fila_actual)->setRowHeight(25);
    $fila_actual += 2;

    if (empty($cultivos_data)) {
        $sheet->setCellValue('A'.$fila_actual, 'No hay cultivos ' . strtolower($titulo_reporte_estado) . ' para reportar.');
        $fila_actual++;
    } else {
        // Encabezados de la tabla de cultivos
        $header_tabla_cultivos = ['ID Cultivo', 'Tipo de Cultivo', 'Municipio', 'Fecha Inicio', 'Fecha Fin', 'Área (ha)', 'Estado'];
        $col_letra = 'A';
        foreach ($header_tabla_cultivos as $header) {
            $sheet->setCellValue($col_letra.$fila_actual, $header);
            $col_letra++;
        }
        // Aplicar estilo al encabezado de la tabla (A a G si son 7 columnas)
        $ultima_columna_header = chr(ord('A') + count($header_tabla_cultivos) - 1);
        $sheet->getStyle('A'.$fila_actual.':'.$ultima_columna_header.$fila_actual)->applyFromArray($sub_header_style_array);
        $fila_actual++;

        // Datos de los cultivos
        foreach ($cultivos_data as $cultivo_item) {
            $sheet->setCellValue('A'.$fila_actual, htmlspecialchars($cultivo_item['id_cultivo']));
            $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($cultivo_item['nombre_cultivo']));
            $sheet->setCellValue('C'.$fila_actual, htmlspecialchars($cultivo_item['nombre_municipio']));
            $sheet->setCellValue('D'.$fila_actual, htmlspecialchars(date("d/m/Y", strtotime($cultivo_item['fecha_inicio']))));
            $sheet->setCellValue('E'.$fila_actual, htmlspecialchars($cultivo_item['fecha_fin_registrada'] ? date("d/m/Y", strtotime($cultivo_item['fecha_fin_registrada'])) : 'N/A'));
            $sheet->setCellValue('F'.$fila_actual, htmlspecialchars($cultivo_item['area_hectarea']));
            $sheet->setCellValue('G'.$fila_actual, htmlspecialchars($cultivo_item['estado_actual_cultivo'] ?: $titulo_reporte_estado)); // Fallback
            
            $sheet->getStyle('A'.$fila_actual.':'.$ultima_columna_header.$fila_actual)->applyFromArray($data_style_array);
            $fila_actual++;
        }
    }

    // Ajustar ancho de columnas
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Nombre del archivo
    $nombre_archivo_base = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre_propietario);
    $nombre_archivo = 'Reporte_MisCultivos_' . $titulo_reporte_estado . '_' . $nombre_archivo_base . '_' . date('Ymd_His') . '.xlsx';

    // Headers para la descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    die("Error de base de datos al generar el reporte: " . $e->getMessage());
} catch (Exception $e) {
    // Loguear el error para depuración
    error_log("Error en generar_reporte_miscultivos_excel.php: " . $e->getMessage() . " en la línea " . $e->getLine() . "\nStack trace: " . $e->getTraceAsString());
    die("Error general al generar el reporte. Por favor, intente más tarde o contacte a soporte si el problema persiste.");
}
?>