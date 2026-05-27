<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$_SESSION['utilisateur_id']]);
$messages_non_lus = $stmt_unread->fetchColumn();

// Traitement de l'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $titre = trim($_POST['titre']); $categorie = trim($_POST['categorie']); $description = trim($_POST['description']);
    $groupe_id = intval($_POST['groupe_id']); $professeur_id = !empty($_POST['professeur_id']) ? intval($_POST['professeur_id']) : null;
    
    $jour = intval($_POST['jour_semaine']); $debut = $_POST['heure_debut']; $fin = $_POST['heure_fin']; $salle = trim($_POST['salle']);

    if (!empty($titre) && !empty($groupe_id)) {
        $stmt = $db->prepare("INSERT INTO cours (titre, categorie, description, groupe_id, professeur_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$titre, $categorie, $description, $groupe_id, $professeur_id]);
        $cours_id = $db->lastInsertId();

        $stmt_cc = $db->prepare("INSERT INTO cours_creneaux (cours_id, jour_semaine, heure_debut, heure_fin, salle) VALUES (?, ?, ?, ?, ?)");
        $stmt_cc->execute([$cours_id, $jour, $debut, $fin, $salle]);
        header("Location: gestion_cours.php"); exit();
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && isset($_GET['id'])) {
    $db->prepare("DELETE FROM cours WHERE id = ?")->execute([$_GET['id']]);
    header("Location: gestion_cours.php"); exit();
}

$groupes = $db->query("SELECT * FROM groupes ORDER BY nom")->fetchAll();
$professeurs = $db->query("SELECT id, nom, prenom FROM utilisateurs WHERE role = 'professeur' ORDER BY nom")->fetchAll();
$liste_cours = $db->query("
    SELECT c.*, g.nom as nom_groupe, p.nom as prof_nom, cc.jour_semaine, cc.heure_debut, cc.heure_fin, cc.salle
    FROM cours c
    LEFT JOIN groupes g ON c.groupe_id = g.id
    LEFT JOIN utilisateurs p ON c.professeur_id = p.id
    LEFT JOIN cours_creneaux cc ON c.id = cc.cours_id
    ORDER BY g.nom, cc.jour_semaine
")->fetchAll();

$jours = [1=>'Lundi', 2=>'Mardi', 3=>'Mercredi', 4=>'Jeudi', 5=>'Vendredi'];
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Programme - SmartCampus</title><link rel="stylesheet" href="style.css"></head>
<body>
    <nav class="top-nav">
        <a href="dashboard_administrateur.php">Membres & Classes</a>
        <a href="gestion_cours.php" class="active">Programme</a>
        <a href="gestion_absences.php">Scolarité (Absences)</a>
        <a href="messages.php" style="display:none;">Fix</a>
        <a href="messagerie.php">Messagerie 💬<?php if ($messages_non_lus > 0): ?><span class="notification-badge"><?= $messages_non_lus ?></span><?php endif; ?></a>
		<a href="rapports_admin.php">📊 Rapports</a>
        <a href="profil.php">Profil</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>
	

    <div class="container" style="margin-top:30px;">
        <h1>Maquette Pédagogique & Emplois du Temps</h1>
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header"><h2>Planning Général</h2></div>
                <table>
                    <tr><th>Classe</th><th>Matière</th><th>Professeur</th><th>Horaire / Salle</th><th>Action</th></tr>
                    <?php foreach($liste_cours as $c): ?>
                        <tr>
                            <td><span class="badge badge-neutral"><?= htmlspecialchars($c['nom_groupe']) ?></span></td>
                            <td><strong><?= htmlspecialchars($c['titre']) ?></strong></td>
                            <td><?= $c['prof_nom'] ? 'M. '.htmlspecialchars($c['prof_nom']) : 'Non assigné' ?></td>
                            <td style="font-size:12px;"><strong><?= $jours[$c['jour_semaine']] ?? '' ?></strong><br><?= $c['heure_debut'] ?> - <?= $c['heure_fin'] ?><br>📍 Salle <?= htmlspecialchars($c['salle']) ?></td>
                            <td><a href="gestion_cours.php?action=supprimer&id=<?= $c['id'] ?>" style="color:var(--danger);font-weight:600;text-decoration:none;" onclick="return confirm('Supprimer ce cours ?');">Supprimer</a></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="card">
                <div class="card-header"><h2>Ouvrir & Planifier un Cours</h2></div>
                <form method="POST">
                    <input type="hidden" name="action" value="ajouter">
                    <label>Intitulé du cours</label><input type="text" name="titre" required>
                    <label>Catégorie</label><input type="text" name="categorie" required placeholder="Ex: Informatique">
                    <label>Classe ciblée</label>
                    <select name="groupe_id" required>
                        <option value="">-- Choisir la classe --</option>
                        <?php foreach($groupes as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nom']) ?></option><?php endforeach; ?>
                    </select>
                    <label>Enseignant responsable</label>
                    <select name="professeur_id">
                        <option value="">-- Choisir le professeur --</option>
                        <?php foreach($professeurs as $p): ?><option value="<?= $p['id'] ?>">M. <?= htmlspecialchars($p['nom'].' '.$p['prenom']) ?></option><?php endforeach; ?>
                    </select>

                    <h3 style="font-size:14px;color:var(--primary);margin-top:15px;">Planification Horaire</h3>
                    <label>Jour</label>
                    <select name="jour_semaine" required>
                        <?php foreach($jours as $n=>$j): ?><option value="<?= $n ?>"><?= $j ?></option><?php endforeach; ?>
                    </select>
                    <div style="display:flex;gap:10px;"><input type="time" name="heure_debut" required><input type="time" name="heure_fin" required></div>
                    <label>Salle</label><input type="text" name="salle" required placeholder="Ex: Amphi 3">
                    <label>Description</label><textarea name="description" rows="2"></textarea>
                    <button type="submit" class="btn-action" style="width:100%;margin-top:15px;">Valider le cours</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>