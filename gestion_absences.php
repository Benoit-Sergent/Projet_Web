<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$message_succes = "";

// 1. ACTION : Arbitrer une absence (Justifier ou Refuser)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id_presence = intval($_GET['id']);
    $valeur_justifie = ($_GET['action'] === 'justifier') ? 1 : 0;
    
    $stmt_update = $db->prepare("UPDATE presences SET justifie = ? WHERE id = ?");
    $stmt_update->execute([$valeur_justifie, $id_presence]);
    
    header("Location: gestion_absences.php?succes=1");
    exit();
}

// Infos Admin
$stmt_admin = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_admin->execute([$_SESSION['utilisateur_id']]);
$admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1));

// Récupération de tous les signalements d'absence et de retard
$absences = $db->query("
    SELECT p.id, p.date_cours, p.statut, p.justifie, c.titre as cours, u.prenom, u.nom, g.nom as nom_groupe 
    FROM presences p
    JOIN cours c ON p.cours_id = c.id
    JOIN utilisateurs u ON p.etudiant_id = u.id
    LEFT JOIN groupes g ON u.groupe_id = g.id
    WHERE p.statut IN ('absent', 'retard')
    ORDER BY p.justifie ASC, p.date_cours DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Compter les dossiers en attente
$dossiers_attente = 0;
foreach ($absences as $a) { if ($a['justifie'] === null) $dossiers_attente++; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Scolarité - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></strong>
                <span>Administrateur</span>
            </div>
            <div class="avatar-small" style="background:#2b2b2b;"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_administrateur.php">Membres & Classes</a>
        <a href="gestion_cours.php">Programme</a>
        <a href="gestion_absences.php" class="active">Scolarité (Absences)</a>
        <a href="parametres.php">Paramètres</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 style="margin:0; color:var(--primary);">Scolarité & Absences</h1>
                <p style="margin:5px 0 0 0; color:var(--text-muted);">Validez ou refusez les justificatifs soumis par les élèves.</p>
            </div>
            <div class="card" style="padding: 10px 20px; text-align: center;">
                <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Dossiers en attente</span>
                <strong style="display: block; font-size: 22px; color: var(--danger);"><?= $dossiers_attente ?></strong>
            </div>
        </div>

        <?php if (isset($_GET['succes'])): ?><div class="alert alert-success">✅ Le dossier a été mis à jour avec succès.</div><?php endif; ?>

        <div class="card">
            <div class="card-header"><h2>Tous les signalements</h2></div>
            <?php if (empty($absences)): ?>
                <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 20px 0;">Aucune absence n'a été signalée par les professeurs.</p>
            <?php else: ?>
                <table>
                    <tr><th>Date</th><th>Élève</th><th>Classe</th><th>Matière</th><th>Statut</th><th>Décision Admin</th></tr>
                    <?php foreach ($absences as $a): ?>
                        <tr style="<?= $a['justifie'] === null ? 'background-color: #fffbeb;' : '' ?>">
                            <td><?= date("d/m/Y", strtotime($a['date_cours'])) ?></td>
                            <td><strong><?= htmlspecialchars($a['nom'] . ' ' . $a['prenom']) ?></strong></td>
                            <td style="font-size:12px;"><?= htmlspecialchars($a['nom_groupe'] ?? '-') ?></td>
                            <td style="font-size:12px;"><?= htmlspecialchars($a['cours']) ?></td>
                            <td>
                                <?= $a['statut'] === 'absent' ? '<span style="color:var(--danger);font-weight:600;">Absence</span>' : '<span style="color:#d97706;font-weight:600;">Retard</span>' ?>
                            </td>
                            <td>
                                <?php if ($a['justifie'] === 1): ?>
                                    <span class="badge badge-success">Justifié</span>
                                <?php elseif ($a['justifie'] === 0): ?>
                                    <span class="badge badge-danger">Refusé</span>
                                <?php else: ?>
                                    <a href="gestion_absences.php?action=justifier&id=<?= $a['id'] ?>" class="badge badge-success" style="text-decoration:none;">Accepter</a>
                                    <a href="gestion_absences.php?action=refuser&id=<?= $a['id'] ?>" class="badge badge-danger" style="text-decoration:none; margin-left:5px;">Refuser</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>