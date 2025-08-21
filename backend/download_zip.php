    <?php

require_once __DIR__.'/session_boot.php';
include 'db.php';


if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  exit('No autorizado');
}

$id_carpeta = isset($_GET['id_carpeta']) ? intval($_GET['id_carpeta']) : 0;
if ($id_carpeta <= 0) exit('Parámetros inválidos');


if (!empty($_SESSION['es_admin']) && isset($_GET['id_usuario'])) {
  $id_usuario = intval($_GET['id_usuario']);
} else {
  $id_usuario = intval($_SESSION['usuario_id']);
}

$stC = $pdo->prepare("SELECT nombre FROM carpetas WHERE id=?");
$stC->execute([$id_carpeta]);
$carpeta = $stC->fetch(PDO::FETCH_ASSOC);
$nombre_carpeta = $carpeta ? preg_replace('/[^a-zA-Z0-9_\-]/','_', $carpeta['nombre']) : ('carpeta_'.$id_carpeta);


$st = $pdo->prepare("SELECT nombre_archivo, ruta FROM archivos WHERE id_usuario=? AND id_carpeta=? ORDER BY nombre_archivo");
$st->execute([$id_usuario, $id_carpeta]);
$files = $st->fetchAll(PDO::FETCH_ASSOC);

if (!$files || count($files) === 0) {
  header('Content-Type: text/plain; charset=utf-8');
  exit("No hay archivos para descargar en esta carpeta.");
}

$tmpZip = tempnam(sys_get_temp_dir(), 'zip');
if ($tmpZip === false) exit('No se pudo crear archivo temporal');

$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== TRUE) {
  @unlink($tmpZip);
  exit('No se pudo abrir ZIP');
}


$vistos = [];
foreach ($files as $f) {
  $fsPath = $f['ruta'];
  if (!is_file($fsPath)) continue;

  $name = $f['nombre_archivo'];

  $nameBase = $name;
  $i = 1;
  while (isset($vistos[strtolower($name)])) {

    $dot = strrpos($nameBase, '.');
    if ($dot !== false) {
      $name = substr($nameBase, 0, $dot) . " ($i)" . substr($nameBase, $dot);
    } else {
      $name = $nameBase . " ($i)";
    }
    $i++;
  }
  $vistos[strtolower($name)] = true;

  $zip->addFile($fsPath, $nombre_carpeta . '/' . $name);
}

$zip->close();


$fname = "archivos_{$nombre_carpeta}.zip";
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Content-Length: ' . filesize($tmpZip));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: public');

readfile($tmpZip);
@unlink($tmpZip);
exit;
