<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$prof_id = $_SESSION['utilisateur_id'];
$message_succes = ""; 
$message_erreur = "";

// ==========================================
// 1. GESTION DU FORMULAIRE : AJOUT D'UNE NOTE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_note') {
    $etudiant_id = intval($_POST['etudiant_id']);
    $cours_id = intval($_POST['cours_id']);
    $valeur = floatval($_POST['valeur_note']);
    $commentaire = trim($_POST['commentaire']);

    if ($valeur >= 0 && $valeur <= 20) {
        $stmt = $db->prepare("
            INSERT INTO notes (etudiant_id, cours_id, valeur_note, commentaire) 
            VALUES (?, ?, ?, ?)
        ");
        if ($stmt->execute([$etudiant_id, $cours_id, $valeur, $commentaire])) {
            $message_succes = "L'évaluation a été enregistrée avec succès.";
        } else {
            $message_erreur = "Erreur lors de l'enregistrement de la note.";
        }
    } else {
        $message_erreur = "La note doit impérativement être comprise entre 0 et 20.";
    }
}

// ==========================================
// 2. CHARGEMENT DES DONNÉES DU TABLEAU DE BORD
// ==========================================
// Liste des cours dispensés par ce professeur
$mes_cours = $db->query("
    SELECT c.*, g.nom as groupe_nom 
    FROM cours c 
    JOIN groupes g ON c.groupe_id = g.id 
    WHERE c.professeur_id = $prof_id 
    ORDER BY c.titre
")->fetchAll();

$nb_cours = count($mes_cours);

// 3 prochaines sessions de cours avec les vrais horaires (tri chronologique)
$prochains_cours_prof = $db->query("
    SELECT c.*, g.nom as groupe_nom 
    FROM cours c 
    JOIN groupes g ON c.groupe_id = g.id 
    WHERE c.professeur_id = $prof_id 
    ORDER BY CASE c.jour 
        WHEN 'Lundi' THEN 1 WHEN 'Mardi' THEN 2 WHEN 'Mercredi' THEN 3 
        WHEN 'Jeudi' THEN 4 WHEN 'Vendredi' THEN 5 ELSE 6 
    END, c.heure_debut ASC
    LIMIT 3
")->fetchAll();

// Liste complète des étudiants inscrits dans ses classes (pour le sélecteur)
$etudiants_possibles = $db->query("
    SELECT u.id, u.nom, u.prenom, u.groupe_id, g.nom as groupe_nom 
    FROM utilisateurs u 
    JOIN groupes g ON u.groupe_id = g.id 
    WHERE u.role = 'etudiant' 
      AND u.groupe_id IN (SELECT groupe_id FROM cours WHERE professeur_id = $prof_id)
    ORDER BY g.nom, u.nom
")->fetchAll();

// Historique global des notes attribuées par ce professeur
$historique_notes = $db->query("
    SELECT n.*, u.nom as etud_nom, u.prenom as etud_prenom, c.titre as cours_titre 
    FROM notes n 
    JOIN utilisateurs u ON n.etudiant_id = u.id 
    JOIN cours c ON n.cours_id = c.id 
    WHERE c.professeur_id = $prof_id 
    ORDER BY n.id DESC
")->fetchAll();

$nb_notes_donnees = count($historique_notes);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Enseignant - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Grille des indicateurs */
        .stats-container { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 20px; 
            margin-bottom: 25px; 
        }
        .stat-card { 
            display: flex; 
            align-items: center; 
            padding: 24px; 
            gap: 20px; 
        }
        .stat-icon { 
            width: 54px; 
            height: 54px; 
            border-radius: 14px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: #e0e7ff; 
            color: #4f46e5; 
        }
        
        .stat-info h2 { 
            font-size: 12px; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            margin-bottom: 4px; 
            letter-spacing: 0.05em;
        }
        .stat-info .stat-value { 
            font-size: 32px; 
            font-weight: 700; 
            color: var(--text-main); 
        }
        
        /* Design de l'Aperçu Agenda */
        .timeline { 
            position: relative; 
            padding-left: 24px; 
        }
        .timeline::before { 
            content: ''; 
            position: absolute; 
            left: 7px; 
            top: 8px; 
            bottom: 8px; 
            width: 2px; 
            background: var(--border); 
        }
        .timeline-item { 
            position: relative; 
            margin-bottom: 15px; 
        }
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        .timeline-badge { 
            position: absolute; 
            left: -22px; 
            top: 6px; 
            width: 8px; 
            height: 8px; 
            border-radius: 50%; 
            background: #10b981; 
            border: 2px solid var(--surface); 
            box-shadow: 0 0 0 3px #d1fae5; 
        }
        .timeline-content { 
            background: var(--bg-body); 
            padding: 12px 16px; 
            border-radius: var(--radius-md); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }

        .empty-state { 
            text-align: center; 
            padding: 40px 20px; 
            color: var(--text-muted); 
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="color:var(--primary);">Espace Pédagogique</h1>
            <p style="color:var(--text-muted); margin:0;">Saisissez vos évaluations et suivez la progression de vos classes.</p>
        </div>

        <?php if ($message_succes): ?>
            <div class="alert alert-success"><span>✅ <?= $message_succes ?></span></div>
        <?php endif; ?>
        <?php if ($message_erreur): ?>
            <div class="alert alert-error"><span>⚠️ <?= $message_erreur ?></span></div>
        <?php endif; ?>

        <div class="stats-container">
            <div class="card stat-card">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253" />
                    </svg>
                </div>
                <div class="stat-info">
                    <h2>Enseignements Actifs</h2>
                    <div class="stat-value"><?= $nb_cours ?></div>
                </div>
            </div>
            
            <div class="card" style="padding: 15px 24px;">
                <h2 style="font-size:12px; text-transform:uppercase; color:var(--text-muted); margin-bottom:10px; letter-spacing:0.05em;">📅 Prochaines sessions</h2>
                <?php if (empty($prochains_cours_prof)): ?>
                    <p style="font-size:13px; color:var(--text-muted); margin:5px 0;">Aucun cours planifié.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach($prochains_cours_prof as $pcp): ?>
                            <div class="timeline-item">
                                <div class="timeline-badge"></div>
                                <div class="timeline-content" style="padding:8px 12px;">
                                    <div>
                                        <span style="font-size:13px; font-weight:600; display:block;">
                                            <?= htmlspecialchars($pcp['titre']) ?> (<?= htmlspecialchars($pcp['groupe_nom']) ?>)
                                        </span>
                                        <span style="font-size:11px; color:var(--text-muted);">
                                            <?= htmlspecialchars($pcp['salle'] ?? 'Salle à définir') ?>
                                        </span>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="badge badge-neutral" style="font-size:10px;">
                                            <?= htmlspecialchars($pcp['jour'] ?? 'Lundi') ?>
                                        </span><br>
                                        <span style="font-size:11px; font-weight:600; color:var(--primary);">
                                            <?= htmlspecialchars(substr($pcp['heure_debut'] ?? '08:30', 0, 5)) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid inverse">
            
            <div class="card" style="align-self: start;">
                <div class="card-header"><h2>Saisir une note</h2></div>
                <?php if ($nb_cours == 0): ?>
                    <p style="color:var(--text-muted); font-size:14px;">Vous n'avez pas de cours assigné.</p>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="ajouter_note">
                        
                        <label>Cours &amp; Classe rattachée</label>
                        <select name="cours_id" id="coursSelect" required onchange="filtrerEtudiants()">
                            <option value="">-- Choisir un cours --</option>
                            <?php foreach($mes_cours as $c): ?>
                                <option value="<?= $c['id'] ?>" data-groupe="<?= $c['groupe_id'] ?>">
                                    <?= htmlspecialchars($c['titre'] . ' (' . $c['groupe_nom'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label>Étudiant</label>
                        <select name="etudiant_id" id="etudiantSelect" required disabled>
                            <option value="">-- Choisir d'abord un cours --</option>
                            <?php foreach($etudiants_possibles as $e): ?>
                                <option value="<?= $e['id'] ?>" data-groupe="<?= $e['groupe_id'] ?>">
                                    <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label>Note / 20</label>
                        <input type="number" name="valeur_note" step="0.25" min="0" max="20" required placeholder="Ex: 15.5">
                        
                        <label>Appréciation générale</label>
                        <textarea name="commentaire" rows="3" placeholder="Saisir un commentaire..."></textarea>
                        
                        <button type="submit" class="btn-action" style="width:100%; margin-top:10px;">Enregistrer la note</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header"><h2>Dernières évaluations enregistrées</h2></div>
                <?php if (empty($historique_notes)): ?>
                    <div class="empty-state">
                        <p>Aucune note enregistrée pour le moment.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Étudiant</th>
                            <th>Matière</th>
                            <th>Note</th>
                        </tr>
                        <?php foreach(array_slice($historique_notes, 0, 10) as $n): ?>
                            <?php 
                                $e_avatar = glob("uploads/avatars/avatar_" . $n['etudiant_id'] . ".*");
                                $e_init = strtoupper(substr($n['etud_prenom'], 0, 1) . substr($n['etud_nom'], 0, 1));
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div class="avatar-small" style="width:28px; height:28px; font-size:10px; box-shadow:none;">
                                            <?php if (!empty($e_avatar)): ?>
                                                <img src="<?= $e_avatar[0] ?>" alt="Pic">
                                            <?php else: ?>
                                                <?= $e_init ?>
                                            <?php endif; ?>
                                        </div>
                                        <strong><?= htmlspecialchars($n['etud_nom'] . ' ' . $n['etud_prenom']) ?></strong>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($n['cours_titre']) ?></td>
                                <td><span class="badge badge-success"><?= number_format($n['valeur_note'], 2, ',', ' ') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Script pour n'afficher que les élèves de la classe sélectionnée
        function filtrerEtudiants() {
            var cs = document.getElementById('coursSelect'); 
            var es = document.getElementById('etudiantSelect');
            var sel = cs.options[cs.selectedIndex]; 
            
            if (!sel.value) { 
                es.disabled = true; 
                return; 
            }
            
            var grp = sel.getAttribute('data-groupe'); 
            es.disabled = false;
            
            for (var i = 0; i < es.options.length; i++) {
                if (es.options[i].value === "") {
                    continue;
                }
                if (es.options[i].getAttribute('data-groupe') === grp) {
                    es.options[i].style.display = 'block';
                } else {
                    es.options[i].style.display = 'none';
                }
            }
            es.selectedIndex = 0;
        }
    </script>
    
    <?php include 'footer.php'; ?>
</body>
</html>