<?php

require_once __DIR__.'/session_boot.php';
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}

$ruta_base = __DIR__ . "/../archivos/";


function safe_name($s){ return preg_replace('/[^a-zA-Z0-9_\-]/','_', $s); }


function getCarpeta(PDO $pdo, int $id) {
  $st = $pdo->prepare("SELECT id,nombre,id_padre FROM carpetas WHERE id=?");
  $st->execute([$id]);
  return $st->fetch(PDO::FETCH_ASSOC);
}


function rutaGlobalPadre(PDO $pdo, ?int $id_padre): string {
  $p = [];
  while ($id_padre !== null) {
    $st = $pdo->prepare("SELECT nombre, id_padre FROM carpetas WHERE id=?");
    $st->execute([$id_padre]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) break;
    array_unshift($p, safe_name($row['nombre']));
    $id_padre = $row['id_padre'];
  }
  return implode('/', $p);
}


function rutaUsuarioPadre(PDO $pdo, ?int $id_padre, int $id_usuario): string {
  $p = [];
  while ($id_padre !== null) {
    $st = $pdo->prepare("
      SELECT c.id, c.nombre, c.id_padre,
             (SELECT nombre_alias FROM carpetas_alias
                WHERE user_id=? AND carpeta_id=c.id) AS alias_nombre
      FROM carpetas c WHERE c.id=?");
    $st->execute([$id_usuario, $id_padre]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) break;
    $seg = safe_name($row['alias_nombre'] ?: $row['nombre']);
    array_unshift($p, $seg);
    $id_padre = $row['id_padre'];
  }
  return implode('/', $p); 
}


function nombreEfectivoCarpeta(PDO $pdo, int $id_carpeta, int $id_usuario): array {
  $st = $pdo->prepare("
    SELECT c.nombre AS global_nombre,
           (SELECT nombre_alias FROM carpetas_alias
              WHERE user_id=? AND carpeta_id=c.id) AS alias_nombre
    FROM carpetas c WHERE c.id=?");
  $st->execute([$id_usuario, $id_carpeta]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) $row = ['global_nombre'=>null,'alias_nombre'=>null];
  return $row;
}

function idsSubarbol(PDO $pdo, int $id): array {
  $ids = [];
  $stack = [$id];
  while ($stack) {
    $cur = array_pop($stack);
    $ids[] = $cur;
    $st = $pdo->prepare("SELECT id FROM carpetas WHERE id_padre=?");
    $st->execute([$cur]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN, 0) as $hijo) {
      $stack[] = (int)$hijo;
    }
  }
  return $ids;
}


if ($_SERVER['REQUEST_METHOD']==='GET') {
  $id_padre = isset($_GET['id_padre']) ? intval($_GET['id_padre']) : null;

 
  $id_usuario_ctx = isset($_GET['id_usuario'])
    ? intval($_GET['id_usuario'])
    : intval($_SESSION['usuario_id']);

  $sqlBase = "
    SELECT c.id,
           COALESCE(a.nombre_alias, c.nombre) AS nombre,
           c.id_padre
    FROM carpetas c
    LEFT JOIN carpetas_alias a
           ON a.carpeta_id = c.id
          AND a.user_id    = ?
  ";

  if ($id_padre === null) {
    $sql = $sqlBase . " WHERE c.id_padre IS NULL";
    $st  = $pdo->prepare($sql);
    $st->execute([$id_usuario_ctx]);
  } else {
    $sql = $sqlBase . " WHERE c.id_padre = ?";
    $st  = $pdo->prepare($sql);
    $st->execute([$id_usuario_ctx, $id_padre]);
  }

  echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
  exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (empty($_SESSION['es_admin'])) { http_response_code(401); echo 'No autorizado'; exit; }

  $d = json_decode(file_get_contents('php://input'), true);
  $nombre   = trim($d['nombre'] ?? '');
  $id_padre = isset($d['id_padre']) ? intval($d['id_padre']) : null;
  if ($nombre === '') { echo json_encode(['ok'=>false,'msg'=>'Nombre vacío']); exit; }


  $st = $pdo->prepare("INSERT INTO carpetas (nombre,id_padre) VALUES (?,?)");
  $st->execute([$nombre, $id_padre]);

  $ruta_padre_glob = rutaGlobalPadre($pdo, $id_padre);
  $seg = safe_name($nombre);
  $dest_global = rtrim($ruta_base . ($ruta_padre_glob ? $ruta_padre_glob.'/' : '') . $seg, '/');
  if (!is_dir($dest_global)) mkdir($dest_global, 0777, true);

  $usuarios = $pdo->query("SELECT nombre FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($usuarios as $u) {
    $root = $ruta_base . safe_name($u['nombre']);
    if (!is_dir($root)) continue;
    $dest_usr = rtrim($root . '/' . ($ruta_padre_glob ? $ruta_padre_glob.'/' : '') . $seg, '/');
    if (!is_dir($dest_usr)) mkdir($dest_usr, 0777, true);
  }

  echo json_encode(['ok'=>true,'msg'=>'Carpeta creada (BD + disco plantilla + réplica usuarios).']);
  exit;
}


if ($_SERVER['REQUEST_METHOD']==='DELETE') {
  if (empty($_SESSION['es_admin'])) { http_response_code(401); echo 'No autorizado'; exit; }
  $id = intval($_GET['id'] ?? 0);
  if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

  $ids = idsSubarbol($pdo, $id);
  $ph  = implode(',', array_fill(0, count($ids), '?'));

  $pdo->prepare("DELETE FROM archivos WHERE id_carpeta IN ($ph)")->execute($ids);

  $pdo->prepare("DELETE FROM carpetas_alias WHERE carpeta_id IN ($ph)")->execute($ids);

  $pdo->prepare("DELETE FROM carpetas WHERE id IN ($ph)")->execute($ids);

  echo json_encode(['ok'=>true,'msg'=>'Carpeta (y subcarpetas) eliminadas del gestor. Archivos físicos conservados.']);
  exit;
}


if ($_SERVER['REQUEST_METHOD']==='PATCH') {
  if (empty($_SESSION['es_admin'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
    exit;
  }

  $d      = json_decode(file_get_contents('php://input'), true);
  $id     = (int)($d['id'] ?? 0);
  $nuevo  = trim($d['nombre'] ?? '');
  $id_usr = isset($d['id_usuario']) ? (int)$d['id_usuario'] : 0;

  if (!$id || $nuevo === '') { echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit; }

  $carp = getCarpeta($pdo, $id);
  if (!$carp) { echo json_encode(['ok'=>false,'msg'=>'Carpeta no existe']); exit; }

  if ($id_usr) {
 
    $nombres = nombreEfectivoCarpeta($pdo, $id, $id_usr);
    $old_seg = safe_name($nombres['alias_nombre'] ?: $nombres['global_nombre']);
    $new_seg = safe_name($nuevo);


    $ruta_padre_user = rutaUsuarioPadre($pdo, $carp['id_padre'], $id_usr);

    $usuario_nombre = $pdo->prepare("SELECT nombre FROM usuarios WHERE id=?");
    $usuario_nombre->execute([$id_usr]);
    $usuario_nombre = $usuario_nombre->fetchColumn();
    $root = rtrim($ruta_base . safe_name($usuario_nombre), '/');

    $parent = rtrim($root . ($ruta_padre_user ? '/'.$ruta_padre_user : ''), '/');
    $from   = $parent . '/' . $old_seg;
    $to     = $parent . '/' . $new_seg;

    $pdo->prepare("
      INSERT INTO carpetas_alias (carpeta_id,user_id,nombre_alias)
      VALUES (?,?,?)
      ON DUPLICATE KEY UPDATE nombre_alias=VALUES(nombre_alias)
    ")->execute([$id,$id_usr,$nuevo]);

    if (is_dir($from) && !is_dir($to)) {
      @rename($from, $to);
      $sql = "UPDATE archivos SET ruta = REPLACE(ruta, ?, ?) WHERE id_usuario=? AND ruta LIKE ?";
      $pdo->prepare($sql)->execute([$from, $to, $id_usr, $from.'%']);
    }

    echo json_encode(['ok'=>true,'msg'=>'Renombrado SOLO para usuario']);
    exit;
  }

  $old_global = $carp['nombre'];
  $new_global = $nuevo;

 
  $st = $pdo->prepare("
    SELECT u.id AS id_usuario, u.nombre AS usuario_nombre,
           (SELECT nombre_alias FROM carpetas_alias
              WHERE user_id=u.id AND carpeta_id=?) AS alias_actual
    FROM usuarios u
  ");
  $st->execute([$id]);
  $usuarios = $st->fetchAll(PDO::FETCH_ASSOC);

  $pdo->prepare("UPDATE carpetas SET nombre=? WHERE id=?")->execute([$new_global, $id]);
  $pdo->prepare("DELETE FROM carpetas_alias WHERE carpeta_id=?")->execute([$id]);


  $ruta_padre_glob = rutaGlobalPadre($pdo, $carp['id_padre']);
  $parent_glob = rtrim($ruta_base . ($ruta_padre_glob ? $ruta_padre_glob.'/' : ''), '/');
  $fromG = $parent_glob . '/' . safe_name($old_global);
  $toG   = $parent_glob . '/' . safe_name($new_global);
  if (is_dir($fromG) && !is_dir($toG)) {
    @rename($fromG, $toG);
    $pdo->prepare("UPDATE archivos SET ruta = REPLACE(ruta, ?, ?) WHERE ruta LIKE ?")
        ->execute([$fromG, $toG, $fromG.'%']);
  }


  foreach ($usuarios as $u) {
    $root = rtrim($ruta_base . safe_name($u['usuario_nombre']), '/');
    $ruta_padre_user = rutaUsuarioPadre($pdo, $carp['id_padre'], intval($u['id_usuario']));
    $parent = rtrim($root . ($ruta_padre_user ? '/'.$ruta_padre_user : ''), '/');

    $old_seg = safe_name($u['alias_actual'] ?: $old_global);
    $new_seg = safe_name($new_global);

    $from = $parent . '/' . $old_seg;
    $to   = $parent . '/' . $new_seg;

    if (is_dir($from) && !is_dir($to)) {
      @rename($from, $to);
      $sql = "UPDATE archivos SET ruta = REPLACE(ruta, ?, ?) WHERE id_usuario=? AND ruta LIKE ?";
      $pdo->prepare($sql)->execute([$from, $to, $u['id_usuario'], $from.'%']);
    }
  }

  echo json_encode(['ok'=>true,'msg'=>'Renombrado global']);
  exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'msg'=>'Método no permitido']);
