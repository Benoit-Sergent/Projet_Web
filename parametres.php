<?php
session_start();
// Barrière de sécurité : il faut être connecté, peu importe le rôle
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role'])) {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

$message_erreur = "";
$message_succes = "";

// 1. Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ancien_mdp = $_POST['ancien_mdp'];
    $nouveau_mdp = $_POST['nouveau_mdp'];
    $confirmer_mdp = $_POST['confirmer_mdp'];

    // On récupère le mot de passe actuel en base
    $stmt = $db->prepare("SELECT mot_de_passe_hash FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['utilisateur_id']]);
    $user_db = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérification de l'ancien mot de passe
    if (password_verify($ancien_mdp, $user_db['mot_de_passe_hash'])) {
        // Vérification de la correspondance des nouveaux mots de passe
        if ($nouveau_mdp === $confirmer_mdp) {
            // Hachage et mise à jour
            $nouveau_hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
            $update_stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe_hash = ? WHERE id = ?");
            $update_stmt->execute([$nouveau_hash, $_SESSION['utilisateur_id']]);
            $message_succes = "✅ Votre mot de passe a été mis à jour avec succès.";
        } else {
            $message_erreur = "⚠️ Les nouveaux mots de passe ne correspondent pas.";
        }
    } else {
        $message_erreur = "⚠️ L'ancien mot de passe est incorrect.";
    }
}

// 2. Récupération des infos de l'utilisateur pour l'affichage
$stmt_info = $db->prepare("SELECT prenom, nom, email, role FROM utilisateurs WHERE id = ?");
$stmt_info->execute([$_SESSION['utilisateur_id']]);
$user_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($user_info['prenom'], 0, 1) . substr($user_info['nom'], 0, 1));

// Détermination du lien de retour selon le rôle
$lien_dashboard = "dashboard_" . $_SESSION['role'] . ".php";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - SmartCampus</title>
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
        .user-widget-info span { display: block; color: var(--text-muted); font-size: 12px; text-transform: capitalize; }

        /* Contenu principal */
        .main-content { flex-grow: 1; padding: 30px 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        
        /* Cartes et Formulaire */
        .card { background: white; border-radius: 12px; padding: 24px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.02); max-width: 500px; }
        .card-header { border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
        .card-header h2 { margin: 0; font-size: 18px; color: var(--text-main); }
        
        label { font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; display: block; margin-top: 15px; }
        input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 14px; }
        input:focus { outline: none; border-color: var(--primary); }
        
        .btn-action { background-color: var(--primary); color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: 0.3s; width: 100%; margin-top: 20px; }
        .btn-action:hover { background-color: #a68444; }

        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
        .alert-success { background-color: #e6f4ea; color: #137333; border: 1px solid #cce8d6; }
        .alert-error { background-color: #fce8e6; color: #d93025; border: 1px solid #f8d0cb; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo-container">
            <img src="images/logo.jpg" alt="Logo HEJ" onerror="this.src='https://via.placeholder.com/120x50?text=SmartCampus'">
        </div>
        
        <nav>
            <a href="<?= $lien_dashboard ?>">⬅️ Retour au tableau de bord</a>
            <a href="parametres.php" class="active">⚙️ Paramètres de sécurité</a>
        </nav>

        <div class="user-widget">
            <div class="avatar-small"><?= $initiales ?></div>
            <div class="user-widget-info">
                <strong><?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?></strong>
                <span><?= htmlspecialchars($user_info['role']) ?></span>
            </div>
        </div>
    </aside>

    <main class="main-content">
        
        <header class="header">
            <div>
                <h1>Mon Compte</h1>
                <span style="color: var(--text-muted); font-size: 14px;">Sécurisez votre accès à la plateforme.</span>
            </div>
        </header>

        <?php if ($message_erreur): ?>
            <div class="alert alert-error"><?= $message_erreur ?></div>
        <?php endif; ?>

        <?php if ($message_succes): ?>
            <div class="alert alert-success"><?= $message_succes ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2>Changer mon mot de passe</h2>
            </div>
            <form action="parametres.php" method="POST">
                <label>Mot de passe actuel</label>
                <input type="password" name="ancien_mdp" required>
                
                <label>Nouveau mot de passe</label>
                <input type="password" name="nouveau_mdp" required minlength="6">
                
                <label>Confirmer le nouveau mot de passe</label>
                <input type="password" name="confirmer_mdp" required minlength="6">
                
                <button type="submit" class="btn-action">Mettre à jour la sécurité</button>
            </form>
        </div>

    </main>

</body>
</html>