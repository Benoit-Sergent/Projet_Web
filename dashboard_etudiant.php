<?php
session_start();
// Barrière de sécurité
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

// 1. Récupération des infos de l'étudiant
$stmt_user = $db->prepare("SELECT prenom, nom, email FROM utilisateurs WHERE id = ?");
$stmt_user->execute([$_SESSION['utilisateur_id']]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// 2. Récupération du programme total
$requete_prog = $db->query("SELECT * FROM cours ORDER BY categorie");
$liste_cours = $requete_prog->fetchAll(PDO::FETCH_ASSOC);
$total_cours = count($liste_cours);

// 3. Récupération des notes de l'étudiant
$stmt = $db->prepare("
    SELECT cours.titre, notes.valeur_note, notes.commentaire 
    FROM notes 
    JOIN cours ON notes.cours_id = cours.id 
    WHERE notes.etudiant_id = ?
");
$stmt->execute([$_SESSION['utilisateur_id']]);
$mes_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Calculs des statistiques
$moyenne_generale = 0;
$nombre_notes = count($mes_notes);
$progression_pourcentage = 0;

if ($total_cours > 0) {
    $progression_pourcentage = round(($nombre_notes / $total_cours) * 100);
}

if ($nombre_notes > 0) {
    $somme = 0;
    foreach ($mes_notes as $note) {
        $somme += $note['valeur_note'];
    }
    $moyenne_generale = round($somme / $nombre_notes, 2);
}

// Initiales pour l'avatar
$initiales = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace - SmartCampus</title>
    <style>
        /* Variables CSS pour gérer facilement les couleurs */
        :root {
            --primary: #C5A059;
            --primary-light: #fcf8f2;
            --bg-body: #f4f7f6;
            --sidebar-bg: #ffffff;
            --text-main: #202124;
            --text-muted: #5f6368;
            --border: #e8eaed;
        }

        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-body);
            margin: 0;
            display: flex;
            height: 100vh;
            overflow: hidden;
            color: var(--text-main);
        }

        /* --- BARRE LATÉRALE (SIDEBAR) --- */
        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }

        .sidebar .logo-container {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border);
            text-align: center;
        }

        .sidebar .logo-container img { max-width: 120px; }

        .sidebar nav {
            flex-grow: 1;
            padding-top: 20px;
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            padding: 12px 24px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s;
        }

        .sidebar nav a:hover { background-color: #f8f9fa; color: var(--primary); }
        .sidebar nav a.active {
            background-color: var(--primary-light);
            color: var(--primary);
            border-left: 4px solid var(--primary);
        }

        .user-widget {
            padding: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-widget .avatar-small {
            width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white;
            display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 14px;
        }
        
        .user-widget-info { font-size: 14px; }
        .user-widget-info span { display: block; color: var(--text-muted); font-size: 12px; }

        /* --- CONTENU PRINCIPAL --- */
        .main-content {
            flex-grow: 1;
            padding: 30px 40px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }

        .btn-action {
            background-color: var(--primary); color: white; padding: 10px 20px;
            border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px;
            border: none; cursor: pointer;
        }

        /* --- SYSTÈME DE CARTES --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .card {
            background: white; border-radius: 12px; padding: 24px;
            border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .card-header { border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
        .card-header h2 { margin: 0; font-size: 18px; color: var(--text-main); }

        /* Profil Header dans la carte */
        .profile-banner { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; }
        .profile-banner .avatar-large {
            width: 90px; height: 90px; border-radius: 50%; background: var(--primary-light);
            color: var(--primary); display: flex; justify-content: center; align-items: center;
            font-size: 32px; font-weight: bold; border: 2px solid var(--primary);
        }
        .profile-banner .info h2 { margin: 0 0 5px 0; font-size: 22px; }
        .badge { background: #e6f4ea; color: #137333; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }

        /* Statistiques */
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .stat-box { padding: 15px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border); }
        .stat-box .label { font-size: 13px; color: var(--text-muted); display: block; margin-bottom: 5px; }
        .stat-box .value { font-size: 24px; font-weight: bold; color: var(--primary); }

        /* Barre de progression */
        .progress-bar-bg { width: 100%; background-color: var(--border); border-radius: 10px; height: 10px; overflow: hidden; margin-top: 10px; }
        .progress-bar-fill { background-color: var(--primary); height: 100%; transition: width 0.5s ease; }

        /* Tableaux */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 0; text-align: left; border-bottom: 1px solid var(--border); font-size: 14px; }
        th { color: var(--text-muted); font-weight: 500; }
        .note-valide { color: #137333; font-weight: bold; background: #e6f4ea; padding: 4px 8px; border-radius: 4px; }
        .note-echec { color: #d93025; font-weight: bold; background: #fce8e6; padding: 4px 8px; border-radius: 4px; }

        /* Liste des cours */
        ul { list-style: none; padding: 0; margin: 0; }
        ul li { padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
        ul li::before { content: "📘"; margin-right: 10px; font-size: 12px; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo-container">
            <img src="images/logo.jpg" alt="Logo HEJ" onerror="this.src='https://via.placeholder.com/120x50?text=SmartCampus'">
        </div>
        
<nav>
    <a href="dashboard_etudiant.php" class="active">📊 Tableau de bord</a>
    <a href="#programme">📚 Programme</a>
    <a href="#resultats">📝 Mes Résultats</a>
    <a href="parametres.php">⚙️ Paramètres</a>
</nav>

        <div class="user-widget">
            <div class="avatar-small"><?= $initiales ?></div>
            <div class="user-widget-info">
                <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong>
                <span>Étudiant 1ère année</span>
            </div>
        </div>
    </aside>

    <main class="main-content">
        
        <header class="header">
            <div>
                <h1>Mon Parcours Académique</h1>
                <span style="color: var(--text-muted); font-size: 14px;">Consultez vos résultats et votre progression.</span>
            </div>
            <a href="deconnexion.php" class="btn-action" style="background-color: var(--text-main);">Se déconnecter</a>
        </header>

        <div class="dashboard-grid">
            
            <div class="column">
                
                <div class="card" style="margin-bottom: 24px;">
                    <div class="profile-banner">
                        <div class="avatar-large"><?= $initiales ?></div>
                        <div class="info">
                            <h2><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?> <span class="badge">Étudiant actif</span></h2>
                            <p style="margin: 5px 0; color: var(--text-muted); font-size: 14px;">✉️ <?= htmlspecialchars($user['email']) ?></p>
                        </div>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <span class="label">Moyenne Générale</span>
                            <span class="value"><?= $nombre_notes > 0 ? number_format($moyenne_generale, 2, ',', ' ') : '-' ?> <span style="font-size: 14px; color: var(--text-muted);">/ 20</span></span>
                        </div>
                        <div class="stat-box">
                            <span class="label">Crédits / Matières validées</span>
                            <span class="value"><?= $nombre_notes ?> <span style="font-size: 14px; color: var(--text-muted);">/ <?= $total_cours ?></span></span>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?= $progression_pourcentage ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Historique des évaluations</h2>
                    </div>
                    <?php if (empty($mes_notes)): ?>
                        <p style="color: var(--text-muted); font-style: italic;">Aucune note enregistrée pour le moment.</p>
                    <?php else: ?>
                        <table>
                            <tr>
                                <th>Matière évaluée</th>
                                <th>Note</th>
                                <th>Appréciation du professeur</th>
                            </tr>
                            <?php foreach ($mes_notes as $note): 
                                $classe_note = ($note['valeur_note'] >= 10) ? 'note-valide' : 'note-echec';
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($note['titre']) ?></strong></td>
                                    <td><span class="<?= $classe_note ?>"><?= htmlspecialchars($note['valeur_note']) ?></span></td>
                                    <td style="color: var(--text-muted); font-style: italic;">
                                        <?= !empty($note['commentaire']) ? htmlspecialchars($note['commentaire']) : 'Aucune appréciation' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>

            </div>

            <div class="column">
                
                <div class="card">
                    <div class="card-header">
                        <h2>Programme officiel</h2>
                    </div>
                    
                    <?php 
                    $categorie_actuelle = '';
                    foreach ($liste_cours as $cours): 
                        if ($cours['categorie'] !== $categorie_actuelle): 
                            $categorie_actuelle = $cours['categorie'];
                            echo "<h3 style='font-size: 13px; text-transform: uppercase; color: var(--text-muted); margin-top: 20px; margin-bottom: 10px;'>" . htmlspecialchars($categorie_actuelle) . "</h3><ul>";
                        endif; 
                        echo "<li>" . htmlspecialchars($cours['titre']) . "</li>";
                        
                        $index = array_search($cours, $liste_cours);
                        if (!isset($liste_cours[$index + 1]) || $liste_cours[$index + 1]['categorie'] !== $categorie_actuelle) {
                            echo "</ul>";
                        }
                    endforeach; 
                    ?>
                </div>

            </div>

        </div>
    </main>

</body>
</html>