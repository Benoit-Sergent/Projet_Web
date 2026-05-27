<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

// Gestion de la justification des absences
if (isset($_GET['action']) && isset($_GET['id'])) {
    $val = ($_GET['action'] === 'justifier') ? 1 : 0;
    $stmt = $db->prepare("UPDATE presences SET justifie = ? WHERE id = ?");
    $stmt->execute([$val, intval($_GET['id'])]);
    header("Location: gestion_absences.php"); 
    exit();
}

// Récupération des absences
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
<head>
    <meta charset="UTF-8">
    <title>Gestion Scolarité - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="color:var(--primary);">Suivi de l'Assiduité</h1>
            <p style="color:var(--text-muted); margin:0;">Gestion et justification des absences et retards des étudiants.</p>
        </div>

        <div class="card">
            <div class="card-header"><h2>Registre des absences</h2></div>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Étudiant</th>
                    <th>Classe</th>
                    <th>Cours</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
                <?php if(empty($absences)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:20px;">Aucune absence signalée.</td></tr>
                <?php else: ?>
                    <?php foreach($absences as $a): ?>
                        <tr style="<?= $a['justifie'] === null ? 'background:#fffbeb;' : '' ?>">
                            <td><?= date('d/m/Y', strtotime($a['date_cours'])) ?></td>
                            <td><strong><?= htmlspecialchars($a['nom'].' '.$a['prenom']) ?></strong></td>
                            <td><?= htmlspecialchars($a['nom_groupe'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['cours_titre']) ?></td>
                            <td>
                                <span style="color:<?= $a['statut']=='absent'?'var(--danger)':'#d97706'?>; font-weight:600;">
                                    <?= ucfirst($a['statut']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if($a['justifie'] === 1): ?>
                                    <span class="badge badge-success">Justifié</span>
                                <?php elseif($a['justifie'] === 0): ?>
                                    <span class="badge badge-danger">Refusé</span>
                                <?php else: ?>
                                    <a href="gestion_absences.php?action=justifier&id=<?= $a['id'] ?>" class="badge badge-success" style="text-decoration:none;">Accepter</a>
                                    <a href="gestion_absences.php?action=refuser&id=<?= $a['id'] ?>" class="badge badge-danger" style="text-decoration:none; margin-left:5px;">Refuser</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>