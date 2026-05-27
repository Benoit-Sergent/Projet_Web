<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$etud_id = $_SESSION['utilisateur_id'];

$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$etud_id]);
$messages_non_lus = $stmt_unread->fetchColumn();

// Informations de l'élève et sa classe
$user = $db->query("SELECT u.*, g.nom as nom_groupe FROM utilisateurs u LEFT JOIN groupes g ON u.groupe_id = g.id WHERE u.id = $etud_id")->fetch();
$initiales = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

// Liste des cours
$liste_cours = [];
if ($user['groupe_id']) {
    $stmt = $db->prepare("SELECT * FROM cours WHERE groupe_id = ? ORDER BY titre");
    $stmt->execute([$user['groupe_id']]);
    $liste_cours = $stmt->fetchAll();
}

// Moyenne générale
$stmt_notes = $db->prepare("SELECT valeur_note FROM notes WHERE etudiant_id = ?");
$stmt_notes->execute([$etud_id]);
$notes = $stmt_notes->fetchAll();
$moyenne = 0;
if (count($notes) > 0) {
    $moyenne = round(array_sum(array_column($notes, 'valeur_note')) / count($notes), 2);
}

// Absences et retards
$stmt_abs = $db->prepare("SELECT COUNT(*) FROM presences WHERE etudiant_id = ? AND statut = 'absent'");
$stmt_abs->execute([$etud_id]);
$nb_abs = $stmt_abs->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Étudiant - SmartCampus</title><link rel="stylesheet" href="style.css"></head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;"><strong><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></strong><span><?= $user['nom_groupe'] ? htmlspecialchars($user['nom_groupe']) : 'Classe non assignée' ?></span></div>
            <div class="avatar-small"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_etudiant.php" class="active">Dashboard</a>
        <a href="mes_cours.php">Mes Cours</a>
        <a href="mes_notes.php">Notes</a>
        <a href="presences.php">Présences</a>
        <a href="planning.php">Emploi du temps</a>
        <a href="messagerie.php">Messagerie 💬<?php if ($messages_non_lus > 0): ?><span class="notification-badge"><?= $messages_non_lus ?></span><?php endif; ?></a>
        <a href="profil.php">Profil</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        <h1>Mon Espace Étudiant</h1>
        <div class="dashboard-grid">
            <div class="card" style="text-align:center;padding:30px;">
                <h2 style="color:var(--text-muted);font-size:14px;text-transform:uppercase;">Moyenne Générale</h2>
                <div style="font-size:48px;font-weight:bold;color:var(--primary);margin:15px 0;"><?= $moyenne ?> <span style="font-size:18px;color:var(--text-muted);">/ 20</span></div>
            </div>

            <div class="card" style="text-align:center;padding:30px;">
                <h2 style="color:var(--text-muted);font-size:14px;text-transform:uppercase;">Absences Signalées</h2>
                <div style="font-size:48px;font-weight:bold;color:var(--danger);margin:15px 0;"><?= $nb_abs ?></div>
            </div>
        </div>

        <div class="card" style="margin-top:25px;">
            <div class="card-header"><h2>Mes Matières Actuelles</h2></div>
            <table>
                <?php foreach($liste_cours as $c): ?>
                    <tr><td>📘 <strong><?= htmlspecialchars($c['titre']) ?></strong> (<?= htmlspecialchars($c['categorie']) ?>)</td></tr>
                <?php endforeach; if(empty($liste_cours)) echo "<tr><td>Aucun cours assigné.</td></tr>"; ?>
            </table>
        </div>
    </div>
</body>
</html>