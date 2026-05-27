<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$message_succes = ""; $message_erreur = "";

// ==========================================
// ACTION : AJOUTER UN COURS AU PLANNING
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_cours') {
    $titre = trim($_POST['titre']);
    $categorie = trim($_POST['categorie']);
    $prof_id = intval($_POST['professeur_id']);
    $groupe_id = intval($_POST['groupe_id']);
    $jour = $_POST['jour'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $salle = trim($_POST['salle']);

    if (!empty($titre) && $prof_id > 0 && $groupe_id > 0) {
        $stmt = $db->prepare("
            INSERT INTO cours (titre, categorie, professeur_id, groupe_id, jour, heure_debut, heure_fin, salle) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt->execute([$titre, $categorie, $prof_id, $groupe_id, $jour, $heure_debut, $heure_fin, $salle])) {
            $message_succes = "Le cours a été planifié avec succès.";
        } else {
            $message_erreur = "Erreur lors de la création du cours.";
        }
    } else {
        $message_erreur = "Veuillez remplir tous les champs obligatoires.";
    }
}

// Récupération des données pour les formulaires
$professeurs = $db->query("SELECT id, nom, prenom FROM utilisateurs WHERE role = 'professeur' ORDER BY nom")->fetchAll();
$groupes = $db->query("SELECT * FROM groupes ORDER BY nom")->fetchAll();
$cours = $db->query("
    SELECT c.*, u.nom as prof_nom, g.nom as groupe_nom 
    FROM cours c 
    LEFT JOIN utilisateurs u ON c.professeur_id = u.id 
    LEFT JOIN groupes g ON c.groupe_id = g.id 
    ORDER BY c.jour, c.heure_debut
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><title>Programme - SmartCampus</title><link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="color:var(--primary);">Ingénierie Pédagogique</h1>
            <p style="color:var(--text-muted); margin:0;">Créez les cours et assignez-les aux créneaux de l'emploi du temps.</p>
        </div>

        <?php if ($message_succes): ?><div class="alert alert-success">✅ <?= $message_succes ?></div><?php endif; ?>
        <?php if ($message_erreur): ?><div class="alert alert-error">⚠️ <?= $message_erreur ?></div><?php endif; ?>

        <div class="dashboard-grid inverse">
            
            <div class="card" style="align-self: start;">
                <div class="card-header"><h2>Planifier un nouvel enseignement</h2></div>
                <form method="POST">
                    <input type="hidden" name="action" value="ajouter_cours">
                    
                    <label>Titre du cours</label>
                    <input type="text" name="titre" required placeholder="Ex: Mathématiques Avancées">
                    
                    <label>Catégorie (Module)</label>
                    <select name="categorie" required>
                        <option value="Sciences">Sciences & Ingénierie</option>
                        <option value="Informatique">Informatique & Tech</option>
                        <option value="Langues">Langues & Culture</option>
                        <option value="Management">Management & Droit</option>
                    </select>

                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;">
                            <label>Professeur</label>
                            <select name="professeur_id" required>
                                <option value="">-- Assigner --</option>
                                <?php foreach($professeurs as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom'].' '.$p['prenom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label>Classe (Groupe)</label>
                            <select name="groupe_id" required>
                                <option value="">-- Assigner --</option>
                                <?php foreach($groupes as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border); margin: 15px 0; padding-top: 15px;">
                        <label style="color:var(--primary); font-size:14px; margin-bottom:10px;">🕒 Horaires & Salle</label>
                    </div>

                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;">
                            <label>Jour de la semaine</label>
                            <select name="jour" required>
                                <option value="Lundi">Lundi</option><option value="Mardi">Mardi</option>
                                <option value="Mercredi">Mercredi</option><option value="Jeudi">Jeudi</option>
                                <option value="Vendredi">Vendredi</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label>Salle</label>
                            <input type="text" name="salle" required placeholder="Ex: Amphi A, Salle 302">
                        </div>
                    </div>

                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;"><label>Heure Début</label><input type="time" name="heure_debut" required value="08:30"></div>
                        <div style="flex:1;"><label>Heure Fin</label><input type="time" name="heure_fin" required value="10:30"></div>
                    </div>

                    <button type="submit" class="btn-action" style="width:100%; margin-top:15px;">Inscrire au planning</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><h2>Maquette Pédagogique Globale</h2></div>
                <?php if (empty($cours)): ?>
                    <p style="color:var(--text-muted); text-align:center;">Aucun cours programmé.</p>
                <?php else: ?>
                    <table>
                        <tr><th>Enseignement</th><th>Créneau</th><th>Salle</th><th>Professeur</th><th>Classe</th></tr>
                        <?php foreach($cours as $c): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($c['titre']) ?></strong><br>
                                    <span style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($c['categorie']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-neutral"><?= htmlspecialchars($c['jour']) ?></span><br>
                                    <span style="font-size:12px; color:var(--primary); font-weight:600;"><?= $c['heure_debut'] ?> - <?= $c['heure_fin'] ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars($c['salle']) ?></strong></td>
                                <td><?= htmlspecialchars($c['prof_nom']) ?></td>
                                <td><span style="color:var(--primary); font-weight:600;"><?= htmlspecialchars($c['groupe_nom']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
</html>