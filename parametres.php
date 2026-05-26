<?php
session_start();
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role'])) { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$message_erreur = ""; $message_succes = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ancien = $_POST['ancien_mdp']; $nouveau = $_POST['nouveau_mdp']; $confirmer = $_POST['confirmer_mdp'];

    $stmt = $db->prepare("SELECT mot_de_passe_hash FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['utilisateur_id']]);
    $user_db = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($ancien, $user_db['mot_de_passe_hash'])) {
        if ($nouveau === $confirmer) {
            $new_hash = password_hash($nouveau, PASSWORD_DEFAULT);
            $db->prepare("UPDATE utilisateurs SET mot_de_passe_hash = ? WHERE id = ?")->execute([$new_hash, $_SESSION['utilisateur_id']]);
            $message_succes = "Votre sécurité a été mise à jour.";
        } else { $message_erreur = "Les nouveaux mots de passe diffèrent."; }
    } else { $message_erreur = "L'ancien mot de passe est faux."; }
}

$stmt_info = $db->prepare("SELECT prenom, nom, role FROM utilisateurs WHERE id = ?");
$stmt_info->execute([$_SESSION['utilisateur_id']]);
$user_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($user_info['prenom'], 0, 1) . substr($user_info['nom'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paramètres - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo HEJ">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?></strong>
                <span style="text-transform: capitalize;"><?= htmlspecialchars($user_info['role']) ?></span>
            </div>
            <div class="avatar-small"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_<?= $_SESSION['role'] ?>.php">⬅️ Tableau de Bord</a>
        <a href="parametres.php" class="active">Paramètres</a>
        <a href="deconnexion.php">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1 style="margin:0; color:var(--primary);">Paramètres de Sécurité</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Gérez la confidentialité de vos accès.</p>
        </div>

        <?php if ($message_erreur): ?><div class="alert alert-error"><?= $message_erreur ?></div><?php endif; ?>
        <?php if ($message_succes): ?><div class="alert alert-success"><?= $message_succes ?></div><?php endif; ?>

        <div class="card" style="max-width: 500px; margin: 0 auto;">
            <div class="card-header"><h2>Changer de mot de passe</h2></div>
            <form action="parametres.php" method="POST">
                <label>Mot de passe actuel</label>
                <input type="password" name="ancien_mdp" required>
                <label>Nouveau mot de passe</label>
                <input type="password" name="nouveau_mdp" required minlength="6">
                <label>Confirmation du nouveau mot de passe</label>
                <input type="password" name="confirmer_mdp" required minlength="6">
                <button type="submit" class="btn-action" style="width: 100%; margin-top:20px;">Mettre à jour</button>
            </form>
        </div>
    </div>
</body>
</html>