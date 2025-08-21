<?php
require_once __DIR__.'/session_boot.php';
require_once __DIR__.'/db.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];


if (!empty($data['check'])) {
    if (isset($_SESSION['usuario_id'])) {
        echo json_encode([
            'ok'         => true,
            'admin'      => !empty($_SESSION['es_admin']),
            'nombre'     => $_SESSION['nombre'] ?? '',
            'usuario_id' => $_SESSION['usuario_id'],
        ]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}


$usuario = $data['usuario'] ?? '';
$clave   = $data['clave']   ?? '';

if ($usuario === '' || $clave === '') {
    echo json_encode(['ok' => false, 'msg' => 'faltan datos']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario=?");
$stmt->execute([$usuario]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($clave, $user['clave_hash'])) {
    $_SESSION['usuario_id']   = (int)$user['id'];
    $_SESSION['es_admin']     = ((int)$user['es_admin'] === 1);
    $_SESSION['nombre']       = $user['nombre'] ?? $usuario;
    $_SESSION['last_activity']= time();

    echo json_encode([
        'ok'         => true,
        'admin'      => $_SESSION['es_admin'],
        'nombre'     => $_SESSION['nombre'],
        'usuario_id' => $_SESSION['usuario_id'],
    ]);
} else {
    echo json_encode(['ok' => false]);
}
