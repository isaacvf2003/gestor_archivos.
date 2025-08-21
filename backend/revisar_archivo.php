<?php

require_once __DIR__.'/session_boot.php';
include 'db.php';


if (!isset($_SESSION['usuario_id']) || !$_SESSION['es_admin']) exit('No autorizado');

$d = json_decode(file_get_contents('php://input'),true);
$stmt = $pdo->prepare("UPDATE archivos SET estado_revision=?, comentario_admin=? WHERE id=?");
$stmt->execute([$d['estado'], $d['comentario'], intval($d['id_archivo'])]);
echo json_encode(['ok'=>true]);
?>
