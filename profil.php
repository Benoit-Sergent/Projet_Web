<?php
session_start();
if (!isset($_SESSION['utilisateur_id'])) { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$mon_id = $_SESSION['utilisateur_id'];
$mon_role = $_SESSION['role'];
$message_succes = ""; 
$message_erreur = "";

// Gestion du dossier d'upload des avatars
$avatar_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
if (!is_dir($avatar_dir)) {
    mkdir($avatar_dir, 0777, true);
}

// 1. ACTION : TÉLÉVERSER LA PHOTO DE PROFIL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $file_name = $_FILES['avatar']['name'];
        $file_size = $_FILES['avatar']['size'];
        $file_tmp = $_FILES['avatar']['tmp_name'];
        
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $extensions_valides = ['jpg', 'jpeg', 'png'];
        
        if (in_array($ext, $extensions_valides)) {
            if ($file_size <= 2 * 1024 * 1024) { // Limite 2 Mo
                // Nettoyer les anciens fichiers pour cet ID
                foreach (glob($avatar_dir . DIRECTORY_SEPARATOR . "avatar_" . $mon_id . ".*") as $old_file) {
                    unlink($old_file);
                }
                
                $new_name = "avatar_" . $mon_id . "." . $ext;
                if (move_uploaded_file($file_tmp, $avatar_dir . DIRECTORY_SEPARATOR . $new_name)) {
                    $message_succes = "Votre photo d'identité a été mise à jour.";
                } else {
                    $message_erreur = "Échec du transfert du fichier.";
                }
            } else {
                $message_erreur = "Le fichier dépasse la taille maximale autorisée (2 Mo).";
            }
        } else {
            $message_erreur = "Format invalide. Autorisé : JPG, JPEG, PNG.";
        }
    } else {
        $message_erreur = "Erreur lors du chargement du fichier.";
    }
}

// 2. ACTION : MODIFIER LE MOT DE PASSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_mdp') {
    $ancien_mdp = $_POST['ancien_mdp'];
    $nouveau_mdp = $_POST['nouveau_mdp'];
    $confirmer_mdp = $_POST['confirmer_mdp'];

    $stmt = $db->prepare("SELECT mot_de_passe_hash FROM utilisateurs WHERE id = ?");
    $stmt->execute([$mon_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($ancien_mdp, $user_data['mot_de_passe_hash'])) {
        if ($nouveau_mdp === $confirmer_mdp) {
            $nouveau_hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
            $stmt_update = $db->prepare("UPDATE utilisateurs SET mot_de_passe_hash = ? WHERE id = ?");
            $stmt_update->execute([$nouveau_hash, $mon_id]);
            $message_succes = "Votre mot de passe a été mis à jour.";
        } else {
            $message_erreur = "Les nouveaux mots de passe ne correspondent pas.";
        }
    } else {
        $message_erreur = "L'ancien mot de passe est erroné.";
    }
}

// Récupération des informations de profil
$stmt_info = $db->prepare("SELECT u.*, g.nom as nom_groupe FROM utilisateurs u LEFT JOIN groupes g ON u.groupe_id = g.id WHERE u.id = ?");
$stmt_info->execute([$mon_id]);
$mon_profil = $stmt_info->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($mon_profil['prenom'], 0, 1) . substr($mon_profil['nom'], 0, 1));

// Recherche de l'avatar physique
$avatar_url = null;
$search_avatar = glob("uploads/avatars/avatar_" . $mon_id . ".*");
if (!empty($search_avatar)) {
    $avatar_url = $search_avatar[0];
}

