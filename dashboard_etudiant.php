<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$stmt_user = $db->prepare("SELECT prenom, nom, email FROM utilisateurs WHERE id = ?");
$stmt_user->execute([$_SESSION['utilisateur_id']]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

$liste_cours = $db->query("SELECT * FROM cours ORDER BY categorie, titre")->fetchAll(PDO::FETCH_ASSOC);

$stmt_notes = $db->prepare("SELECT cours.titre, notes.valeur_note, notes.commentaire FROM notes JOIN cours ON notes.cours_id = cours.id WHERE notes.etudiant_id = ?");
$stmt_notes->execute([$_SESSION['utilisateur_id']]);
$mes_notes = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);

$moyenne_generale = 0; $nombre_notes = count($mes_notes);
if ($nombre_notes > 0) {
    $moyenne_generale = round(array_sum(array_column($mes_notes, 'valeur_note')) / $nombre_notes, 2);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Espace - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo HEJ">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong>
                <span>Étudiant</span>
            </div>
            <div class="avatar-small"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_etudiant.php" class="active">Tableau de bord</a>
        <a href="parametres.php">Paramètres</a>
        <a href="deconnexion.php">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin:0; color:var(--primary);">Mon Parcours Académique</h1>
                <p style="margin:5px 0 0 0; color:var(--text-muted);">Suivez vos évaluations en temps réel.</p>
            </div>
            <div class="card" style="padding: 15px 35px; text-align: center;">
                <span style="font-size:12px; color:var(--text-muted); text-transform:uppercase;">Moyenne Générale</span>
                <strong style="display:block; font-size:26px; color:var(--primary); margin-top:2px;"><?= $nombre_notes > 0 ? number_format($moyenne_generale, 2, ',', ' ') : '-' ?> <span style="font-size:14px;color:var(--text-muted)">/20</span></strong>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header"><h2>Carnet de Notes</h2></div>
                <?php if (empty($mes_notes)): ?>
                    <p style="color: var(--text-muted); font-style: italic;">Aucune note enregistrée pour le moment.</p>
                <?php else: ?>
                    <table>
                        <tr><th>Matière</th><th>Note</th><th>Appréciation</th></tr>
                        <?php foreach ($mes_notes as $note): 
                            $badge = ($note['valeur_note'] >= 10) ? 'badge-success' : 'badge-danger';
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($note['titre']) ?></strong></td>
                                <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($note['valeur_note']) ?> / 20</span></td>
                                <td style="color: var(--text-muted); font-style: italic;"><?= !empty($note['commentaire']) ? htmlspecialchars($note['commentaire']) : 'Aucune mention' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header"><h2>Matières Enseignées</h2></div>
                <table style="width:100%">
                    <?php foreach ($liste_cours as $cours): ?>
                        <tr><td>📘 <strong><?= htmlspecialchars($cours['titre']) ?></strong><br><span style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($cours['categorie']) ?></span></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
</body>
</html>