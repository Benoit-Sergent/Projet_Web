<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$_SESSION['utilisateur_id']]);
$messages_non_lus = $stmt_unread->fetchColumn();

if (isset($_GET['action']) && isset($_GET['id'])) {
    $val = ($_GET['action'] === 'justifier') ? 1 : 0;
    $db->prepare("UPDATE presences SET justifie = ? WHERE id = ?")->execute([$val, intval($_GET['id'])]);
    header("Location: gestion_absences.php"); exit();
}

$absences = $db->query("
    SELECT p.*, c.titre as cours_titre, u.nom, u.prenom, g.nom as nom_groupe
    FROM presences p
    JOIN cours c ON p.cours_id = c.id
    JOIN utilisateurs u ON p.etudiant_id = u.id
    LEFT JOIN groupes g ON u.groupe_id = g.id
    WHERE p.statut IN ('absent', 'retard')
    ORDER BY p.justifie ASC, p.date_cours DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Scolarité - SmartCampus</title><link rel="stylesheet" href="style.css"></head>
<body>
    <nav class="top-nav">
        <a href="dashboard_administrateur.php">Membres & Classes</a>
        <a href="gestion_cours.php">Programme</a>
        <a href="gestion_absences.php" class="active">Scolarité (Absences)</a>
        <a href="messagerie.php">Messagerie 💬<?php if ($messages_non_lus > 0): ?><span class="notification-badge"><?= $messages_non_lus ?></span><?php endif; ?></a>
        <a href="profil.php">Profil</a>
		<a href="rapports_admin.php">📊 Rapports</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container" style="margin-top:30px;">
        <h1>Suivi Scolarité & Absences</h1>
<div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Tous les signalements</h2>
                <a href="export_absences.php" class="btn-action" style="background-color: var(--success); text-decoration: none;">
                    📥 Exporter en CSV (Excel)
                </a>
            </div>
            
            <table>
                <tr><th>Date</th><th>Étudiant</th><th>Classe</th><th>Cours</th><th>Statut</th><th>Arbitrage Administratif</th></tr>
                <?php foreach($absences as $a): ?>
                    <tr style="<?= $a['justifie'] === null ? 'background:#fffbeb;' : '' ?>">
                        <td><?= date('d/m/Y', strtotime($a['date_cours'])) ?></td>
                        <td><strong><?= htmlspecialchars($a['nom'].' '.$a['prenom']) ?></strong></td>
                        <td><?= htmlspecialchars($a['nom_groupe']) ?></td>
                        <td><?= htmlspecialchars($a['cours_titre']) ?></td>
                        <td><span style="color:<?= $a['statut']=='absent'?'var(--danger)':'#d97706'?>;font-weight:600;"><?= ucfirst($a['statut']) ?></span></td>
                        <td>
                            <?php if($a['justifie'] === 1): ?><span class="badge badge-success">Justifié</span>
                            <?php elseif($a['justifie'] === 0): ?><span class="badge badge-danger">Non Justifié</span>
                            <?php else: ?>
                                <a href="gestion_absences.php?action=justifier&id=<?= $a['id'] ?>" class="badge badge-success" style="text-decoration:none;">Accepter</a>
                                <a href="gestion_absences.php?action=refuser&id=<?= $a['id'] ?>" class="badge badge-danger" style="text-decoration:none;margin-left:5px;">Refuser</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>