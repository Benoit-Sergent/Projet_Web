<?php
session_start();
// Barrière de sécurité
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

// 1. Récupération des infos de l'admin connecté
$stmt_admin = $db->prepare("SELECT prenom, nom, email FROM utilisateurs WHERE id = ?");
$stmt_admin->execute([$_SESSION['utilisateur_id']]);
$admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1));

// 2. Récupération de tous les utilisateurs
$utilisateurs = $db->query("SELECT id, prenom, nom, email, role FROM utilisateurs ORDER BY role, nom")->fetchAll(PDO::FETCH_ASSOC);

// 3. Calcul des statistiques rapides
$nb_etudiants = 0;
$nb_profs = 0;
foreach ($utilisateurs as $u) {
    if ($u['role'] === 'etudiant') $nb_etudiants++;
    if ($u['role'] === 'professeur') $nb_profs++;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - SmartCampus</title>
    <style>
        /* Variables communes */
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

        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-body);
            margin: 0; display: flex; height: 100vh; overflow: hidden; color: var(--text-main);
        }

        /* --- BARRE LATÉRALE --- */
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

        /* --- CONTENU PRINCIPAL --- */
        .main-content { flex-grow: 1; padding: 30px 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .btn-action { background-color: var(--primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: 0.3s; }
        .btn-action:hover { background-color: #a68444; }

        /* --- CARTES ET GRILLES --- */
        .stats-top { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .card { background: white; border-radius: 12px; padding: 24px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .card-header { border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
        .card-header h2 { margin: 0; font-size: 18px; color: var(--text-main); }

        /* Stats */
        .stat-box { display: flex; align-items: center; justify-content: space-between; }
        .stat-box h3 { margin: 0; font-size: 14px; color: var(--text-muted); font-weight: 500; }
        .stat-box .number { font-size: 32px; font-weight: bold; color: var(--primary); }

        /* Tableaux */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--border); font-size: 14px; }
        th { color: var(--text-muted); font-weight: 500; }
        
        /* Badges */
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-admin { background: #fce8e6; color: #d93025; }
        .badge-prof { background: #e8f0fe; color: #1a73e8; }
        .badge-etu { background: #e6f4ea; color: #137333; }
        
        .btn-delete { color: var(--danger); text-decoration: none; font-weight: 600; font-size: 13px; padding: 4px 8px; border-radius: 4px; transition: 0.2s; }
        .btn-delete:hover { background-color: var(--danger-light); }

        /* Formulaire */
        input, select { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: var(--primary); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo-container">
            <img src="images/logo.jpg" alt="Logo HEJ" onerror="this.src='https://via.placeholder.com/120x50?text=SmartCampus'">
        </div>
        
<nav>
    <a href="dashboard_administrateur.php" class="active">👥 Gestion des utilisateurs</a>
    <a href="gestion_cours.php">📚 Gestion des cours</a>
    <a href="rapports_admin.php">📈 Statistiques Globales</a>
    <a href="parametres.php">⚙️ Paramètres</a>
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
                <h1>Administration du Campus</h1>
                <span style="color: var(--text-muted); font-size: 14px;">Gérez les accès et les membres de la plateforme.</span>
            </div>
            <a href="deconnexion.php" class="btn-action" style="background-color: var(--text-main);">Se déconnecter</a>
        </header>

        <div class="stats-top">
            <div class="card stat-box">
                <div>
                    <h3>Total Utilisateurs</h3>
                    <div class="number"><?= count($utilisateurs) ?></div>
                </div>
            </div>
            <div class="card stat-box">
                <div>
                    <h3>Étudiants actifs</h3>
                    <div class="number" style="color: #137333;"><?= $nb_etudiants ?></div>
                </div>
            </div>
            <div class="card stat-box">
                <div>
                    <h3>Corps professoral</h3>
                    <div class="number" style="color: #1a73e8;"><?= $nb_profs ?></div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            
            <div class="card">
                <div class="card-header">
                    <h2>Annuaire des membres</h2>
                </div>
                <table>
                    <tr>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?></strong></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php 
                                if ($u['role'] === 'administrateur') echo '<span class="badge badge-admin">Admin</span>';
                                elseif ($u['role'] === 'professeur') echo '<span class="badge badge-prof">Professeur</span>';
                                else echo '<span class="badge badge-etu">Étudiant</span>';
                                ?>
                            </td>
                            <td>
                                <?php if ($u['id'] !== $_SESSION['utilisateur_id']): ?>
                                    <a href="supprimer_utilisateur.php?id=<?= $u['id'] ?>" 
                                       class="btn-delete"
                                       onclick="return confirm('Supprimer définitivement <?= htmlspecialchars($u['prenom']) ?> ?');">
                                       🗑️ Supprimer
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--border); font-size: 12px; font-style: italic;">(Vous)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Nouveau membre</h2>
                </div>
                <form action="traitement_inscription.php" method="POST">
                    <input type="text" name="prenom" placeholder="Prénom" required>
                    <input type="text" name="nom" placeholder="Nom de famille" required>
                    <input type="email" name="email" placeholder="Adresse email" required>
                    
                    <select name="role" required>
                        <option value="etudiant">Étudiant</option>
                        <option value="professeur">Professeur</option>
                        <option value="administrateur">Administrateur</option>
                    </select>

                    <input type="password" name="mot_de_passe" placeholder="Mot de passe provisoire" required>
                    
                    <button type="submit" class="btn-action" style="width: 100%;">Créer le compte</button>
                </form>
            </div>

        </div>
    </main>

</body>
</html>