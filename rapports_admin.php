<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: connexion.php"); exit();
}
require_once 'db.php';

// Calcul de la moyenne par cours
$stats_cours = $db->query("
    SELECT cours.titre, cours.categorie, AVG(notes.valeur_note) as moyenne_cours, COUNT(notes.id) as nb_notes
    FROM cours
    LEFT JOIN notes ON cours.id = notes.cours_id
    GROUP BY cours.id
    ORDER BY moyenne_cours DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Infos de l'admin
$stmt_admin = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_admin->execute([$_SESSION['utilisateur_id']]);
$admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statistiques - SmartCampus</title>
    <style>
        /* Réutiliser le CSS de gestion_cours.php pour la cohérence */
        :root { --primary: #C5A059; --bg-body: #f4f7f6; --sidebar-bg: #ffffff; --text-main: #202124; --text-muted: #5f6368; --border: #e8eaed; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-body); margin: 0; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 20px 0; }
        .sidebar nav a { display: flex; align-items: center; padding: 12px 24px; color: var(--text-muted); text-decoration: none; font-weight: 500; }
        .sidebar nav a.active { background-color: #fcf8f2; color: var(--primary); border-left: 4px solid var(--primary); }
        .main-content { flex-grow: 1; padding: 30px 40px; overflow-y: auto; }
        .card { background: white; border-radius: 12px; padding: 24px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border); }
        .moyenne-badge { font-weight: bold; padding: 5px 10px; border-radius: 20px; background: #eee; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo-container" style="text-align:center;"><img src="images/logo.jpg" style="max-width:120px;"></div>
        <nav>
            <a href="dashboard_administrateur.php">👥 Gestion des utilisateurs</a>
            <a href="gestion_cours.php">📚 Gestion des cours</a>
            <a href="rapports_admin.php" class="active">📈 Statistiques Globales</a>
            <a href="parametres.php">⚙️ Paramètres</a>
        </nav>
    </aside>
    <main class="main-content">
        <h1>Analyse des Performances</h1>
        <div class="card">
            <h2>Moyennes par Matière</h2>
            <table>
                <tr><th>Matière</th><th>Catégorie</th><th>Nombre de notes</th><th>Moyenne</th></tr>
                <?php foreach ($stats_cours as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['titre']) ?></strong></td>
                    <td><?= htmlspecialchars($s['categorie']) ?></td>
                    <td><?= $s['nb_notes'] ?> éval.</td>
                    <td><span class="moyenne-badge" style="color: <?= $s['moyenne_cours'] >= 10 ? '#137333' : '#d93025' ?>;">
                        <?= $s['moyenne_cours'] ? number_format($s['moyenne_cours'], 2) : 'N/A' ?> / 20
                    </span></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </main>
</body>
</html>