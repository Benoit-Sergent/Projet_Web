<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$etudiant_id = $_SESSION['utilisateur_id'];

// 1. Infos de l'étudiant et de sa classe
$stmt_user = $db->prepare("
    SELECT u.prenom, u.nom, u.groupe_id, g.nom AS nom_groupe 
    FROM utilisateurs u 
    LEFT JOIN groupes g ON u.groupe_id = g.id 
    WHERE u.id = ?
");
$stmt_user->execute([$etudiant_id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

// 2. Préparation du tableau de la semaine (Lundi à Vendredi)
$jours_semaine = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi'];
$planning_organise = [];
foreach ($jours_semaine as $num => $nom) {
    $planning_organise[$num] = [];
}

// 3. Récupération automatique des créneaux de la classe
if ($user['groupe_id']) {
    $stmt_planning = $db->prepare("
        SELECT cc.jour_semaine, cc.heure_debut, cc.heure_fin, cc.salle, 
               c.titre, c.categorie, 
               p.nom AS prof_nom, p.prenom AS prof_prenom
        FROM cours_creneaux cc
        JOIN cours c ON cc.cours_id = c.id
        LEFT JOIN utilisateurs p ON c.professeur_id = p.id
        WHERE c.groupe_id = ?
        ORDER BY cc.jour_semaine, cc.heure_debut
    ");
    $stmt_planning->execute([$user['groupe_id']]);
    $creneaux = $stmt_planning->fetchAll(PDO::FETCH_ASSOC);
    
    // On range chaque cours dans le bon jour
    foreach ($creneaux as $c) {
        $planning_organise[$c['jour_semaine']][] = $c;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Planning - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .planning-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-top: 20px; }
        .day-col { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 15px; min-height: 400px; box-shadow: var(--shadow-soft); }
        .day-header { text-align: center; font-weight: bold; border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 15px; color: var(--primary); text-transform: uppercase; font-size: 13px; letter-spacing: 1px; }
        .course-slot { background: var(--bg-body); border-left: 4px solid var(--primary); padding: 12px; margin-bottom: 12px; border-radius: 6px; font-size: 13px; transition: transform 0.2s; }
        .course-slot:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .c-time { font-weight: 700; color: var(--text-main); font-size: 14px; margin-bottom: 4px; }
        .c-title { font-weight: 600; color: var(--primary); margin-bottom: 6px; line-height: 1.3; }
        .c-detail { color: var(--text-muted); font-size: 12px; margin-bottom: 3px; display: flex; align-items: center; gap: 5px; }
    </style>
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo SmartCampus" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong>
                <span><?= $user['nom_groupe'] ? htmlspecialchars($user['nom_groupe']) : 'Étudiant' ?></span>
            </div>
            <div class="avatar-small"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_etudiant.php">Dashboard</a>
        <a href="profil.php">Profil</a>
        <a href="mes_cours.php">Mes Cours</a>
        <a href="mes_notes.php">Notes</a>
        <a href="presences.php">Présences</a>
        <a href="planning.php" class="active">Emploi du temps</a>
    </nav>

    <div class="container">
        <h1>Mon Emploi du Temps</h1>

        <?php if (!$user['groupe_id']): ?>
            <div class="alert alert-error">
                <strong>Attention :</strong> Vous n'êtes actuellement assigné à aucune classe. Votre emploi du temps est vide. Veuillez contacter la scolarité.
            </div>
        <?php else: ?>
            <p style="color:var(--text-muted);">Voici le planning officiel pour la classe <strong><?= htmlspecialchars($user['nom_groupe']) ?></strong>.</p>
            
            <div class="planning-grid">
                <?php foreach ($jours_semaine as $num => $nom): ?>
                    <div class="day-col">
                        <div class="day-header"><?= $nom ?></div>
                        
                        <?php if (empty($planning_organise[$num])): ?>
                            <p style="color: var(--text-muted); font-style: italic; text-align: center; font-size: 12px; margin-top: 20px;">Aucun cours</p>
                        <?php else: ?>
                            <?php foreach ($planning_organise[$num] as $cours): ?>
                                <div class="course-slot">
                                    <div class="c-time"><?= date('H:i', strtotime($cours['heure_debut'])) ?> - <?= date('H:i', strtotime($cours['heure_fin'])) ?></div>
                                    <div class="c-title"><?= htmlspecialchars($cours['titre']) ?></div>
                                    <div class="c-detail">📍 <?= htmlspecialchars($cours['salle']) ?></div>
                                    <div class="c-detail">👨‍🏫 <?= $cours['prof_nom'] ? htmlspecialchars($cours['prof_nom'] . ' ' . $cours['prof_prenom']) : 'À définir' ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>