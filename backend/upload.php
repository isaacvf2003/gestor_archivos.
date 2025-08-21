<?php
// backend/upload.php
require_once __DIR__.'/session_boot.php';
include 'db.php';

if (!isset($_SESSION['usuario_id'])) exit('No autorizado');

$id_usuario = intval($_SESSION['usuario_id']);
$id_carpeta  = intval($_POST['id_carpeta'] ?? 0);
$desc        = $_POST['descripcion'] ?? null;

if (!$id_carpeta) exit('Falta carpeta');

// === config y helpers ===
$ruta_base = __DIR__ . '/../archivos/';
function safe_name($s){ return preg_replace('/[^a-zA-Z0-9_\-]/','_', $s); }
function rutaJerarquicaDesdeId(PDO $pdo, ?int $id): string {
    $p = [];
    while ($id !== null) {
        $st = $pdo->prepare("SELECT nombre,id_padre FROM carpetas WHERE id=?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) break;
        array_unshift($p, safe_name($row['nombre']));
        $id = $row['id_padre'];
    }
    return implode('/', $p);
}


$st = $pdo->prepare("SELECT nombre FROM usuarios WHERE id=?");
$st->execute([$id_usuario]);
$u = $st->fetch(PDO::FETCH_ASSOC);
if (!$u) exit('Usuario inválido');

$ruta_rel = rutaUsuarioDesdeId($pdo, $id_carpeta, $id_usuario);
$folder   = rtrim($ruta_base . safe_name($u['nombre']) . '/' . $ruta_rel, '/'); 

if (!is_dir($folder)) mkdir($folder, 0777, true);

if (!isset($_FILES['archivos']) || !is_array($_FILES['archivos']['name'])) {
    exit('No se recibieron archivos');
}

$totalArchivos = count($_FILES['archivos']['name']);
$resultados = [];

for ($i = 0; $i < $totalArchivos; $i++) {
    $nombre  = basename($_FILES['archivos']['name'][$i]);
    $tmp     = $_FILES['archivos']['tmp_name'][$i];

    if ($nombre === '' || $tmp === '') {
        $resultados[] = ['archivo' => $nombre, 'ok' => false, 'error' => 'Archivo inválido'];
        continue;
    }

    $timestamp = date('Y-m-d_H-i-s'); 
    $extension = pathinfo($nombre, PATHINFO_EXTENSION);
    $nombreBase = pathinfo($nombre, PATHINFO_FILENAME);
    $nombreFisico = $nombreBase . '_' . $timestamp . '.' . $extension;

    $destino = $folder . '/' . $nombreFisico;

    if (move_uploaded_file($tmp, $destino)) {

        $st = $pdo->prepare("INSERT INTO archivos (nombre_archivo, ruta, id_usuario, id_carpeta, descripcion) VALUES (?,?,?,?,?)");
        $st->execute([$nombre, $destino, $id_usuario, $id_carpeta, $desc]);
        $resultados[] = ['archivo' => $nombre, 'ok' => true];
    } else {
        $resultados[] = ['archivo' => $nombre, 'ok' => false, 'error' => 'Error al mover el archivo'];
    }
}

function rutaUsuarioDesdeId(PDO $pdo, int $id_carpeta, int $id_usuario): string {
   
    $segmentos = [];
    $id = $id_carpeta;
    while ($id !== null) {
        $st = $pdo->prepare("
            SELECT c.id, c.nombre, c.id_padre,
                   (SELECT nombre_alias FROM carpetas_alias
                      WHERE user_id = ? AND carpeta_id = c.id) AS alias_nombre
            FROM carpetas c
            WHERE c.id = ?
        ");
        $st->execute([$id_usuario, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) break;

        $segmento = safe_name($row['alias_nombre'] ?: $row['nombre']);
        array_unshift($segmentos, $segmento);
        $id = $row['id_padre'];
    }
    return implode('/', $segmentos); 
}

echo json_encode(['ok' => true, 'archivos' => $resultados]);
