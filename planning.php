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
// RÉCUPÉRATION DU PLANNING RÉEL
// ==========================================
// Astuce Pro : Trier les jours chronologiquement et non alphabétiquement
$order_sql = "
    ORDER BY 
    CASE c.jour 
        WHEN 'Lundi' THEN 1 
        WHEN 'Mardi' THEN 2 
        WHEN 'Mercredi' THEN 3 
        WHEN 'Jeudi' THEN 4 
        WHEN 'Vendredi' THEN 5 
        ELSE 6 
    END, 
    c.heure_debut ASC
";

if ($mon_role === 'etudiant') {
    $stmt_plan = $db->prepare("
        SELECT c.*, u.nom as prof_nom, u.prenom as prof_prenom, g.nom as classe_nom 
        FROM cours c 
        LEFT JOIN utilisateurs u ON c.professeur_id = u.id 
        JOIN groupes g ON c.groupe_id = g.id
        WHERE c.groupe_id = (SELECT groupe_id FROM utilisateurs WHERE id = ?)
        " . $order_sql . "
    ");
    $stmt_plan->execute([$mon_id]);
} elseif ($mon_role === 'professeur') {
    $stmt_plan = $db->prepare("
        SELECT c.*, g.nom as classe_nom 
        FROM cours c 
        JOIN groupes g ON c.groupe_id = g.id 
        WHERE c.professeur_id = ?
        " . $order_sql . "
    ");
    $stmt_plan->execute([$mon_id]);
} else { 
    // L'administrateur
    $stmt_plan = $db->prepare("
        SELECT c.*, u.nom as prof_nom, g.nom as classe_nom 
        FROM cours c 
        LEFT JOIN utilisateurs u ON c.professeur_id = u.id 
        JOIN groupes g ON c.groupe_id = g.id
        " . $order_sql . "
    ");
    $stmt_plan->execute();
}
$tous_les_cours = $stmt_plan->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planning Officiel - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <div>
                <h1>Mon Emploi du Temps</h1>
                <p style="color:var(--text-muted); margin:0;">Données synchronisées en temps réel avec la scolarité.</p>
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
                <?php foreach($tous_les_cours as $c): ?>
                    
                    <div class="card agenda-card" data-jour="<?= htmlspecialchars($c['jour'] ?? 'Lundi') ?>">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:start;">
                                <div class="agenda-time">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <?= htmlspecialchars(substr($c['heure_debut'] ?? '00:00', 0, 5)) ?> - <?= htmlspecialchars(substr($c['heure_fin'] ?? '00:00', 0, 5)) ?>
                                </div>
                                <span class="badge badge-neutral" style="font-size:11px; font-weight:700;">
                                    <?= htmlspecialchars($c['jour'] ?? 'Lundi') ?>
                                </span>
                            </div>
                            
                            <h3 style="margin:12px 0 4px 0; font-size:16px; font-weight:700; color:var(--text-main);">
                                <?= htmlspecialchars($c['titre']) ?>
                            </h3>
                            <span style="font-size:11px; font-weight:600; color:var(--primary); background:var(--primary-light); padding:2px 6px; border-radius:4px;">
                                <?= htmlspecialchars($c['categorie'] ?? 'Général') ?>
                            </span>
                        </div>

                        <div>
                            <div class="agenda-meta">
                                <span>📍 <strong><?= htmlspecialchars($c['salle'] ?? 'Non assignée') ?></strong></span>
                                <?php if ($mon_role === 'etudiant'): ?>
                                    <span>👤 Prof: <strong><?= htmlspecialchars($c['prof_nom'] ?? 'Enseignant') ?></strong></span>
                                <?php else: ?>
                                    <span>👥 Classe: <strong><?= htmlspecialchars($c['classe_nom'] ?? '-') ?></strong></span>
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
<?php include 'footer.php'; ?>
</body>
</html>