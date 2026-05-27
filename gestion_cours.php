<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$message_succes = "";

// 1. ACTION : Ajouter un cours avec son créneau horaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $titre = trim($_POST['titre']); 
    $categorie = trim($_POST['categorie']); 
    $description = trim($_POST['description']);
    $groupe_id = !empty($_POST['groupe_id']) ? intval($_POST['groupe_id']) : null;
    $professeur_id = !empty($_POST['professeur_id']) ? intval($_POST['professeur_id']) : null;
    
    // Informations du créneau
    $jour_semaine = intval($_POST['jour_semaine']);
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $salle = trim($_POST['salle']);

    if (!empty($titre) && !empty($categorie) && !empty($groupe_id)) {
        // Insertion du cours
        $stmt = $db->prepare("INSERT INTO cours (titre, description, categorie, groupe_id, professeur_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$titre, $description, $categorie, $groupe_id, $professeur_id]);
        $cours_id = $db->lastInsertId();
        
        // Insertion du créneau horaire associé
        if (!empty($heure_debut) && !empty($heure_fin) && !empty($salle)) {
            $stmt_creneau = $db->prepare("INSERT INTO cours_creneaux (cours_id, jour_semaine, heure_debut, heure_fin, salle) VALUES (?, ?, ?, ?, ?)");
            $stmt_creneau->execute([$cours_id, $jour_semaine, $heure_debut, $heure_fin, $salle]);
        }
        
        header("Location: gestion_cours.php?succes=ajout"); 
        exit();
    }
}

// 2. ACTION : Supprimer un cours
if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Grâce aux contraintes "ON DELETE CASCADE" définies dans db.php, 
    // la suppression du cours nettoiera automatiquement les créneaux, les notes et les présences liés !
    $db->prepare("DELETE FROM cours WHERE id = ?")->execute([$id]);
    header("Location: gestion_cours.php?succes=suppression"); 
    exit();
}

// Infos Admin connecté
$stmt_admin = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_admin->execute([$_SESSION['utilisateur_id']]);
$admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1));

