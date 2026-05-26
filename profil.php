<?php
session_start();
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role'])) { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$message_erreur = ""; $message_succes = "";

// Traitement du formulaire de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mdp') {
    $ancien = $_POST['ancien_mdp']; $nouveau = $_POST['nouveau_mdp']; $confirmer = $_POST['confirmer_mdp'];
    $stmt = $db->prepare("SELECT mot_de_passe_hash FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['utilisateur_id']]);
    $user_db = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($ancien, $user_db['mot_de_passe_hash'])) {
        if ($nouveau === $confirmer) {
            $new_hash = password_hash($nouveau, PASSWORD_DEFAULT);
            $db->prepare("UPDATE utilisateurs SET mot_de_passe_hash = ? WHERE id = ?")->execute([$new_hash, $_SESSION['utilisateur_id']]);
            $message_succes = "Votre mot de passe a été mis à jour avec succès.";
        } else { $message_erreur = "Les nouveaux mots de passe diffèrent."; }
    } else { $message_erreur = "L'ancien mot de passe est incorrect."; }
}

// Récupération des infos de l'utilisateur
$stmt_info = $db->prepare("SELECT prenom, nom, email, role FROM utilisateurs WHERE id = ?");
$stmt_info->execute([$_SESSION['utilisateur_id']]);
$user_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($user_info['prenom'], 0, 1) . substr($user_info['nom'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil - SmartCampus</title>
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
            <a href="deconnexion.php" style="margin-left: 15px; color: var(--danger); text-decoration: none; font-size: 13px; font-weight: 600;">Déconnexion</a>
        </div>
    </header>

    <?php if($_SESSION['role'] === 'etudiant'): ?>
    <nav class="top-nav">
        <a href="dashboard_etudiant.php">Dashboard</a>
        <a href="profil.php" class="active">Profil</a>
        <a href="mes_cours.php">Mes Cours</a>
        <a href="mes_notes.php">Notes</a>
        <a href="presences.php">Présences</a>
        <a href="planning.php">Emploi du temps</a>
    </nav>
    <?php endif; ?>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1 style="margin:0; color:var(--primary);">Dossier Personnel</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Consultez vos informations et gérez votre sécurité.</p>
        </div>

        <?php if ($message_erreur): ?><div class="alert alert-error"><?= $message_erreur ?></div><?php endif; ?>
        <?php if ($message_succes): ?><div class="alert alert-success"><?= $message_succes ?></div><?php endif; ?>

        <div class="dashboard-grid" style="grid-template-columns: 1fr 2fr;">
            
            <div class="card" style="text-align: center;">
                <div class="avatar-small" style="width: 80px; height: 80px; font-size: 28px; margin: 0 auto 20px;">
                    <?= $initiales ?>
                </div>
                <h2 style="margin: 0; color: var(--text-main); font-size: 22px;">
                    <?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?>
                </h2>
                <span class="badge badge-neutral" style="margin-top: 10px; text-transform: capitalize;">
                    <?= htmlspecialchars($user_info['role']) ?>
                </span>
                
                <div style="margin-top: 30px; text-align: left; border-top: 1px solid var(--border); padding-top: 20px;">
                    <label>Adresse Email</label>
                    <p style="margin: 0 0 15px 0; font-size: 14px; font-weight: 500;"><?= htmlspecialchars($user_info['email']) ?></p>
                    
                    <label>Statut du compte</label>
                    <p style="margin: 0; font-size: 14px;"><span style="color: var(--success); font-weight: 600;">● Actif</span></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Sécurité du compte</h2></div>
                <form action="profil.php" method="POST">
                    <input type="hidden" name="action" value="mdp">
                    <label>Mot de passe actuel</label>
                    <input type="password" name="ancien_mdp" required>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label>Nouveau mot de passe</label>
                            <input type="password" name="nouveau_mdp" required minlength="6">
                        </div>
                        <div>
                            <label>Confirmation</label>
                            <input type="password" name="confirmer_mdp" required minlength="6">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-action" style="margin-top:20px;">Mettre à jour mon mot de passe</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>