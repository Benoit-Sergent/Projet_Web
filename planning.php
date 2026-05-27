<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$etud_id = $_SESSION['utilisateur_id'];
$user = $db->query("SELECT groupe_id FROM utilisateurs WHERE id = $etud_id")->fetch();

$jours = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi'];
$planning = [1=>[], 2=>[], 3=>[], 4=>[], 5=>[]];

if ($user['groupe_id']) {
    $stmt = $db->prepare("
        SELECT cc.*, c.titre, prof.nom as prof_nom
        FROM cours_creneaux cc
        JOIN cours c ON cc.cours_id = c.id
        LEFT JOIN utilisateurs prof ON c.professeur_id = prof.id
        WHERE c.groupe_id = ?
        ORDER BY cc.heure_debut
    ");
    $stmt->execute([$user['groupe_id']]);
    foreach($stmt->fetchAll() as $row) {
        $planning[$row['jour_semaine']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Planning - SmartCampus</title><link rel="stylesheet" href="style.css">
    <style>
        .planning-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-top:20px; }
        .day-col { background: white; border: 1px solid var(--border); border-radius: 8px; padding: 15px; min-height: 400px; }
        .day-header { text-align: center; font-weight: bold; border-bottom: 2px solid var(--primary); padding-bottom: 10px; color: var(--primary); }
        .slot { background: var(--bg-body); border-left: 4px solid var(--primary); padding: 10px; margin-top: 10px; border-radius: 4px; font-size:13px; }
    </style>
</head>
<body>
    <div class="container" style="margin-top:40px;">
        <h1>Mon Emploi du Temps</h1>
        <div class="planning-grid">
            <?php foreach($jours as $n=>$j): ?>
                <div class="day-col">
                    <div class="day-header"><?= $j ?></div>
                    <?php foreach($planning[$n] as $c): ?>
                        <div class="slot">
                            <strong><?= $c['heure_debut'] ?> - <?= $c['heure_fin'] ?></strong><br>
                            <span style="color:var(--primary);font-weight:600;"><?= htmlspecialchars($c['titre']) ?></span><br>
                            📍 Salle <?= htmlspecialchars($c['salle']) ?><br>
                            👨‍🏫 M. <?= htmlspecialchars($c['prof_nom']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:20px;"><a href="dashboard_etudiant.php" class="btn-action" style="text-decoration:none;">Retour</a></p>
    </div>
</body>
</html>