// Récupération des groupes et des professeurs pour les listes déroulantes
$groupes = $db->query("SELECT * FROM groupes ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$professeurs = $db->query("SELECT id, prenom, nom FROM utilisateurs WHERE role = 'professeur' AND statut_compte = 'actif' ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Liste complète des cours planifiés avec les jointures nécessaires
$liste_cours = $db->query("
    SELECT c.id, c.titre, c.categorie, g.nom as nom_groupe, u.nom as prof_nom, u.prenom as prof_prenom,
           cc.jour_semaine, cc.heure_debut, cc.heure_fin, cc.salle
    FROM cours c
    LEFT JOIN groupes g ON c.groupe_id = g.id
    LEFT JOIN utilisateurs u ON c.professeur_id = u.id
    LEFT JOIN cours_creneaux cc ON c.id = cc.cours_id
    ORDER BY g.nom, cc.jour_semaine, cc.heure_debut
")->fetchAll(PDO::FETCH_ASSOC);

$jours_label = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Programme & Emploi du temps - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo SmartCampus" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></strong>
                <span>Administrateur</span>
            </div>
            <div class="avatar-small" style="background:#2b2b2b;"><?= $initiales ?></div>
        </div>
    </header>

<nav class="top-nav">
        <a href="dashboard_administrateur.php" <?= basename($_SERVER['PHP_SELF']) == 'dashboard_administrateur.php' ? 'class="active"' : '' ?>>Membres & Classes</a>
        <a href="gestion_cours.php" <?= basename($_SERVER['PHP_SELF']) == 'gestion_cours.php' ? 'class="active"' : '' ?>>Programme</a>
        <a href="gestion_absences.php" <?= basename($_SERVER['PHP_SELF']) == 'gestion_absences.php' ? 'class="active"' : '' ?>>Scolarité (Absences)</a>
        <a href="parametres.php">Paramètres</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1 style="margin:0; color:var(--primary);">Gestion du Programme & Planning</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Planifiez les matières, affectez les classes et les enseignants.</p>
        </div>

        <?php if (isset($_GET['succes'])): ?>
            <div class="alert alert-success">
                <?= $_GET['succes'] === 'ajout' ? "✅ Cours et créneau horaire enregistrés avec succès." : "🗑️ Cours et toutes les données associées supprimés." ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header"><h2>Matières & Séances Planifiées (<?= count($liste_cours) ?>)</h2></div>
                <table>
                    <tr><th>Classe</th><th>Matière</th><th>Enseignant</th><th>Créneau / Salle</th><th>Action</th></tr>
                    <?php foreach ($liste_cours as $c): ?>
                        <tr>
                            <td><?= $c['nom_groupe'] ? '<span class="badge badge-neutral">'.htmlspecialchars($c['nom_groupe']).'</span>' : '<em>Aucune</em>' ?></td>
                            <td>
                                <strong><?= htmlspecialchars($c['titre']) ?></strong><br>
                                <span style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($c['categorie']) ?></span>
                            </td>
                            <td>👨‍🏫 <?= $c['prof_nom'] ? htmlspecialchars($c['prof_nom'] . ' ' . $c['prof_prenom']) : '<em>Non assigné</em>' ?></td>
                            <td style="font-size:13px;">
                                <?php if ($c['jour_semaine']): ?>
                                    <strong><?= $jours_label[$c['jour_semaine']] ?></strong><br>
                                    ⏱️ <?= date('H:i', strtotime($c['heure_debut'])) ?> - <?= date('H:i', strtotime($c['heure_fin'])) ?><br>
                                    📍 <?= htmlspecialchars($c['salle']) ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-style:italic;">Aucun créneau</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="gestion_cours.php?action=supprimer&id=<?= $c['id'] ?>" style="color:var(--danger); font-weight:600; text-decoration:none; font-size:13px;" onclick="return confirm('Attention ! Supprimer ce cours supprimera définitivement ses créneaux, ses notes et ses présences. Continuer ?');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="card">
                <div class="card-header"><h2>Créer et Planifier un Cours</h2></div>
                <form action="gestion_cours.php" method="POST">
                    <input type="hidden" name="action" value="ajouter">
                    
                    <label>Intitulé du cours</label>
                    <input type="text" name="titre" placeholder="Ex: Gemmologie Avancée" required>
                    
                    <label>Module / Catégorie</label>
                    <input type="text" name="categorie" placeholder="Ex: Enseignements complémentaires" required>
                    
                    <label>Classe / Groupe ciblé</label>
                    <select name="groupe_id" required>
                        <option value="">-- Sélectionner la classe --</option>
                        <?php foreach ($groupes as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Enseignant responsable</label>
                    <select name="professeur_id">
                        <option value="">-- Sélectionner le professeur (Optionnel) --</option>
                        <?php foreach ($professeurs as $p): ?>
                            <option value="<?= $p['id'] ?>">M./Mme <?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div style="border-top: 1px solid var(--border); margin-top:20px; padding-top:15px;">
                        <h3 style="font-size:14px; margin:0 0 10px 0; color:var(--primary); font-family:'Inter', sans-serif;">Planification horaire</h3>
                        
                        <label>Jour de la semaine</label>
                        <select name="jour_semaine" required>
                            <?php foreach ($jours_label as $num => $nom): ?>
                                <option value="<?= $num ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label>Heure de début</label>
                                <input type="time" name="heure_debut" required>
                            </div>
                            <div>
                                <label>Heure de fin</label>
                                <input type="time" name="heure_fin" required>
                            </div>
                        </div>

                        <label>Salle de cours</label>
                        <input type="text" name="salle" placeholder="Ex: Salle 204, Amphi B" required>
                    </div>

                    <label>Description du cours</label>
                    <textarea name="description" rows="2" placeholder="Objectifs ou détails du cours..."></textarea>
                    
                    <button type="submit" class="btn-action" style="width:100%; margin-top:20px;">Ouvrir et Planifier</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>