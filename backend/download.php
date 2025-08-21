<?php
require_once __DIR__.'/session_boot.php';
include 'db.php';

if (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}

file_put_contents(__DIR__."/debug_download.log", json_encode([
    "GET"     => $_GET,
    "POST"    => $_POST,
    "COOKIE"  => $_COOKIE,
    "SESSION" => $_SESSION,
    "TIME"    => date('c'),
    "UA"      => $_SERVER['HTTP_USER_AGENT'] ?? "",
]).PHP_EOL, FILE_APPEND);

$id_archivo = 0;
if (isset($_POST['id'])) {
    $id_archivo = intval($_POST['id']);
} elseif (isset($_GET['id'])) {
    $id_archivo = intval($_GET['id']);
}
if (!$id_archivo) exit('No autorizado. Falta ID.');


if (!isset($_SESSION['usuario_id'])) exit('No autorizado. Sin sesión');


$is_admin = isset($_SESSION['es_admin']) && $_SESSION['es_admin'];


$stmt = $pdo->prepare("SELECT * FROM archivos WHERE id=?");
$stmt->execute([$id_archivo]);
$archivo = $stmt->fetch();

if (!$archivo) exit('Archivo no encontrado.');


if (!$is_admin && $archivo['id_usuario'] != $_SESSION['usuario_id']) {
    exit('No autorizado. No eres dueño ni admin.');
}

$filepath = $archivo['ruta'];
if (!file_exists($filepath)) exit('Archivo no disponible en el servidor.');


header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
?>
