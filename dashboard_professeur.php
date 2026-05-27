<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$prof_id = $_SESSION['utilisateur_id'];

// Infos du Prof
$stmt_prof = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_prof->execute([$prof_id]);
$prof = $stmt_prof->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($prof['prenom'], 0, 1) . substr($prof['nom'], 0, 1));

// 1. Récupérer UNIQUEMENT les cours assignés à ce professeur
$stmt_cours = $db->prepare("
    SELECT c.id, c.titre, c.groupe_id, g.nom AS nom_groupe 
    FROM cours c 
    LEFT JOIN groupes g ON c.groupe_id = g.id 
    WHERE c.professeur_id = ?
");
$stmt_cours->execute([$prof_id]);
$mes_cours = $stmt_cours->fetchAll(PDO::FETCH_ASSOC);

// 2. Récupérer les étudiants appartenant aux groupes de ce professeur
$stmt_etudiants = $db->prepare("
    SELECT id, prenom, nom, groupe_id 
    FROM utilisateurs 
    WHERE role = 'etudiant' 
    AND groupe_id IN (SELECT groupe_id FROM cours WHERE professeur_id = ?)
    ORDER BY nom
");
$stmt_etudiants->execute([$prof_id]);
$mes_etudiants = $stmt_etudiants->fetchAll(PDO::FETCH_ASSOC);

// 3. Récupérer l'historique des notes saisies par ce prof
$stmt_notes = $db->prepare("
    SELECT u.nom, u.prenom, c.titre, n.valeur_note, n.commentaire 
    FROM notes n 
    JOIN utilisateurs u ON n.etudiant_id = u.id 
    JOIN cours c ON n.cours_id = c.id 
    WHERE c.professeur_id = ?
    ORDER BY n.id DESC
");
$stmt_notes->execute([$prof_id]);
$toutes_les_notes = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Enseignant - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo SmartCampus" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']) ?></strong>
                <span>Professeur</span>
            </div>
            <div class="avatar-small"><?= $initiales ?></div>
        </div>
    </header>

<nav class="top-nav">
        <a href="dashboard_professeur.php" class="active">Évaluations</a>
        <a href="faire_appel.php">Faire l'appel</a>
        <a href="parametres.php">Paramètres</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1 style="margin:0; color:var(--primary);">Saisie & Suivi Pédagogique</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Attribuez les notes pour vos classes assignées.</p>
        </div>

        <?php if (isset($_GET['succes'])): ?>
            <div class="alert alert-success">✅ La note a été enregistrée avec succès.</div>
        <?php endif; ?>

        <div class="dashboard-grid inverse">
            <div class="card">
                <div class="card-header"><h2>Nouvelle Évaluation</h2></div>
                <?php if (empty($mes_cours)): ?>
                    <p style="color: var(--danger); font-size: 13px;">Vous n'êtes assigné à aucun cours pour le moment.</p>
                <?php else: ?>
                    <form action="traitement_note.php" method="POST">
                        <label>Matière enseignée</label>
                        <select name="cours_id" id="coursSelect" required onchange="filtrerEtudiants()">
                            <option value="">-- Sélectionner le cours --</option>
                            <?php foreach ($mes_cours as $c): ?>
                                <option value="<?= $c['id'] ?>" data-groupe="<?= $c['groupe_id'] ?>">
                                    <?= htmlspecialchars($c['titre']) ?> (<?= htmlspecialchars($c['nom_groupe']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Étudiant</label>
                        <select name="etudiant_id" id="etudiantSelect" required>
                            <option value="">-- Sélectionner d'abord un cours --</option>
                            <?php foreach ($mes_etudiants as $e): ?>
                                <option value="<?= $e['id'] ?>" data-groupe="<?= $e['groupe_id'] ?>" style="display:none;">
                                    <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Note (sur 20)</label>
                        <input type="number" name="valeur_note" step="0.25" min="0" max="20" required placeholder="Ex: 15.5">
                        
                        <label>Appréciation pédagogique</label>
                        <textarea name="commentaire" rows="3" placeholder="Commentaire sur le travail rendu..."></textarea>
                        
                        <button type="submit" class="btn-action" style="width: 100%; margin-top: 20px;">Enregistrer la Note</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header"><h2>Notes Récemment Attribuées</h2></div>
                <?php if (empty($toutes_les_notes)): ?>
                    <p style="color: var(--text-muted); font-style: italic; font-size:13px;">Aucune évaluation saisie pour le moment.</p>
                <?php else: ?>
                    <table>
                        <tr><th>Élève</th><th>Matière</th><th>Note</th><th>Appréciation</th></tr>
                        <?php foreach ($toutes_les_notes as $n): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($n['nom'] . ' ' . $n['prenom']) ?></strong></td>
                                <td style="font-size:12px;"><?= htmlspecialchars($n['titre']) ?></td>
                                <td><span class="badge badge-neutral"><?= htmlspecialchars($n['valeur_note']) ?></span></td>
                                <td style="color: var(--text-muted); font-style: italic; font-size:12px;"><?= !empty($n['commentaire']) ? htmlspecialchars($n['commentaire']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function filtrerEtudiants() {
            var coursSelect = document.getElementById('coursSelect');
            var etudiantSelect = document.getElementById('etudiantSelect');
            
            // Récupérer le groupe_id du cours sélectionné
            var selectedOption = coursSelect.options[coursSelect.selectedIndex];
            var groupeCible = selectedOption.getAttribute('data-groupe');

            // Réinitialiser le select étudiant
            etudiantSelect.value = "";
            etudiantSelect.options[0].text = groupeCible ? "-- Sélectionner l'élève --" : "-- Sélectionner d'abord un cours --";

            // Afficher uniquement les étudiants du bon groupe
            for (var i = 1; i < etudiantSelect.options.length; i++) {
                var option = etudiantSelect.options[i];
                if (option.getAttribute('data-groupe') === groupeCible) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>