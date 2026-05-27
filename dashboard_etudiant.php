<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$etud_id = $_SESSION['utilisateur_id'];

// 1. Compter les messages non lus
$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$etud_id]);
$messages_non_lus = $stmt_unread->fetchColumn();

// 2. Informations de l'élève, sa classe et son avatar
$user = $db->query("SELECT u.*, g.nom as nom_groupe FROM utilisateurs u LEFT JOIN groupes g ON u.groupe_id = g.id WHERE u.id = $etud_id")->fetch();
$initiales = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

$avatar_url = null;
$search_avatar = glob("uploads/avatars/avatar_" . $etud_id . ".*");
if (!empty($search_avatar)) { $avatar_url = $search_avatar[0]; }

// 3. Liste des cours
$liste_cours = [];
if ($user['groupe_id']) {
    $stmt = $db->prepare("SELECT * FROM cours WHERE groupe_id = ? ORDER BY titre");
    $stmt->execute([$user['groupe_id']]);
    $liste_cours = $stmt->fetchAll();
}

// 4. Moyenne générale
$stmt_notes = $db->prepare("SELECT valeur_note FROM notes WHERE etudiant_id = ?");
$stmt_notes->execute([$etud_id]);
$notes = $stmt_notes->fetchAll();
$moyenne = 0;
if (count($notes) > 0) {
    $moyenne = round(array_sum(array_column($notes, 'valeur_note')) / count($notes), 2);
}

// 5. Absences et retards
$stmt_abs = $db->prepare("SELECT COUNT(*) FROM presences WHERE etudiant_id = ? AND statut = 'absent'");
$stmt_abs->execute([$etud_id]);
$nb_abs = $stmt_abs->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Étudiant - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Styles spécifiques pour sublimer les statistiques du dashboard */
        .stat-card {
            display: flex;
            align-items: center;
            padding: 30px;
            gap: 25px;
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon-blue { background: #e0e7ff; color: #4f46e5; }
        .icon-red { background: #fee2e2; color: #ef4444; }
        
        .stat-info h2 { font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px; }
        .stat-info .stat-value { font-size: 42px; font-weight: 700; line-height: 1; color: var(--text-main); }
        .stat-info .stat-sub { font-size: 16px; color: var(--text-muted); font-weight: 500; }
        
        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state svg { width: 48px; height: 48px; margin-bottom: 15px; opacity: 0.5; }
        
        .course-list { list-style: none; padding: 0; margin: 0; }
        .course-item { display: flex; align-items: center; padding: 15px 0; border-bottom: 1px solid var(--border); }
        .course-item:last-child { border-bottom: none; padding-bottom: 0; }
        .course-icon { width: 40px; height: 40px; background: var(--bg-body); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px; color: var(--primary); }
    </style>
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo SmartCampus" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></strong>
                <span><?= $user['nom_groupe'] ? htmlspecialchars($user['nom_groupe']) : 'Classe non assignée' ?></span>
            </div>
            <div class="avatar-small">
                <?php if ($avatar_url): ?><img src="<?= $avatar_url ?>" alt="Profil"><?php else: ?><?= $initiales ?><?php endif; ?>
            </div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_etudiant.php" class="active">Dashboard</a>
        <a href="mes_cours.php">Mes Cours</a>
        <a href="mes_notes.php">Notes</a>
        <a href="presences.php">Présences</a>
        <a href="planning.php">Emploi du temps</a>
        <a href="messagerie.php">Messagerie 💬<?php if ($messages_non_lus > 0): ?><span class="notification-badge"><?= $messages_non_lus ?></span><?php endif; ?></a>
        <a href="profil.php">Profil</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="color:var(--primary);">Bonjour, <?= htmlspecialchars($user['prenom']) ?> 👋</h1>
            <p style="color:var(--text-muted); margin:0;">Bienvenue sur votre espace académique.</p>
        </div>

        <div class="dashboard-grid">
            <div class="card stat-card">
                <div class="stat-icon icon-blue">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                </div>
                <div class="stat-info">
                    <h2>Moyenne Générale</h2>
                    <?php if (count($notes) > 0): ?>
                        <div class="stat-value"><?= number_format($moyenne, 2, ',', ' ') ?> <span class="stat-sub">/ 20</span></div>
                    <?php else: ?>
                        <div class="stat-value" style="color: var(--text-muted); font-size: 24px;">-- <span class="stat-sub">/ 20</span></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card stat-card">
                <div class="stat-icon icon-red">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="stat-info">
                    <h2>Absences Signalées</h2>
                    <div class="stat-value"><?= $nb_abs ?> <span class="stat-sub">heure(s)</span></div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:25px;">
            <div class="card-header"><h2>Mes Enseignements Actuels</h2></div>
            
            <?php if (empty($liste_cours)): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                    <p>Vous n'êtes inscrit à aucun cours pour le moment.<br>Veuillez contacter l'administration scolarité.</p>
                </div>
            <?php else: ?>
                <ul class="course-list">
                    <?php foreach($liste_cours as $c): ?>
                        <li class="course-item">
                            <div class="course-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <div>
                                <strong style="display:block; font-size: 15px; color: var(--text-main);"><?= htmlspecialchars($c['titre']) ?></strong>
                                <span style="font-size: 13px; color: var(--text-muted);"><?= htmlspecialchars($c['categorie']) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>