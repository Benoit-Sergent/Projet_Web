<?php
session_start();
// Barrière de sécurité
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

// --- LOGIQUE DE TRAITEMENT ---

// 1. Ajout d'un cours (Formulaire POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $titre = trim($_POST['titre']);
    $categorie = trim($_POST['categorie']);
    $description = trim($_POST['description']);
    
    if (!empty($titre) && !empty($categorie)) {
        $stmt = $db->prepare("INSERT INTO cours (titre, description, categorie) VALUES (?, ?, ?)");
        $stmt->execute([$titre, $description, $categorie]);
        header("Location: gestion_cours.php?succes=ajout");
        exit();
    }
}

// 2. Suppression d'un cours (Lien GET)
if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && isset($_GET['id'])) {
    $id_cours = $_GET['id'];
    
    // On supprime d'abord les notes liées à ce cours pour éviter les erreurs de clés étrangères
    $stmt_notes = $db->prepare("DELETE FROM notes WHERE cours_id = ?");
    $stmt_notes->execute([$id_cours]);
    
    // Puis on supprime le cours
    $stmt_cours = $db->prepare("DELETE FROM cours WHERE id = ?");
    $stmt_cours->execute([$id_cours]);
    
    header("Location: gestion_cours.php?succes=suppression");
    exit();
}

// --- RÉCUPÉRATION DES DONNÉES ---

// Infos de l'admin pour le menu
$stmt_admin = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_admin->execute([$_SESSION['utilisateur_id']]);
$admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1));

// Liste des cours
$liste_cours = $db->query("SELECT * FROM cours ORDER BY categorie, titre")->fetchAll(PDO::FETCH_ASSOC);
$nb_cours = count($liste_cours);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Cours - SmartCampus</title>
    <style>
        /* Mêmes variables et styles globaux que le dashboard */
        :root {
            --primary: #C5A059;
            --primary-light: #fcf8f2;
            --bg-body: #f4f7f6;
            --sidebar-bg: #ffffff;
            --text-main: #202124;
            --text-muted: #5f6368;
            --border: #e8eaed;
            --danger: #d93025;
            --danger-light: #fce8e6;
        }

        body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-body); margin: 0; display: flex; height: 100vh; overflow: hidden; color: var(--text-main); }

        /* Sidebar */
        .sidebar { width: 260px; background-color: var(--sidebar-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 20px 0; }
        .sidebar .logo-container { padding: 0 20px 20px; border-bottom: 1px solid var(--border); text-align: center; }
        .sidebar .logo-container img { max-width: 120px; }
        .sidebar nav { flex-grow: 1; padding-top: 20px; }
        .sidebar nav a { display: flex; align-items: center; padding: 12px 24px; color: var(--text-muted); text-decoration: none; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .sidebar nav a:hover { background-color: #f8f9fa; color: var(--primary); }
        .sidebar nav a.active { background-color: var(--primary-light); color: var(--primary); border-left: 4px solid var(--primary); }
        .user-widget { padding: 20px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .user-widget .avatar-small { width: 40px; height: 40px; border-radius: 50%; background: #333; color: white; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 14px; }
        .user-widget-info { font-size: 14px; }
        .user-widget-info span { display: block; color: var(--text-muted); font-size: 12px; }

        /* Contenu principal */
        .main-content { flex-grow: 1; padding: 30px 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .btn-action { background-color: var(--primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: 0.3s; }
        .btn-action:hover { background-color: #a68444; }

        /* Grilles et Cartes */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .card { background: white; border-radius: 12px; padding: 24px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .card-header { border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
        .card-header h2 { margin: 0; font-size: 18px; color: var(--text-main); }

        /* Tableaux */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--border); font-size: 14px; }
        th { color: var(--text-muted); font-weight: 500; }
        .badge-cat { background: #e8f0fe; color: #1a73e8; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .btn-delete { color: var(--danger); text-decoration: none; font-weight: 600; font-size: 13px; padding: 4px 8px; border-radius: 4px; transition: 0.2s; }
        .btn-delete:hover { background-color: var(--danger-light); }

        /* Formulaire */
        input, select, textarea { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 14px; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary); }
        label { font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; display: block; }
        
        .alert { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; font-weight: bold; }
        .alert-success { background-color: #e6f4ea; color: #137333; border: 1px solid #137333; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo-container">
            <img src="images/logo.jpg" alt="Logo HEJ" onerror="this.src='https://via.placeholder.com/120x50?text=SmartCampus'">
        </div>
        
        <nav>
            <a href="dashboard_administrateur.php">👥 Gestion des utilisateurs</a>
            <a href="gestion_cours.php" class="active">📚 Gestion des cours</a>
            <a href="#">⚙️ Paramètres système</a>
        </nav>

        <div class="user-widget">
            <div class="avatar-small"><?= $initiales ?></div>
            <div class="user-widget-info">
                <strong><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></strong>
                <span>Administrateur</span>
            </div>
        </div>
    </aside>

    <main class="main-content">
        
        <header class="header">
            <div>
                <h1>Programme Académique</h1>
                <span style="color: var(--text-muted); font-size: 14px;">Gérez les matières enseignées sur le campus.</span>
            </div>
            <a href="deconnexion.php" class="btn-action" style="background-color: var(--text-main);">Se déconnecter</a>
        </header>

        <?php if (isset($_GET['succes'])): ?>
            <div class="alert alert-success">
                <?php 
                if ($_GET['succes'] === 'ajout') echo "✅ Le cours a été ajouté au programme avec succès.";
                if ($_GET['succes'] === 'suppression') echo "🗑️ Le cours (et ses notes associées) ont été supprimés.";
                ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Liste des matières (<?= $nb_cours ?>)</h2>
                </div>
                <table>
                    <tr>
                        <th>Catégorie</th>
                        <th>Titre de la matière</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($liste_cours as $c): ?>
                        <tr>
                            <td><span class="badge-cat"><?= htmlspecialchars($c['categorie']) ?></span></td>
                            <td><strong><?= htmlspecialchars($c['titre']) ?></strong></td>
                            <td style="color: var(--text-muted); font-size: 13px;">
                                <?= htmlspecialchars(strlen($c['description']) > 40 ? substr($c['description'], 0, 40) . '...' : $c['description']) ?>
                            </td>
                            <td>
                                <a href="gestion_cours.php?action=supprimer&id=<?= $c['id'] ?>" 
                                   class="btn-delete"
                                   onclick="return confirm('Attention : supprimer ce cours effacera aussi TOUTES les notes attribuées aux étudiants pour cette matière. Confirmer ?');">
                                   Supprimer
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Ajouter une matière</h2>
                </div>
                <form action="gestion_cours.php" method="POST">
                    <input type="hidden" name="action" value="ajouter">
                    
                    <label>Titre de la matière</label>
                    <input type="text" name="titre" placeholder="Ex: Histoire de l'art" required>
                    
                    <label>Catégorie (Module)</label>
                    <input type="text" name="categorie" placeholder="Ex: Tronc Commun" required list="categories-list">
                    
                    <datalist id="categories-list">
                        <?php 
                        $categories = array_unique(array_column($liste_cours, 'categorie'));
                        foreach ($categories as $cat) {
                            echo '<option value="' . htmlspecialchars($cat) . '">';
                        }
                        ?>
                    </datalist>

                    <label>Description (Optionnelle)</label>
                    <textarea name="description" rows="3" placeholder="Description brève du cours..."></textarea>
                    
                    <button type="submit" class="btn-action" style="width: 100%; margin-top: 10px;">Enregistrer la matière</button>
                </form>
            </div>

        </div>
    </main>

</body>
</html>