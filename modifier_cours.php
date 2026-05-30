<?php
/**
 * modifier_cours.php
 * Endpoint AJAX — met à jour les informations d'un cours.
 * Accessible uniquement par les administrateurs.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé.']);
    exit();
}

require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !is_array($data)) {
    echo json_encode(['error' => 'Données invalides.']);
    exit();
}

// ── Récupération et validation ────────────────────────────────────────────────
$id          = intval($data['id']           ?? 0);
$titre       = trim($data['titre']          ?? '');
$categorie   = trim($data['categorie']      ?? '');
$prof_id     = intval($data['professeur_id'] ?? 0);
$groupe_id   = intval($data['groupe_id']    ?? 0);
$jour        = trim($data['jour']           ?? '');
$heure_debut = trim($data['heure_debut']    ?? '');
$heure_fin   = trim($data['heure_fin']      ?? '');
$salle       = trim($data['salle']          ?? '');

if ($id <= 0)                    { echo json_encode(['error' => 'Identifiant invalide.']);              exit(); }
if (empty($titre))               { echo json_encode(['error' => 'Le titre est obligatoire.']);          exit(); }
if ($prof_id <= 0)               { echo json_encode(['error' => 'Professeur invalide.']);               exit(); }
if ($groupe_id <= 0)             { echo json_encode(['error' => 'Classe invalide.']);                   exit(); }
if (empty($heure_debut) || empty($heure_fin)) { echo json_encode(['error' => 'Horaires invalides.']); exit(); }

$categories_autorisees = ['Sciences', 'Informatique', 'Langues', 'Management'];
if (!in_array($categorie, $categories_autorisees, true)) {
    echo json_encode(['error' => 'Catégorie non reconnue.']);
    exit();
}

$jours_autorises = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
if (!in_array($jour, $jours_autorises, true)) {
    echo json_encode(['error' => 'Jour invalide.']);
    exit();
}

// ── Mise à jour ───────────────────────────────────────────────────────────────
try {
    // Vérifie que le cours existe
    $check = $db->prepare("SELECT COUNT(*) FROM cours WHERE id = ?");
    $check->execute([$id]);
    if ((int) $check->fetchColumn() === 0) {
        echo json_encode(['error' => 'Cours introuvable.']);
        exit();
    }

    $stmt = $db->prepare("
        UPDATE cours
        SET titre        = ?,
            categorie    = ?,
            professeur_id = ?,
            groupe_id    = ?,
            jour         = ?,
            heure_debut  = ?,
            heure_fin    = ?,
            salle        = ?
        WHERE id = ?
    ");
    $stmt->execute([$titre, $categorie, $prof_id, $groupe_id, $jour, $heure_debut, $heure_fin, $salle, $id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur lors de la mise à jour. Veuillez réessayer.']);
}
?>