// Compter les messages non lus
$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$mon_id]); $messages_non_lus = $stmt_unread->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><title>Mon Profil - SmartCampus</title><link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($mon_profil['prenom'] . ' ' . $mon_profil['nom']) ?></strong>
                <span style="text-transform: capitalize;"><?= $mon_role ?></span>
            </div>
            <div class="avatar-small" <?= $mon_role === 'administrateur' ? 'style="background:#2b2b2b;"' : '' ?>>
                <?php if ($avatar_url): ?><img src="<?= $avatar_url ?>" alt="Profil"><?php else: ?><?= $initiales ?><?php endif; ?>
            </div>
        </div>
    </header>

    <nav class="top-nav">
        <?php if ($mon_role === 'administrateur'): ?>
            <a href="dashboard_administrateur.php">Membres & Classes</a><a href="gestion_cours.php">Programme</a><a href="gestion_absences.php">Scolarité (Absences)</a><a href="rapports_admin.php">📊 Rapports</a>
        <?php elseif ($mon_role === 'professeur'): ?>
            <a href="dashboard_professeur.php">Évaluations</a><a href="faire_appel.php">Faire l'appel</a>
        <?php else: ?>
            <a href="dashboard_etudiant.php">Dashboard</a><a href="mes_cours.php">Mes Cours</a><a href="mes_notes.php">Notes</a><a href="presences.php">Présences</a><a href="planning.php">Emploi du temps</a>
        <?php endif; ?>
        <a href="messagerie.php">Messagerie 💬<?php if ($messages_non_lus > 0): ?><span class="notification-badge"><?= $messages_non_lus ?></span><?php endif; ?></a>
        <a href="profil.php" class="active">Profil</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        <h1>Paramètres du Compte</h1>
        <?php if ($message_succes): ?><div class="alert alert-success"><?= $message_succes ?></div><?php endif; ?>
        <?php if ($message_erreur): ?><div class="alert alert-error"><?= $message_erreur ?></div><?php endif; ?>

        <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="card">
                <div class="card-header"><h2>Dossier Académique</h2></div>
                <div style="margin-bottom:15px;"><span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Identité</span><strong style="display:block;"><?= htmlspecialchars($mon_profil['nom'].' '.$mon_profil['prenom']) ?></strong></div>
                <div style="margin-bottom:15px;"><span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Email</span><strong style="display:block;"><?= htmlspecialchars($mon_profil['email']) ?></strong></div>
                <div style="margin-bottom:15px;"><span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Rôle</span><strong style="display:block;text-transform:capitalize;"><?= $mon_role ?></strong></div>
                <?php if($mon_role === 'etudiant'): ?><div><span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Affectation</span><strong style="display:block;color:var(--primary);"><?= $mon_profil['nom_groupe'] ? htmlspecialchars($mon_profil['nom_groupe']):'Aucune' ?></strong></div><?php endif; ?>
            </div>

            <div class="card" style="text-align:center;">
                <div class="card-header"><h2>Photo Institutionnelle</h2></div>
                <div style="display:flex;justify-content:center;margin:15px 0;">
                    <div style="width:110px;height:110px;border-radius:50%;background:var(--bg-body);overflow:hidden;border:3px solid var(--primary);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:bold;color:var(--text-muted);">
                        <?php if($avatar_url): ?><img src="<?= $avatar_url ?>" style="width:100%;height:100%;object-fit:cover;" alt="Avatar"><?php else: ?><?= $initiales ?><?php endif; ?>
                    </div>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_avatar">
                    <input type="file" name="avatar" accept=".jpg,.jpeg,.png" required style="font-size:12px;margin-bottom:15px;width:100%;">
                    <button type="submit" class="btn-action" style="width:100%;">Mettre à jour la photo</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><h2>Sécurité d'accès</h2></div>
                <form method="POST">
                    <input type="hidden" name="action" value="modifier_mdp">
                    <label>Ancien mot de passe</label><input type="password" name="ancien_mdp" required>
                    <label>Nouveau mot de passe</label><input type="password" name="nouveau_mdp" required>
                    <label>Confirmation</label><input type="password" name="confirmer_mdp" required>
                    <button type="submit" class="btn-action" style="width:100%;">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>