<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$stmt_prof = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_prof->execute([$_SESSION['utilisateur_id']]);
$prof = $stmt_prof->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($prof['prenom'], 0, 1) . substr($prof['nom'], 0, 1));

$etudiants = $db->query("SELECT id, prenom, nom FROM utilisateurs WHERE role = 'etudiant' ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$cours = $db->query("SELECT id, titre FROM cours ORDER BY titre")->fetchAll(PDO::FETCH_ASSOC);

$toutes_les_notes = $db->query("SELECT u.nom, u.prenom, c.titre, n.valeur_note, n.commentaire FROM notes n JOIN utilisateurs u ON n.etudiant_id = u.id JOIN cours c ON n.cours_id = c.id ORDER BY n.id DESC")->fetchAll(PDO::FETCH_ASSOC);
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
        <img src="images/logo.jpg" alt="Logo HEJ">
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
        <a href="parametres.php">Paramètres</a>
        <a href="deconnexion.php">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1 style="margin:0; color:var(--primary);">Saisie & Suivi Pédagogique</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Attribuez les notes et mentions de la Haute École.</p>
        </div>

        <div class="dashboard-grid inverse">
            <div class="card">
                <div class="card-header"><h2>Nouvelle Évaluation</h2></div>
                <form action="traitement_note.php" method="POST">
                    <label>Étudiant</label>
                    <select name="etudiant_id" required>
                        <option value="">-- Sélectionner l'élève --</option>
                        <?php foreach ($etudiants as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Matière</label>
                    <select name="cours_id" required>
                        <option value="">-- Sélectionner le cours --</option>
                        <?php foreach ($cours as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['titre']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Note (sur 20)</label>
                    <input type="number" name="valeur_note" step="0.25" min="0" max="20" required placeholder="Ex: 15.5">
                    
                    <label>Appréciation pédagogique</label>
                    <textarea name="commentaire" rows="3" placeholder="Commentaire sur le travail rendu..."></textarea>
                    
                    <button type="submit" class="btn-action" style="width: 100%; margin-top: 20px;">Enregistrer la Note</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><h2>Notes Récemment Atbribuées</h2></div>
                <table>
                    <tr><th>Élève</th><th>Matière</th><th>Note</th><th>Appréciation</th></tr>
                    <?php foreach ($toutes_les_notes as $n): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($n['nom'] . ' ' . $n['prenom']) ?></strong></td>
                            <td><?= htmlspecialchars($n['titre']) ?></td>
                            <td><span class="badge badge-neutral"><?= htmlspecialchars($n['valeur_note']) ?></span></td>
                            <td style="color: var(--text-muted); font-style: italic; font-size:13px;"><?= !empty($n['commentaire']) ? htmlspecialchars($n['commentaire']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
</body>
</html>