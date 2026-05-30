<?php
/**
 * modifier_profil.php
 * Endpoint AJAX — met à jour les informations d'un utilisateur.
 * Accessible uniquement par les administrateurs.
 * Reçoit un body JSON, retourne un JSON {success: true} ou {error: "..."}.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// Vérification de session
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé.']);
    exit();
}

require_once 'db.php';

// Lecture du body JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !is_array($data)) {
    echo json_encode(['error' => 'Données invalides.']);
    exit();
}

// ── Récupération et validation des champs ─────────────────────────────────────
$id       = intval($data['id']       ?? 0);
$prenom   = trim($data['prenom']     ?? '');
$nom      = trim($data['nom']        ?? '');
$email    = trim($data['email']      ?? '');
$role     = trim($data['role']       ?? '');
$groupe_id = isset($data['groupe_id']) && $data['groupe_id'] !== '' && $data['groupe_id'] !== null
             ? intval($data['groupe_id'])
             : null;

// Vérifications de base
if ($id <= 0) {
    echo json_encode(['error' => 'Identifiant invalide.']);
    exit();
}
if (empty($prenom) || empty($nom) || empty($email)) {
    echo json_encode(['error' => 'Les champs prénom, nom et email sont obligatoires.']);
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Adresse email invalide.']);
    exit();
}
$roles_autorises = ['etudiant', 'professeur', 'administrateur'];
if (!in_array($role, $roles_autorises, true)) {
    echo json_encode(['error' => 'Rôle non reconnu.']);
    exit();
}

// ── Protection : un admin ne peut pas se rétrograder lui-même ─────────────────
if ($id === (int) $_SESSION['utilisateur_id'] && $role !== 'administrateur') {
    echo json_encode(['error' => 'Vous ne pouvez pas modifier votre propre rôle.']);
    exit();
}

// ── Mise à jour en base ───────────────────────────────────────────────────────
try {
    // Vérifier l'unicité de l'email (en excluant l'utilisateur courant)
    $check = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);
    if ((int) $check->fetchColumn() > 0) {
        echo json_encode(['error' => 'Cet email est déjà utilisé par un autre membre.']);
        exit();
    }

    $stmt = $db->prepare("
        UPDATE utilisateurs
        SET prenom    = ?,
            nom       = ?,
            email     = ?,
            role      = ?,
            groupe_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$prenom, $nom, $email, $role, $groupe_id, $id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    // Erreur générique (ne pas exposer les détails en production)
    echo json_encode(['error' => 'Erreur lors de la mise à jour. Veuillez réessayer.']);
}