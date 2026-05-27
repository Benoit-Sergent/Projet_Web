<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$prof_id = $_SESSION['utilisateur_id'];

$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$prof_id]);
$messages_non_lus = $stmt_unread->fetchColumn();

$prof_info = $db->query("SELECT prenom, nom FROM utilisateurs WHERE id = $prof_id")->fetch();
$initiales = strtoupper(substr($prof_info['prenom'], 0, 1) . substr($prof_info['nom'], 0, 1));

// 1. Cours attribués à ce professeur
$stmt_c = $db->prepare("SELECT c.*, g.nom as nom_groupe FROM cours c JOIN groupes g ON c.groupe_id = g.id WHERE c.professeur_id = ?");
$stmt_c->execute([$prof_id]);
$mes_cours = $stmt_c->fetchAll();

// 2. Étudiants concernés (inscrits dans les classes de ce professeur)
$stmt_e = $db->prepare("SELECT id, nom, prenom, groupe_id FROM utilisateurs WHERE role = 'etudiant' AND groupe_id IN (SELECT groupe_id FROM cours WHERE professeur_id = ?)");
$stmt_e->execute([$prof_id]);
$mes_etudiants = $stmt_e->fetchAll();

// 3. Historique des notes saisies
$stmt_h = $db->prepare("SELECT n.*, u.nom, u.prenom, c.titre FROM notes n JOIN utilisateurs u ON n.etudiant_id = u.id JOIN cours c ON n.cours_id = c.id WHERE c.professeur_id = ? ORDER BY n.id DESC");
$stmt_h->execute([$prof_id]);
$historique_notes = $stmt_h->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Enseignant - SmartCampus</title><link rel="stylesheet" href="style.css"></head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;"><strong><?= htmlspecialchars($prof_info['prenom'].' '.$prof_info['nom']) ?></strong><span>Professeur</span></div>
            <div class="avatar-small"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_professeur.php" class="active">Évaluations</a>
        <a href="faire_appel.php">Faire l'appel</a>
        <a href="messagerie.php">Messagerie 💬<?php if ($messages_non_lus > 0): ?><span class="notification-badge"><?= $messages_non_lus ?></span><?php endif; ?></a>
        <a href="profil.php">Profil</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        <h1>Saisie & Historique des Notes</h1>
        <div class="dashboard-grid inverse">
            <div class="card">
                <div class="card-header"><h2>Attribuer une Note</h2></div>
                <form action="traitement_note.php" method="POST">
                    <label>Matière & Groupe</label>
                    <select name="cours_id" id="coursSelect" required onchange="filtrerEleves()">
                        <option value="">-- Choisir le cours --</option>
                        <?php foreach($mes_cours as $c): ?><option value="<?= $c['id'] ?>" data-groupe="<?= $c['groupe_id'] ?>"><?= htmlspecialchars($c['titre'].' ('.$c['nom_groupe'].')') ?></option><?php endforeach; ?>
                    </select>

                    <label>Étudiant</label>
                    <select name="etudiant_id" id="etudiantSelect" required>
                        <option value="">-- Choisir d'abord un cours --</option>
                        <?php foreach($mes_etudiants as $e): ?><option value="<?= $e['id'] ?>" data-groupe="<?= $e['groupe_id'] ?>" style="display:none;"><?= htmlspecialchars($e['nom'].' '.$e['prenom']) ?></option><?php endforeach; ?>
                    </select>

                    <label>Note / 20</label><input type="number" name="valeur_note" min="0" max="20" step="0.25" required>
                    <label>Appréciation</label><textarea name="commentaire" rows="2"></textarea>
                    <button type="submit" class="btn-action" style="width:100%;margin-top:15px;">Enregistrer la note</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><h2>Notes publiées</h2></div>
                <table>
                    <tr><th>Élève</th><th>Matière</th><th>Note</th></tr>
                    <?php foreach($historique_notes as $n): ?>
                        <tr><td><strong><?= htmlspecialchars($n['nom'].' '.$n['prenom']) ?></strong></td><td><?= htmlspecialchars($n['titre']) ?></td><td><span class="badge badge-neutral"><?= $n['valeur_note'] ?> / 20</span></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <script>
        function filtrerEleves() {
            var selectedCours = document.getElementById('coursSelect').options[document.getElementById('coursSelect').selectedIndex];
            var gId = selectedCours.getAttribute('data-groupe');
            var eSelect = document.getElementById('etudiantSelect');
            
            eSelect.value = "";
            eSelect.options[0].text = gId ? "-- Sélectionner l'étudiant --" : "-- Choisir d'abord un cours --";

            for(var i=1; i<eSelect.options.length; i++) {
                var opt = eSelect.options[i];
                opt.style.display = (opt.getAttribute('data-groupe') === gId) ? 'block' : 'none';
            }
        }
    </script>
</body>
</html>