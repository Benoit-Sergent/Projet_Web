<?php
session_start();
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'etudiant') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

// Vérification qu'un ID de cours a bien été transmis
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: mes_cours.php");
    exit();
}
$cours_id = $_GET['id'];

// 1. Récupération des informations du cours
$stmt_cours = $db->prepare("SELECT * FROM cours WHERE id = ?");
$stmt_cours->execute([$cours_id]);
$cours = $stmt_cours->fetch(PDO::FETCH_ASSOC);

// Si le cours n'existe pas, on redirige
if (!$cours) {
    header("Location: mes_cours.php");
    exit();
}

// 2. Récupération de la note de l'étudiant pour ce cours spécifique
$stmt_note = $db->prepare("SELECT valeur_note, commentaire FROM notes WHERE cours_id = ? AND etudiant_id = ?");
$stmt_note->execute([$cours_id, $_SESSION['utilisateur_id']]);
$evaluation = $stmt_note->fetch(PDO::FETCH_ASSOC);

// 3. Infos de l'utilisateur pour le header
$stmt_info = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_info->execute([$_SESSION['utilisateur_id']]);
$user_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($user_info['prenom'], 0, 1) . substr($user_info['nom'], 0, 1));

// (Optionnel) Simulation de documents rattachés au cours
$documents_simules = [
    ['titre' => 'Syllabus du cours.pdf', 'taille' => '1.2 MB', 'type' => 'PDF'],
    ['titre' => 'Support de présentation - Chapitre 1.pdf', 'taille' => '4.5 MB', 'type' => 'PDF'],
    ['titre' => 'Exercices pratiques.docx', 'taille' => '800 KB', 'type' => 'DOCX']
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($cours['titre']) ?> - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s;
        }
        .back-link:hover { color: var(--primary); }
        
        .course-banner {
            background: var(--primary-grad);
            border-radius: 16px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            box-shadow: var(--shadow-soft);
        }
        .course-banner h1 { margin: 0 0 10px 0; color: white; font-size: 32px; }
        .course-banner .badge { background: rgba(255,255,255,0.2); color: white; backdrop-filter: blur(5px); }

        .doc-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 10px;
            transition: border-color 0.3s;
        }
        .doc-item:hover { border-color: var(--primary); background-color: var(--primary-light); }
        .doc-icon { font-size: 24px; margin-right: 15px; }
        .doc-info { flex-grow: 1; }
        .doc-title { font-weight: 600; font-size: 14px; margin: 0 0 4px 0; color: var(--text-main); }
        .doc-meta { font-size: 12px; color: var(--text-muted); margin: 0; }
        
        .grade-highlight {
            font-size: 48px;
            font-weight: bold;
            color: var(--primary);
            text-align: center;
            display: block;
            margin: 10px 0;
            font-family: 'Playfair Display', serif;
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
        <a href="mes_cours.php" class="back-link">← Retour au catalogue des cours</a>

        <div class="course-banner">
            <span class="badge"><?= htmlspecialchars($cours['categorie']) ?></span>
            <h1><?= htmlspecialchars($cours['titre']) ?></h1>
            <p style="margin: 0; opacity: 0.9; font-size: 15px;">Détails, ressources et évaluations liés à cet enseignement.</p>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
            
            <div>
                <div class="card" style="margin-bottom: 25px;">
                    <div class="card-header"><h2>À propos de ce cours</h2></div>
                    <p style="color: var(--text-muted); line-height: 1.6; font-size: 15px;">
                        <?= nl2br(htmlspecialchars($cours['description'])) ?>
                        <?php if(empty($cours['description'])) echo "<em>Aucune description détaillée n'a été fournie pour cette matière.</em>"; ?>
                    </p>
                </div>

                <div class="card">
                    <div class="card-header"><h2>Ressources documentaires</h2></div>
                    <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">Support de cours et fichiers mis à disposition par l'enseignant.</p>
                    
                    <?php foreach ($documents_simules as $doc): ?>
                        <div class="doc-item">
                            <div class="doc-icon">📄</div>
                            <div class="doc-info">
                                <h4 class="doc-title"><?= htmlspecialchars($doc['titre']) ?></h4>
                                <p class="doc-meta"><?= htmlspecialchars($doc['type']) ?> • <?= htmlspecialchars($doc['taille']) ?></p>
                            </div>
                            <button class="btn-action" style="padding: 8px 16px; font-size: 12px; border-radius: 6px;">Télécharger</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-header"><h2>Mon Évaluation</h2></div>
                    <?php if ($evaluation): ?>
                        <div style="text-align: center; padding: 10px 0;">
                            <span style="font-size: 13px; color: var(--text-muted); text-transform: uppercase;">Note finale</span>
                            <span class="grade-highlight"><?= htmlspecialchars($evaluation['valeur_note']) ?></span>
                            <span style="color: var(--text-muted); font-size: 14px;">/ 20</span>
                        </div>
                        <?php if (!empty($evaluation['commentaire'])): ?>
                            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border);">
                                <label style="margin-top: 0;">Appréciation du professeur</label>
                                <p style="font-style: italic; font-size: 13px; color: var(--text-main); line-height: 1.5; margin: 5px 0 0 0;">
                                    "<?= htmlspecialchars($evaluation['commentaire']) ?>"
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px 0;">
                            <div style="font-size: 32px; margin-bottom: 10px;">⏳</div>
                            <p style="color: var(--text-muted); font-size: 14px; font-weight: 500;">En attente d'évaluation</p>
                            <p style="color: var(--text-muted); font-size: 12px; margin-top: 5px;">Le professeur n'a pas encore saisi de note pour cette matière.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>