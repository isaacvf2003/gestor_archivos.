<?php
require_once __DIR__.'/session_boot.php';
require_once __DIR__.'/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
  if (empty($_SESSION['usuario_id']) || empty($_SESSION['es_admin'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = $pdo->query("SELECT id, nombre, usuario, es_admin FROM usuarios");
    echo json_encode($res->fetchAll(PDO::FETCH_ASSOC));
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    if (!$d) throw new Exception('JSON inválido');

    $nombre   = trim($d['nombre']  ?? '');
    $usuario  = trim($d['usuario'] ?? '');
    $clave    = (string)($d['clave'] ?? '');
    $es_admin = !empty($d['es_admin']) ? 1 : 0;

    if ($nombre === '' || $usuario === '' || $clave === '') {
      throw new Exception('Faltan nombre/usuario/clave');
    }


    $stmt = $pdo->prepare(
      "INSERT INTO usuarios (nombre, usuario, clave_hash, es_admin) VALUES (?,?,?,?)"
    );
    $stmt->execute([$nombre, $usuario, password_hash($clave, PASSWORD_DEFAULT), $es_admin]);

    $ruta_base = __DIR__ . "/../archivos/";
    $carpeta_usuario = $ruta_base . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nombre);
    if (!is_dir($carpeta_usuario) && !mkdir($carpeta_usuario, 0777, true)) {
      throw new Exception('No se pudo crear la carpeta del usuario');
    }

    echo json_encode(['ok'=>true, 'msg'=>'Usuario creado']);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) throw new Exception('ID inválido');

    $pdo->prepare("DELETE FROM archivos WHERE id_usuario=?")->execute([$id]);
    $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);

    echo json_encode(['ok'=>true, 'msg'=>'Usuario eliminado del gestor (carpetas físicas intactas)']);
    exit;
  }

  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'Método no permitido']);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
