<?php
session_start();
// Barrière de sécurité : accès étudiant uniquement
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'etudiant') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

// 1. Récupération des infos de l'utilisateur
$stmt_info = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_info->execute([$_SESSION['utilisateur_id']]);
$user_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($user_info['prenom'], 0, 1) . substr($user_info['nom'], 0, 1));

// 2. Récupération de tous les cours disponibles, triés par catégorie
$liste_cours = $db->query("SELECT * FROM cours ORDER BY categorie, titre")->fetchAll(PDO::FETCH_ASSOC);

// 3. Calcul de la progression (ex: nombre de cours évalués / nombre total)
$total_cours = count($liste_cours);
$stmt_notes = $db->prepare("SELECT COUNT(DISTINCT cours_id) as cours_evalues FROM notes WHERE etudiant_id = ?");
$stmt_notes->execute([$_SESSION['utilisateur_id']]);
$cours_evalues = $stmt_notes->fetch(PDO::FETCH_ASSOC)['cours_evalues'];

$progression = $total_cours > 0 ? round(($cours_evalues / $total_cours) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Cours - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Styles spécifiques pour la grille des cours */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .course-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 25px;
            box-shadow: var(--shadow-soft);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary);
        }

        .course-category {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .course-title {
            font-size: 18px;
            color: var(--text-main);
            margin: 0 0 15px 0;
            font-family: 'Playfair Display', serif;
        }

        .course-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            flex-grow: 1;
            margin-bottom: 20px;
        }

        .progress-container {
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-soft);
        }

        .progress-bar-bg {
            background-color: var(--border);
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-bar-fill {
            background: var(--primary-grad);
            height: 100%;
            transition: width 1s ease-in-out;
        }
    </style>
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo HEJ" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?></strong>
                <span>Étudiant</span>
            </div>
            <div class="avatar-small"><?= $initiales ?></div>
            <a href="deconnexion.php" style="margin-left: 15px; color: var(--danger); text-decoration: none; font-size: 13px; font-weight: 600;">Déconnexion</a>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_etudiant.php">Dashboard</a>
        <a href="profil.php">Profil</a>
        <a href="mes_cours.php" class="active">Mes Cours</a>
        <a href="mes_notes.php">Notes</a>
        <a href="presences.php">Présences</a>
        <a href="planning.php">Emploi du temps</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="margin:0; color:var(--primary);">Livret de Formation</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Consultez le catalogue des matières de votre cursus.</p>
        </div>

        <div class="progress-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <strong style="font-size: 14px;">Progression des évaluations</strong>
                <span style="font-size: 14px; font-weight: bold; color: var(--primary);"><?= $progression ?>%</span>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: <?= $progression ?>%;"></div>
            </div>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: var(--text-muted);">Vous avez été évalué sur <?= $cours_evalues ?> matière(s) sur un total de <?= $total_cours ?>.</p>
        </div>

        <div class="courses-grid">
            <?php if (empty($liste_cours)): ?>
                <p style="color: var(--text-muted);">Aucun cours n'est actuellement au programme.</p>
            <?php else: ?>
                <?php foreach ($liste_cours as $cours): ?>
                    <div class="course-card">
                        <div>
                            <div class="course-category"><?= htmlspecialchars($cours['categorie']) ?></div>
                            <h3 class="course-title"><?= htmlspecialchars($cours['titre']) ?></h3>
                            <div class="course-desc">
                                <?= htmlspecialchars(strlen($cours['description']) > 100 ? substr($cours['description'], 0, 100) . '...' : $cours['description']) ?>
                                <?php if(empty($cours['description'])) echo "<em>Aucune description fournie par l'équipe pédagogique.</em>"; ?>
                            </div>
                        </div>
                       <a href="detail_cours.php?id=<?= $cours['id'] ?>" class="btn-action" style="width: 100%;">Voir les détails</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>