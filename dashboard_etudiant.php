<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$etud_id = $_SESSION['utilisateur_id'];

// ==========================================
// 1. MESSAGERIE ET NOTIFICATIONS
// ==========================================
$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$etud_id]);
$messages_non_lus = $stmt_unread->fetchColumn();

// ==========================================
// 2. INFORMATIONS DE L'ÉTUDIANT ET AVATAR
// ==========================================
$user = $db->query("
    SELECT u.*, g.nom as nom_groupe 
    FROM utilisateurs u 
    LEFT JOIN groupes g ON u.groupe_id = g.id 
    WHERE u.id = $etud_id
")->fetch();

$initiales = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
$avatar_url = null;
$search_avatar = glob("uploads/avatars/avatar_" . $etud_id . ".*");
if (!empty($search_avatar)) { 
    $avatar_url = $search_avatar[0]; 
}

// ==========================================
// 3. TOUS LES COURS DE L'ÉTUDIANT
// ==========================================
$liste_cours = [];
if ($user['groupe_id']) {
    $stmt = $db->prepare("SELECT * FROM cours WHERE groupe_id = ? ORDER BY titre");
    $stmt->execute([$user['groupe_id']]);
    $liste_cours = $stmt->fetchAll();
}

// ==========================================
// 4. LES 3 PROCHAINS COURS (TIMELINE)
// ==========================================
$prochains_cours = [];
if ($user['groupe_id']) {
    $stmt_next = $db->prepare("
        SELECT c.*, u.nom as prof_nom 
        FROM cours c 
        LEFT JOIN utilisateurs u ON c.professeur_id = u.id 
        WHERE c.groupe_id = ? 
        LIMIT 3
    ");
    $stmt_next->execute([$user['groupe_id']]);
    $prochains_cours = $stmt_next->fetchAll();
}

// ==========================================
// 5. MOYENNE GÉNÉRALE
// ==========================================
$stmt_notes = $db->prepare("SELECT valeur_note FROM notes WHERE etudiant_id = ?");
$stmt_notes->execute([$etud_id]);
$notes = $stmt_notes->fetchAll();
$moyenne = 0;
if (count($notes) > 0) {
    $moyenne = round(array_sum(array_column($notes, 'valeur_note')) / count($notes), 2);
}

// ==========================================
// 6. COMPTEUR D'ABSENCES
// ==========================================
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
        /* Cartes Statistiques */
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
        
        .stat-info h2 { 
            font-size: 13px; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            margin-bottom: 5px; 
        }
        .stat-info .stat-value { 
            font-size: 42px; 
            font-weight: 700; 
            line-height: 1; 
            color: var(--text-main); 
        }
        .stat-info .stat-sub { 
            font-size: 16px; 
            color: var(--text-muted); 
            font-weight: 500; 
        }
        
        /* Empty States */
        .empty-state { 
            text-align: center; 
            padding: 30px 20px; 
            color: var(--text-muted); 
        }
        .empty-state svg { 
            width: 44px; 
            height: 44px; 
            margin-bottom: 10px; 
            opacity: 0.4; 
        }
        
        /* TIMELINE DESIGN POUR LES PROCHAINS COURS */
        .timeline { 
            position: relative; 
            padding-left: 24px; 
            margin-top: 10px; 
        }
        .timeline::before { 
            content: ''; 
            position: absolute; 
            left: 7px; 
            top: 8px; 
            bottom: 8px; 
            width: 2px; 
            background: var(--border); 
        }
        .timeline-item { 
            position: relative; 
            margin-bottom: 20px; 
        }
        .timeline-item:last-child { 
            margin-bottom: 0; 
        }
        .timeline-badge { 
            position: absolute; 
            left: -22px; 
            top: 6px; 
            width: 8px; 
            height: 8px; 
            border-radius: 50%; 
            background: var(--primary); 
            border: 2px solid var(--surface); 
            box-shadow: 0 0 0 3px var(--primary-light); 
        }
        .timeline-content { 
            background: var(--bg-body); 
            padding: 12px 16px; 
            border-radius: var(--radius-md); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        /* Liste des enseignements */
        .course-list { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
        }
        .course-item { 
            display: flex; 
            align-items: center; 
            padding: 12px 0; 
            border-bottom: 1px solid var(--border); 
        }
        .course-item:last-child { 
            border-bottom: none; 
            padding-bottom: 0; 
        }
        .course-icon { 
            width: 36px; 
            height: 36px; 
            background: var(--bg-body); 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-right: 15px; 
            color: var(--primary); 
        }
    </style>
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></strong>
                <span><?= $user['nom_groupe'] ? htmlspecialchars($user['nom_groupe']) : 'Non assigné' ?></span>
            </div>
            <div class="avatar-small">
                <?php if ($avatar_url): ?>
                    <img src="<?= $avatar_url ?>" alt="Profil">
                <?php else: ?>
                    <?= $initiales ?>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_etudiant.php" class="active">Dashboard</a>
        <a href="mes_cours.php">Mes Cours</a>
        <a href="mes_notes.php">Notes</a>
        <a href="presences.php">Présences</a>
        <a href="planning.php">Emploi du temps</a>
        <a href="messagerie.php">
            Messagerie 💬
            <?php if ($messages_non_lus > 0): ?>
                <span class="notification-badge"><?= $messages_non_lus ?></span>
            <?php endif; ?>
        </a>
        <a href="profil.php">Profil</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        
        <div style="margin-bottom: 30px;">
            <h1 style="color:var(--primary);">Bonjour, <?= htmlspecialchars($user['prenom']) ?> 👋</h1>
            <p style="color:var(--text-muted); margin:0;">Votre point de situation en temps réel.</p>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 25px;">
            <div class="card stat-card">
                <div class="stat-icon icon-blue">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <h2>Moyenne Générale</h2>
                    <?php if (count($notes) > 0): ?>
                        <div class="stat-value">
                            <?= number_format($moyenne, 2, ',', ' ') ?> 
                            <span class="stat-sub">/ 20</span>
                        </div>
                    <?php else: ?>
                        <div class="stat-value" style="color:var(--text-muted); font-size:24px;">
                            -- <span class="stat-sub">/ 20</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card stat-card">
                <div class="stat-icon icon-red">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <h2>Absences Constatées</h2>
                    <div class="stat-value">
                        <?= $nb_abs ?> <span class="stat-sub">séance(s)</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            
            <div class="card">
                <div class="card-header"><h2>📅 Prochains cours au planning</h2></div>
                
                <?php if (empty($prochains_cours)): ?>
                    <div class="empty-state">
                        <p>Aucune séance planifiée prochainement.</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach($prochains_cours as $pc): ?>
                            <div class="timeline-item">
                                <div class="timeline-badge"></div>
                                <div class="timeline-content">
                                    <div>
                                        <strong style="color:var(--text-main); font-size:14px; display:block;">
                                            <?= htmlspecialchars($pc['titre']) ?>
                                        </strong>
                                        <span style="font-size:12px; color:var(--text-muted);">
                                            Par M. <?= htmlspecialchars($pc['prof_nom'] ?? 'Enseignant') ?>
                                        </span>
                                    </div>
                                    <div style="text-align:right;">
                                        <span class="badge badge-success" style="font-size:11px; padding:3px 8px; font-weight:600; display:block; margin-bottom:4px;">
                                            Salle A302
                                        </span>
                                        <span style="font-size:11px; font-weight:500; color:var(--primary);">
                                            Prochainement
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header"><h2>📚 Mes Enseignements</h2></div>
                
                <?php if (empty($liste_cours)): ?>
                    <div class="empty-state">
                        <p>Aucun cours rattaché.</p>
                    </div>
                <?php else: ?>
                    <ul class="course-list">
                        <?php foreach($liste_cours as $c): ?>
                            <li class="course-item">
                                <div class="course-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <strong style="font-size:14px; color:var(--text-main); display:block;">
                                        <?= htmlspecialchars($c['titre']) ?>
                                    </strong>
                                    <span style="font-size:12px; color:var(--text-muted);">
                                        <?= htmlspecialchars($c['categorie']) ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>