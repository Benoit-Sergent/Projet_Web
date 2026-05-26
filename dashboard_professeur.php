<?php
session_start();
// Barrière de sécurité
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

// 1. Récupération des infos du professeur connecté
$stmt_prof = $db->prepare("SELECT prenom, nom, email FROM utilisateurs WHERE id = ?");
$stmt_prof->execute([$_SESSION['utilisateur_id']]);
$prof = $stmt_prof->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($prof['prenom'], 0, 1) . substr($prof['nom'], 0, 1));

// 2. Récupération des étudiants pour le menu déroulant
$etudiants = $db->query("SELECT id, prenom, nom FROM utilisateurs WHERE role = 'etudiant' ORDER BY nom");
$nb_etudiants = $etudiants->rowCount(); // Pour les statistiques

// 3. Récupération des cours
$cours = $db->query("SELECT id, titre FROM cours ORDER BY titre");

// 4. Récupération de toutes les notes déjà saisies
$toutes_les_notes = $db->query("
    SELECT utilisateurs.nom, utilisateurs.prenom, cours.titre, notes.valeur_note, notes.commentaire 
    FROM notes 
    JOIN utilisateurs ON notes.etudiant_id = utilisateurs.id 
    JOIN cours ON notes.cours_id = cours.id
    ORDER BY notes.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$nb_evaluations = count($toutes_les_notes);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Professeur - SmartCampus</title>
    <style>
        /* Variables communes */
        :root {
            --primary: #C5A059;
            --primary-light: #fcf8f2;
            --bg-body: #f4f7f6;
            --sidebar-bg: #ffffff;
            --text-main: #202124;
            --text-muted: #5f6368;
            --border: #e8eaed;
        }

        body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-body); margin: 0; display: flex; height: 100vh; overflow: hidden; color: var(--text-main); }

        /* --- BARRE LATÉRALE --- */
        .sidebar { width: 260px; background-color: var(--sidebar-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 20px 0; }
        .sidebar .logo-container { padding: 0 20px 20px; border-bottom: 1px solid var(--border); text-align: center; }
        .sidebar .logo-container img { max-width: 120px; }
        
        .sidebar nav { flex-grow: 1; padding-top: 20px; }
        .sidebar nav a { display: flex; align-items: center; padding: 12px 24px; color: var(--text-muted); text-decoration: none; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .sidebar nav a:hover { background-color: #f8f9fa; color: var(--primary); }
        .sidebar nav a.active { background-color: var(--primary-light); color: var(--primary); border-left: 4px solid var(--primary); }

        .user-widget { padding: 20px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .user-widget .avatar-small { width: 40px; height: 40px; border-radius: 50%; background: #1a73e8; color: white; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 14px; }
        .user-widget-info { font-size: 14px; }
        .user-widget-info span { display: block; color: var(--text-muted); font-size: 12px; }

        /* --- CONTENU PRINCIPAL --- */
        .main-content { flex-grow: 1; padding: 30px 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .btn-action { background-color: var(--primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: 0.3s; }
        .btn-action:hover { background-color: #a68444; }

        /* --- CARTES ET GRILLES --- */
        .stats-top { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .dashboard-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; }
        .card { background: white; border-radius: 12px; padding: 24px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .card-header { border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
        .card-header h2 { margin: 0; font-size: 18px; color: var(--text-main); }

        /* Stats */
        .stat-box { display: flex; align-items: center; justify-content: space-between; }
        .stat-box h3 { margin: 0; font-size: 14px; color: var(--text-muted); font-weight: 500; }
        .stat-box .number { font-size: 32px; font-weight: bold; color: var(--primary); }

        /* Formulaire */
        label { font-weight: 600; font-size: 13px; color: var(--text-muted); display: block; margin-bottom: 5px; margin-top: 15px;}
        input, select, textarea { width: 100%; padding: 12px; margin-bottom: 5px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 14px; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary); }

        /* Tableaux */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--border); font-size: 14px; }
        th { color: var(--text-muted); font-weight: 500; }
        
        .note-badge { font-weight: bold; background: var(--bg-body); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo-container">
            <img src="images/logo.jpg" alt="Logo HEJ" onerror="this.src='https://via.placeholder.com/120x50?text=SmartCampus'">
        </div>
        
<nav>
    <a href="dashboard_professeur.php" class="active">📝 Évaluations</a>
    <a href="mes_etudiants.php">👥 Mes Étudiants</a>
    <a href="parametres.php">⚙️ Paramètres</a>
</nav>

        <div class="user-widget">
            <div class="avatar-small"><?= $initiales ?></div>
            <div class="user-widget-info">
                <strong><?= htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']) ?></strong>
                <span>Professeur</span>
            </div>
        </div>
    </aside>

    <main class="main-content">
        
        <header class="header">
            <div>
                <h1>Espace Pédagogique</h1>
                <span style="color: var(--text-muted); font-size: 14px;">Saisissez les notes et appréciations de vos élèves.</span>
            </div>
            <a href="deconnexion.php" class="btn-action" style="background-color: var(--text-main);">Se déconnecter</a>
        </header>

        <div class="stats-top">
            <div class="card stat-box">
                <div>
                    <h3>Élèves inscrits</h3>
                    <div class="number"><?= $nb_etudiants ?></div>
                </div>
            </div>
            <div class="card stat-box">
                <div>
                    <h3>Évaluations saisies</h3>
                    <div class="number" style="color: #1a73e8;"><?= $nb_evaluations ?></div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            
            <div class="card">
                <div class="card-header">
                    <h2>Saisir une note</h2>
                </div>
                <form action="traitement_note.php" method="POST">
                    
                    <label>Élève évalué</label>
                    <select name="etudiant_id" required>
                        <option value="">-- Choisir un étudiant --</option>
                        <?php while ($e = $etudiants->fetch()): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label>Matière</label>
                    <select name="cours_id" required>
                        <option value="">-- Choisir une matière --</option>
                        <?php while ($c = $cours->fetch()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['titre']) ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label>Note (sur 20)</label>
                    <input type="number" name="valeur_note" step="0.25" min="0" max="20" placeholder="Ex: 14.5" required>
                    
                    <label>Appréciation (facultatif)</label>
                    <textarea name="commentaire" rows="3" placeholder="Ex: Très bon travail, continuez ainsi..."></textarea>
                    
                    <button type="submit" class="btn-action" style="width: 100%; margin-top: 15px;">Enregistrer l'évaluation</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Historique des évaluations</h2>
                </div>
                <?php if (empty($toutes_les_notes)): ?>
                    <p style="color: var(--text-muted); font-style: italic;">Aucune évaluation n'a été saisie sur la plateforme.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Élève</th>
                            <th>Matière</th>
                            <th>Note</th>
                            <th>Appréciation</th>
                        </tr>
                        <?php foreach ($toutes_les_notes as $n): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($n['nom'] . ' ' . $n['prenom']) ?></strong></td>
                                <td><?= htmlspecialchars($n['titre']) ?></td>
                                <td><span class="note-badge"><?= htmlspecialchars($n['valeur_note']) ?></span></td>
                                <td style="color: var(--text-muted); font-style: italic; max-width: 250px;">
                                    <?= !empty($n['commentaire']) ? htmlspecialchars($n['commentaire']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

        </div>
    </main>

</body>
</html>