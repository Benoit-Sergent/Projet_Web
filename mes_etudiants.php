<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') {
    header("Location: connexion.php"); exit();
}
require_once 'db.php';

// Récupération de tous les étudiants avec leur moyenne calculée
$liste_eleves = $db->query("
    SELECT u.id, u.nom, u.prenom, u.email, AVG(n.valeur_note) as moyenne_generale
    FROM utilisateurs u
    LEFT JOIN notes n ON u.id = n.etudiant_id
    WHERE u.role = 'etudiant'
    GROUP BY u.id
    ORDER BY u.nom ASC
")->fetchAll(PDO::FETCH_ASSOC);

$initiales = "PR"; // Exemple
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Étudiants - SmartCampus</title>
    <style>
        /* Style identique pour la cohérence */
        :root { --primary: #C5A059; --bg-body: #f4f7f6; --sidebar-bg: #ffffff; --text-muted: #5f6368; --border: #e8eaed; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-body); margin: 0; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 20px 0; }
        .sidebar nav a { display: flex; align-items: center; padding: 12px 24px; color: var(--text-muted); text-decoration: none; font-weight: 500; }
        .sidebar nav a.active { background-color: #fcf8f2; color: var(--primary); border-left: 4px solid var(--primary); }
        .main-content { flex-grow: 1; padding: 30px 40px; overflow-y: auto; }
        .card { background: white; border-radius: 12px; padding: 24px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        .avatar-circle { width: 35px; height: 35px; background: #eee; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-right: 10px; font-weight: bold; font-size: 12px; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo-container" style="text-align:center;"><img src="images/logo.jpg" style="max-width:120px;"></div>
        <nav>
            <a href="dashboard_professeur.php">📝 Évaluations</a>
            <a href="mes_etudiants.php" class="active">👥 Mes Étudiants</a>
            <a href="parametres.php">⚙️ Paramètres</a>
        </nav>
    </aside>
    <main class="main-content">
        <h1>Liste de Classe</h1>
        <div class="card">
            <h2>Suivi des Étudiants</h2>
            <table>
                <tr><th>Étudiant</th><th>Email</th><th>Moyenne Générale</th><th>Statut</th></tr>
                <?php foreach ($liste_eleves as $e): ?>
                <tr>
                    <td>
                        <div class="avatar-circle"><?= substr($e['prenom'],0,1).substr($e['nom'],0,1) ?></div>
                        <strong><?= htmlspecialchars($e['nom']." ".$e['prenom']) ?></strong>
                    </td>
                    <td style="color:var(--text-muted)"><?= htmlspecialchars($e['email']) ?></td>
                    <td><strong><?= $e['moyenne_generale'] ? number_format($e['moyenne_generale'], 2) : '-' ?> / 20</strong></td>
                    <td>
                        <?php if(!$e['moyenne_generale']): ?> <span style="color:orange">Aucune note</span>
                        <?php elseif($e['moyenne_generale'] < 10): ?> <span style="color:red">En difficulté</span>
                        <?php else: ?> <span style="color:green">En réussite</span> <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </main>
</body>
</html>