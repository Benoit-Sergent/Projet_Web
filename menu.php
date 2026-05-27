<?php
// Sécurité : On s'assure qu'une session existe
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['utilisateur_id'])) {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

$mon_id = $_SESSION['utilisateur_id'];
$mon_role = $_SESSION['role'];

// 1. Récupération centralisée des informations de l'utilisateur
$stmt_menu = $db->prepare("
    SELECT u.*, g.nom as nom_groupe 
    FROM utilisateurs u 
    LEFT JOIN groupes g ON u.groupe_id = g.id 
    WHERE u.id = ?
");
$stmt_menu->execute([$mon_id]);
$user_menu = $stmt_menu->fetch(PDO::FETCH_ASSOC);

$initiales_menu = strtoupper(substr($user_menu['prenom'], 0, 1) . substr($user_menu['nom'], 0, 1));

// Recherche physique de la photo de profil (Trombinoscope)
$avatar_menu_url = null;
$search_avatar_menu = glob("uploads/avatars/avatar_" . $mon_id . ".*");
if (!empty($search_avatar_menu)) {
    $avatar_menu_url = $search_avatar_menu[0];
}

// 2. Compteur centralisé des messages non lus
$stmt_unread_menu = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread_menu->execute([$mon_id]);
$messages_non_lus_menu = $stmt_unread_menu->fetchColumn();

// 3. Détection automatique de la page courante pour la classe .active
$current_page = basename($_SERVER['PHP_SELF']);
?>

<header class="top-bar">
    <img src="images/logo.jpg" alt="Logo SmartCampus" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
    <div class="user-widget">
        <div class="user-widget-info" style="text-align: right;">
            <strong><?= htmlspecialchars($user_menu['prenom'] . ' ' . $user_menu['nom']) ?></strong>
            <span>
                <?php 
                if ($mon_role === 'administrateur') echo 'Administrateur';
                elseif ($mon_role === 'professeur') echo 'Professeur Enseignant';
                else echo htmlspecialchars($user_menu['nom_groupe'] ?? 'Étudiant');
                ?>
            </span>
        </div>
        <div class="avatar-small" <?= $mon_role === 'administrateur' ? 'style="background: #1e293b;"' : '' ?>>
            <?php if ($avatar_menu_url): ?>
                <img src="<?= $avatar_menu_url ?>" alt="Profil">
            <?php else: ?>
                <?= $initiales_menu ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<nav class="top-nav">
    <?php if ($mon_role === 'administrateur'): ?>
        <a href="dashboard_administrateur.php" class="<?= $current_page === 'dashboard_administrateur.php' ? 'active' : '' ?>">Membres &amp; Classes</a>
        <a href="gestion_cours.php" class="<?= $current_page === 'gestion_cours.php' ? 'active' : '' ?>">Programme</a>
        <a href="gestion_absences.php" class="<?= $current_page === 'gestion_absences.php' ? 'active' : '' ?>">Scolarité (Absences)</a>
        <a href="rapports_admin.php" class="<?= $current_page === 'rapports_admin.php' ? 'active' : '' ?>">📊 Rapports</a>

    <?php elseif ($mon_role === 'professeur'): ?>
        <a href="dashboard_professeur.php" class="<?= $current_page === 'dashboard_professeur.php' ? 'active' : '' ?>">Évaluations</a>
        <a href="faire_appel.php" class="<?= $current_page === 'faire_appel.php' ? 'active' : '' ?>">Faire l'appel</a>

    <?php else: ?>
        <a href="dashboard_etudiant.php" class="<?= $current_page === 'dashboard_etudiant.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="mes_cours.php" class="<?= $current_page === 'mes_cours.php' ? 'active' : '' ?>">Mes Cours</a>
        <a href="mes_notes.php" class="<?= $current_page === 'mes_notes.php' ? 'active' : '' ?>">Notes</a>
        <a href="presences.php" class="<?= $current_page === 'presences.php' ? 'active' : '' ?>">Présences</a>
    <?php endif; ?>

    <a href="planning.php" class="<?= $current_page === 'planning.php' ? 'active' : '' ?>">Emploi du temps</a>
    <a href="messagerie.php" class="<?= $current_page === 'messagerie.php' ? 'active' : '' ?>">
        Messagerie 💬
        <?php if ($messages_non_lus_menu > 0): ?>
            <span class="notification-badge"><?= $messages_non_lus_menu ?></span>
        <?php endif; ?>
    </a>
    <a href="profil.php" class="<?= $current_page === 'profil.php' ? 'active' : '' ?>">Profil</a>
    <a href="deconnexion.php" style="color: var(--danger);">Déconnexion</a>
</nav>