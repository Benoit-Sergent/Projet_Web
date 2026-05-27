<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$etudiant_id = $_SESSION['utilisateur_id'];

// Infos de l'étudiant
$stmt_user = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_user->execute([$etudiant_id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

// Récupération de l'historique des présences (uniquement les absences et retards pour ne pas polluer l'affichage)
$stmt_presences = $db->prepare("
    SELECT p.date_cours, p.statut, p.justifie, c.titre 
    FROM presences p 
    JOIN cours c ON p.cours_id = c.id 
    WHERE p.etudiant_id = ? AND p.statut != 'present'
    ORDER BY p.date_cours DESC
");
$stmt_presences->execute([$etudiant_id]);
$historique = $stmt_presences->fetchAll(PDO::FETCH_ASSOC);

// Calcul des statistiques
$total_absences = 0;
$total_retards = 0;
foreach ($historique as $h) {
    if ($h['statut'] === 'absent') $total_absences++;
    if ($h['statut'] === 'retard') $total_retards++;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Présences - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo SmartCampus" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong>
                <span>Étudiant</span>
            </div>
            <div class="avatar-small"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_etudiant.php">Dashboard</a>
        <a href="profil.php">Profil</a>
        <a href="mes_cours.php">Mes Cours</a>
        <a href="mes_notes.php">Notes</a>
        <a href="presences.php" class="active">Présences</a>
        <a href="planning.php">Emploi du temps</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 style="margin:0; color:var(--primary);">Registre d'Assiduité</h1>
                <p style="margin:5px 0 0 0; color:var(--text-muted);">Suivez vos signalements d'absence et vos justificatifs.</p>
            </div>
            <div style="display:flex; gap:15px;">
                <div class="card" style="padding: 10px 20px; text-align: center;">
                    <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Absences</span>
                    <strong style="display: block; font-size: 22px; color: var(--danger);"><?= $total_absences ?></strong>
                </div>
                <div class="card" style="padding: 10px 20px; text-align: center;">
                    <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Retards</span>
                    <strong style="display: block; font-size: 22px; color: #d97706;"><?= $total_retards ?></strong>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Historique des signalements</h2></div>
            <?php if (empty($historique)): ?>
                <p style="color: var(--success); font-weight: 600; text-align: center; padding: 20px;">Félicitations ! Vous n'avez aucune absence ni aucun retard à votre actif.</p>
            <?php else: ?>
                <table>
                    <tr><th>Date</th><th>Matière</th><th>Type</th><th>Statut du Justificatif</th></tr>
                    <?php foreach ($historique as $h): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($h['date_cours'])) ?></strong></td>
                            <td><?= htmlspecialchars($h['titre']) ?></td>
                            <td>
                                <?php if ($h['statut'] === 'absent'): ?>
                                    <span style="color:var(--danger); font-weight:600;">Absence</span>
                                <?php else: ?>
                                    <span style="color:#d97706; font-weight:600;">Retard</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($h['justifie'] === 1): ?>
                                    <span class="badge badge-success">Accepté (Justifié)</span>
                                <?php elseif ($h['justifie'] === 0): ?>
                                    <span class="badge badge-danger">Refusé (Non justifié)</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral" style="border-color:#d97706; color:#d97706;">⏳ En attente de traitement</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php include 'footer.php'; ?>
</body>
</html>