<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit();
}
require_once 'db.php';

header('Content-Type: application/json');

$email = trim($_GET['email'] ?? '');
if (empty($email)) {
    echo json_encode(['existe' => false]);
    exit();
}

$stmt = $db->prepare("SELECT id, prenom, nom, role FROM utilisateurs WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo json_encode([
        'existe' => true,
        'nom'    => htmlspecialchars($user['prenom'] . ' ' . $user['nom']),
        'role'   => $user['role']
    ]);
} else {
    echo json_encode(['existe' => false]);
}
?>