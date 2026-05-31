<?php
session_start();
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'professeur') { 
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
            background: var(--primary);
            border-radius: 16px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            box-shadow: var(--shadow-soft);
        }
        .course-banner h1 { margin: 0 0 10px 0; color: white; font-size: 32px; text-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .course-banner p { color: rgba(255,255,255,0.9); text-shadow: 0 1px 2px rgba(0,0,0,0.15); }
        .course-banner .badge { background: rgba(0,0,0,0.2); color: white; backdrop-filter: blur(5px); }

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
    <?php include 'menu.php'; ?>

    <div class="container">
        <a href="mes_cours_prof.php" class="back-link">← Retour au catalogue des cours</a>

        <div class="course-banner">
            <span class="badge"><?= htmlspecialchars($cours['categorie']) ?></span>
            <h1><?= htmlspecialchars($cours['titre']) ?></h1>
            <p style="margin: 0; opacity: 0.9; font-size: 15px;">Détails, ressources et évaluations liés à cet enseignement.</p>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 1fr;">
            
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

        </div>
    </div>
    <?php include 'footer.php'; ?> 
</body>
</html>