<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['utilisateur_id'])) { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$mon_id = $_SESSION['utilisateur_id'];
$mon_role = $_SESSION['role'];

// Infos utilisateur
$stmt_menu = $db->prepare("SELECT u.*, g.nom as nom_groupe FROM utilisateurs u LEFT JOIN groupes g ON u.groupe_id = g.id WHERE u.id = ?");
$stmt_menu->execute([$mon_id]);
$user_menu = $stmt_menu->fetch(PDO::FETCH_ASSOC);

$initiales_menu = strtoupper(substr($user_menu['prenom'], 0, 1) . substr($user_menu['nom'], 0, 1));
$avatar_menu_url = null;
$search_avatar_menu = glob("uploads/avatars/avatar_" . $mon_id . ".*");
if (!empty($search_avatar_menu)) { $avatar_menu_url = $search_avatar_menu[0]; }

// Messages non lus
$stmt_unread_menu = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread_menu->execute([$mon_id]);
$messages_non_lus_menu = $stmt_unread_menu->fetchColumn();

$current_page = basename($_SERVER['PHP_SELF']);
?>

<header class="top-bar">
    <img src="images/logo.jpg" alt="SmartCampus" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
    <div class="user-widget">
        <div class="user-widget-info" style="text-align: right;">
            <strong style="color:var(--text-main); font-size:14px;"><?= htmlspecialchars($user_menu['prenom'] . ' ' . $user_menu['nom']) ?></strong>
            <span style="font-size:12px; color:var(--text-muted);">
                <?php 
                if ($mon_role === 'administrateur') echo 'Administration';
                elseif ($mon_role === 'professeur') echo 'Corps Enseignant';
                else echo htmlspecialchars($user_menu['nom_groupe'] ?? 'Étudiant');
                ?>
            </span>
        </div>
        <div class="avatar-small" <?= $mon_role === 'administrateur' ? 'style="background: #1e293b;"' : '' ?>>
            <?php if ($avatar_menu_url): ?><img src="<?= $avatar_menu_url ?>" alt="Profil"><?php else: ?><?= $initiales_menu ?><?php endif; ?>
        </div>
    </div>
</header>

<nav class="top-nav">
    <?php if ($mon_role === 'administrateur'): ?>
        <a href="dashboard_administrateur.php" class="<?= $current_page === 'dashboard_administrateur.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg> Membres
        </a>
        <a href="gestion_cours.php" class="<?= $current_page === 'gestion_cours.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253" /></svg> Programme
        </a>
        <a href="gestion_absences.php" class="<?= $current_page === 'gestion_absences.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg> Scolarité
        </a>
        <a href="gestion_notes.php" class="<?= $current_page === 'gestion_notes.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z" />
            </svg> Notes
        </a>
        <a href="rapports_admin.php" class="<?= $current_page === 'rapports_admin.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg> Rapports
        </a>

    <?php elseif ($mon_role === 'professeur'): ?>
        <a href="dashboard_professeur.php" class="<?= $current_page === 'dashboard_professeur.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> Évaluations
        </a>
        <a href="faire_appel.php" class="<?= $current_page === 'faire_appel.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg> Appel
        </a>
        <a href="mes_cours_prof.php" class="<?= $current_page === 'mes_cours_prof.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253" /></svg> Mes cours
        </a>
        <a href="gestion_notes.php" class="<?= $current_page === 'gestion_notes.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z" />
            </svg> Notes
        </a>

    <?php else: ?>
        <a href="dashboard_etudiant.php" class="<?= $current_page === 'dashboard_etudiant.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg> Accueil
        </a>
        <a href="mes_cours.php" class="<?= $current_page === 'mes_cours.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253" /></svg> Cours
        </a>
        <a href="mes_notes.php" class="<?= $current_page === 'mes_notes.php' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 14l9-5-9-5-9 5 9 5z" /><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" /></svg> Notes
        </a>
    <?php endif; ?>

    <a href="planning.php" class="<?= $current_page === 'planning.php' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg> Planning
    </a>
    <a href="messagerie.php" class="<?= $current_page === 'messagerie.php' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg> 
        Messagerie <?php if ($messages_non_lus_menu > 0): ?><span class="notification-badge"><?= $messages_non_lus_menu ?></span><?php endif; ?>
    </a>
    <a href="profil.php" class="<?= $current_page === 'profil.php' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> Profil
    </a>
    
    <a href="deconnexion.php" style="color: var(--danger); margin-left: auto;">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg> Quitter
    </a>
</nav>