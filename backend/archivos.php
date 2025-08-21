<?php

require_once __DIR__.'/session_boot.php';
include 'db.php';


if (isset($_GET['stats'])) {
  $row = $pdo->query("SELECT COUNT(*) AS total FROM archivos")->fetch();
  echo json_encode(['total' => $row['total']]);
  exit;
}
if (isset($_GET['ultimos'])) {
  $res = $pdo->query("SELECT archivos.*, usuarios.nombre as nombre_usuario FROM archivos JOIN usuarios ON archivos.id_usuario=usuarios.id ORDER BY fecha_subida DESC LIMIT 8");
  echo json_encode($res->fetchAll(PDO::FETCH_ASSOC));
  exit;
}

if (!isset($_SESSION['usuario_id'])) exit("No autorizado");

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);

 
    $stmt = $pdo->prepare("SELECT * FROM archivos WHERE id=?");
    $stmt->execute([$id]);
    $archivo = $stmt->fetch();

    if (!$archivo) exit(json_encode(['error'=>'No existe el archivo']));

  
    if ($_SESSION['usuario_id'] != $archivo['id_usuario'] && !$_SESSION['es_admin']) {
        exit(json_encode(['error'=>'No autorizado']));
    }


    $pdo->prepare("DELETE FROM archivos WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true, 'msg'=>'Archivo eliminado del gestor, pero permanece en la carpeta física']);
    exit;
}


$id_usuario = $_SESSION['es_admin'] && isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : $_SESSION['usuario_id'];
$id_carpeta = intval($_GET['id_carpeta']);

$stmt = $pdo->prepare("SELECT * FROM archivos WHERE id_usuario=? AND id_carpeta=?");
$stmt->execute([$id_usuario, $id_carpeta]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
