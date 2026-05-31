<?php
/**
 * get_profil.php
 * Endpoint AJAX — retourne les informations d'un utilisateur en JSON.
 * Accessible uniquement par les administrateurs.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// Vérification de session
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['administrateur', 'professeur'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé.']);
    exit();
}

require_once 'db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['error' => 'Identifiant invalide.']);
    exit();
}

// ── Informations de base (sans le mot de passe) ──────────────────────────────
$stmt = $db->prepare("
    SELECT u.id, u.nom, u.prenom, u.email, u.role, u.groupe_id,
           g.nom AS nom_groupe
    FROM utilisateurs u
    LEFT JOIN groupes g ON u.groupe_id = g.id
    WHERE u.id = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'Utilisateur introuvable.']);
    exit();
}

// ── Données académiques (étudiants uniquement) ────────────────────────────────
$moyenne = null;
$notes_par_cours = [];
$parcours = [];

if ($user['role'] === 'etudiant') {
    // Moyenne générale
    try {
        $stmt = $db->prepare("SELECT ROUND(AVG(valeur_note), 2) FROM notes WHERE etudiant_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetchColumn();
        $moyenne = ($result !== false && $result !== null) ? $result : null;

        // Détail par cours
        $stmt = $db->prepare("
            SELECT c.titre, ROUND(AVG(n.valeur_note), 2) AS moyenne
            FROM notes n
            JOIN cours c ON c.id = n.cours_id
            WHERE n.etudiant_id = ?
            GROUP BY c.id
            ORDER BY c.titre
        ");
        $stmt->execute([$id]);
        $notes_par_cours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $moyenne = null;
        $notes_par_cours = [];
    }

    // ── Parcours académique depuis le nom du groupe ───────────────────────────
    // Format attendu : "G1-A2", "G3-A1", etc. — la partie "A{n}" indique l'année.
    $labels_annees = [
        1 => 'Première année',
        2 => 'Deuxième année',
        3 => 'Troisième année',
    ];

    if (!empty($user['nom_groupe']) && preg_match('/A(\d+)/i', $user['nom_groupe'], $matches)) {
        $annee_actuelle = (int) $matches[1];

        for ($i = 1; $i <= $annee_actuelle; $i++) {
            $parcours[] = [
                'annee'    => $i,
                'label'    => $labels_annees[$i] ?? "Année $i",
                'actuelle' => ($i === $annee_actuelle),
            ];
        }
    }
}
// ── Cours enseignés (professeurs uniquement) ──────────────────────────────
$cours_enseignes = [];
if ($user['role'] === 'professeur') {
    try {
        $stmt = $db->prepare("
            SELECT c.id, c.titre, c.categorie, c.jour,
                   c.heure_debut, c.heure_fin, c.salle,
                   g.nom AS nom_groupe
            FROM cours c
            LEFT JOIN groupes g ON c.groupe_id = g.id
            WHERE c.professeur_id = ?
            ORDER BY c.jour, c.heure_debut
        ");
        $stmt->execute([$id]);
        $cours_enseignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cours_enseignes = [];
    }
}

// ── Liste de tous les groupes (pour le select d'édition) ─────────────────────
$groupes = $db->query("SELECT id, nom FROM groupes ORDER BY nom")
              ->fetchAll(PDO::FETCH_ASSOC);

// ── Réponse JSON ──────────────────────────────────────────────────────────────
echo json_encode([
    'user'            => $user,
    'moyenne'         => $moyenne,
    'notes_par_cours' => $notes_par_cours,
    'parcours'        => $parcours,
    'groupes'         => $groupes,
    'cours_enseignes' => $cours_enseignes,  // ← ajouter cette ligne
]);