<?php
session_start();
if (!isset($_SESSION['utilisateur_id'])) { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$mon_id = $_SESSION['utilisateur_id'];
$mon_role = $_SESSION['role'];

// ==========================================
// 1. INFORMATIONS UTILISATEUR & COMPTEURS
// ==========================================
$user_info = $db->query("SELECT prenom, nom FROM utilisateurs WHERE id = $mon_id")->fetch();
$initiales = strtoupper(substr($user_info['prenom'], 0, 1) . substr($user_info['nom'], 0, 1));

$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$mon_id]); 
$messages_non_lus = $stmt_unread->fetchColumn();

// ==========================================
// 2. RÉCUPÉRATION DE L'EMPLOI DU TEMPS SELON LE RÔLE
// ==========================================
if ($mon_role === 'etudiant') {
    $stmt_plan = $db->prepare("
        SELECT c.*, u.nom as prof_nom, u.prenom as prof_prenom, g.nom as classe_nom 
        FROM cours c 
        LEFT JOIN utilisateurs u ON c.professeur_id = u.id 
        JOIN groupes g ON c.groupe_id = g.id
        WHERE c.groupe_id = (SELECT groupe_id FROM utilisateurs WHERE id = ?)
        ORDER BY c.titre
    ");
    $stmt_plan->execute([$mon_id]);
} elseif ($mon_role === 'professeur') {
    $stmt_plan = $db->prepare("
        SELECT c.*, g.nom as classe_nom 
        FROM cours c 
        JOIN groupes g ON c.groupe_id = g.id 
        WHERE c.professeur_id = ?
        ORDER BY c.titre
    ");
    $stmt_plan->execute([$mon_id]);
} else { 
    // L'administrateur a une vue globale complète
    $stmt_plan = $db->prepare("
        SELECT c.*, u.nom as prof_nom, g.nom as classe_nom 
        FROM cours c 
        LEFT JOIN utilisateurs u ON c.professeur_id = u.id 
        JOIN groupes g ON c.groupe_id = g.id
        ORDER BY g.nom, c.titre
    ");
    $stmt_plan->execute();
}
$tous_les_cours = $stmt_plan->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 3. FONCTION DE SIMULATION HORAIRE (MÉTIER)
// ==========================================
function attribuerJourEtHeureSimules($index, $titre) {
    $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
    $creneaux = [
        ['08:30', '10:30'],
        ['10:45', '12:45'],
        ['14:00', '16:00'],
        ['16:15', '18:15']
    ];
    
    $j_index = $index % 5;
    $c_index = (crc32($titre) & 0x7FFFFFFF) % 4;
    
    return [
        'jour'   => $jours[$j_index],
        'debut'  => $creneaux[$c_index][0],
        'fin'    => $creneaux[$c_index][1],
        'salle'  => 'Salle A' . (200 + ($index * 7) % 30)
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planning - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Onglets horizontaux de sélection de jours */
        .tabs-container { 
            display: flex; 
            gap: 10px; 
            margin-bottom: 25px; 
            border-bottom: 2px solid var(--border); 
            padding-bottom: 10px; 
        }
        .tab-btn { 
            padding: 10px 20px; 
            border-radius: var(--radius-md); 
            font-weight: 600; 
            font-size: 14px; 
            background: white; 
            border: 1px solid var(--border); 
            cursor: pointer; 
            color: var(--text-muted); 
            transition: var(--transition); 
        }
        .tab-btn:hover, .tab-btn.active { 
            background: var(--primary); 
            color: white; 
            border-color: var(--primary); 
            box-shadow: var(--shadow-md); 
        }
        
        /* Grille adaptative pour les blocs de cours */
        .planning-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 20px; 
        }
        .agenda-card { 
            border-left: 5px solid var(--primary); 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
        }
        .agenda-time { 
            font-size: 16px; 
            font-weight: 700; 
            color: var(--primary); 
            display: flex; 
            align-items: center; 
            gap: 6px; 
        }
        .agenda-meta { 
            font-size: 13px; 
            color: var(--text-muted); 
            margin-top: 12px; 
            display: flex; 
            gap: 15px; 
        }
    </style>
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?></strong>
                <span style="text-transform:capitalize;"><?= $mon_role ?></span>
            </div>
            <div class="avatar-small"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <?php if ($mon_role === 'administrateur'): ?>
            <a href="dashboard_administrateur.php">Membres &amp; Classes</a>
            <a href="gestion_cours.php">Programme</a>
            <a href="gestion_absences.php">Scolarité</a>
            <a href="rapports_admin.php">📊 Rapports</a>
        <?php elseif ($mon_role === 'professeur'): ?>
            <a href="dashboard_professeur.php">Évaluations</a>
            <a href="faire_appel.php">Faire l'appel</a>
        <?php else: ?>
            <a href="dashboard_etudiant.php">Dashboard</a>
            <a href="mes_cours.php">Mes Cours</a>
            <a href="mes_notes.php">Notes</a>
            <a href="presences.php">Présences</a>
        <?php endif; ?>
        
        <a href="planning.php" class="active">Emploi du temps</a>
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
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <div>
                <h1>Mon Emploi du Temps</h1>
                <p style="color:var(--text-muted); margin:0;">Visualisation de vos sessions de cours et gestion des créneaux.</p>
            </div>
            <button class="btn-action" onclick="window.print()" style="background-color:var(--text-main);">🖨️ Imprimer le planning</button>
        </div>

        <div class="tabs-container">
            <button class="tab-btn active" onclick="filtrerJour('Tous')">🗓️ Toute la semaine</button>
            <button class="tab-btn" onclick="filtrerJour('Lundi')">Lundi</button>
            <button class="tab-btn" onclick="filtrerJour('Mardi')">Mardi</button>
            <button class="tab-btn" onclick="filtrerJour('Mercredi')">Mercredi</button>
            <button class="tab-btn" onclick="filtrerJour('Jeudi')">Jeudi</button>
            <button class="tab-btn" onclick="filtrerJour('Vendredi')">Vendredi</button>
        </div>

        <?php if (empty($tous_les_cours)): ?>
            <div class="card" style="text-align:center; padding:40px; color:var(--text-muted);">
                Aucun cours programmé dans votre calendrier actuellement.
            </div>
        <?php else: ?>
            <div class="planning-grid" id="planningGrid">
                <?php foreach($tous_les_cours as $index => $c): ?>
                    <?php $time = attribuerJourEtHeureSimules($index, $c['titre']); ?>
                    
                    <div class="card agenda-card" data-jour="<?= $time['jour'] ?>">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:start;">
                                <div class="agenda-time">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <?= $time['debut'] ?> - <?= $time['fin'] ?>
                                </div>
                                <span class="badge badge-neutral" style="font-size:11px; font-weight:700;"><?= $time['jour'] ?></span>
                            </div>
                            
                            <h3 style="margin:12px 0 4px 0; font-size:16px; font-weight:700; color:var(--text-main);"><?= htmlspecialchars($c['titre']) ?></h3>
                            <span style="font-size:11px; font-weight:600; color:var(--primary); background:var(--primary-light); padding:2px 6px; border-radius:4px;">
                                <?= htmlspecialchars($c['categorie']) ?>
                            </span>
                        </div>

                        <div>
                            <div class="agenda-meta">
                                <span>📍 <strong><?= $time['salle'] ?></strong></span>
                                <?php if ($mon_role === 'etudiant'): ?>
                                    <span>👤 Prof: <strong><?= htmlspecialchars($c['prof_nom'] ?? 'Enseignant') ?></strong></span>
                                <?php else: ?>
                                    <span>👥 Classe: <strong><?= htmlspecialchars($c['classe_nom']) ?></strong></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function filtrerJour(jourCible) {
            var boutons = document.querySelectorAll('.tab-btn');
            boutons.forEach(function(btn) { 
                btn.classList.remove('active'); 
            });
            event.currentTarget.classList.add('active');

            var cartes = document.querySelectorAll('.agenda-card');
            cartes.forEach(function(carte) {
                if (jourCible === 'Tous' || carte.getAttribute('data-jour') === jourCible) {
                    carte.style.display = 'flex';
                } else {
                    carte.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>