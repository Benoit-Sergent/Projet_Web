<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $titre = trim($_POST['titre']); $categorie = trim($_POST['categorie']); $description = trim($_POST['description']);
    if (!empty($titre) && !empty($categorie)) {
        $stmt = $db->prepare("INSERT INTO cours (titre, description, categorie) VALUES (?, ?, ?)");
        $stmt->execute([$titre, $description, $categorie]);
        header("Location: gestion_cours.php?succes=ajout"); exit();
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && isset($_GET['id'])) {
    $db->prepare("DELETE FROM notes WHERE cours_id = ?")->execute([$_GET['id']]);
    $db->prepare("DELETE FROM cours WHERE id = ?")->execute([$_GET['id']]);
    header("Location: gestion_cours.php?succes=suppression"); exit();
}

$stmt_admin = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_admin->execute([$_SESSION['utilisateur_id']]);
$admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1));

$liste_cours = $db->query("SELECT * FROM cours ORDER BY categorie, titre")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Programme - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo HEJ">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></strong>
                <span>Administrateur</span>
            </div>
            <div class="avatar-small" style="background:#2b2b2b;"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_administrateur.php">Membres</a>
        <a href="gestion_cours.php" class="active">Programme</a>
        <a href="parametres.php">Paramètres</a>
        <a href="deconnexion.php">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1 style="margin:0; color:var(--primary);">Gestion du Programme</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Ajoutez ou retirez des matières de la maquette pédagogique.</p>
        </div>

        <?php if (isset($_GET['succes'])): ?>
            <div class="alert alert-success">
                <?= $_GET['succes'] === 'ajout' ? "✅ Matière enregistrée avec succès." : "🗑️ Cours et notes supprimés." ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header"><h2>Matières Ouvertes (<?= count($liste_cours) ?>)</h2></div>
                <table>
                    <tr><th>Module</th><th>Matière</th><th>Action</th></tr>
                    <?php foreach ($liste_cours as $c): ?>
                        <tr>
                            <td><span class="badge badge-neutral"><?= htmlspecialchars($c['categorie']) ?></span></td>
                            <td><strong><?= htmlspecialchars($c['titre']) ?></strong></td>
                            <td><a href="gestion_cours.php?action=supprimer&id=<?= $c['id'] ?>" style="color:var(--danger); font-weight:600; text-decoration:none; font-size:13px;" onclick="return confirm('Supprimer ce cours supprimera TOUTES ses notes liées. Continuer ?');">Supprimer</a></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="card">
                <div class="card-header"><h2>Créer une Matière</h2></div>
                <form action="gestion_cours.php" method="POST">
                    <input type="hidden" name="action" value="ajouter">
                    <label>Intitulé du cours</label>
                    <input type="text" name="titre" placeholder="Ex: Design de Haute Joaillerie" required>
                    <label>Module / Catégorie</label>
                    <input type="text" name="categorie" placeholder="Ex: Atelier pratique" required list="cats">
                    <datalist id="cats">
                        <?php foreach (array_unique(array_column($liste_cours, 'categorie')) as $cat) echo "<option value='".htmlspecialchars($cat)."'>"; ?>
                    </datalist>
                    <label>Description du cours</label>
                    <textarea name="description" rows="3"></textarea>
                    <button type="submit" class="btn-action" style="width:100%; margin-top:20px;">Ouvrir le cours</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>