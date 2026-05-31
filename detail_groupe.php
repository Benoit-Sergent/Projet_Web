<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['professeur', 'administrateur'])) { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$prof_id = $_SESSION['utilisateur_id'];
$is_admin = $_SESSION['role'] === 'administrateur';

// Vérification qu'un ID de groupe a bien été transmis
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: mes_cours.php");
    exit();
}
$groupe_id = (int) $_GET['id'];


if ($is_admin) {
    $stmt_check = $db->prepare("SELECT id, nom FROM groupes WHERE id = ?");
    $stmt_check->execute([$groupe_id]);
} else { // Vérification que ce groupe appartient bien à un cours de ce professeur
    $stmt_check = $db->prepare("
        SELECT g.id, g.nom 
        FROM groupes g
        JOIN cours c ON c.groupe_id = g.id
        WHERE g.id = ? AND c.professeur_id = ?
        LIMIT 1
    ");
    $stmt_check->execute([$groupe_id, $prof_id]);
}
$groupe = $stmt_check->fetch(PDO::FETCH_ASSOC);

if (!$groupe) {
    header("Location: mes_cours.php");
    exit();
}

// Récupération de tous les étudiants du groupe
$stmt_etudiants = $db->prepare("
    SELECT u.id, u.nom, u.prenom, u.email
    FROM utilisateurs u
    WHERE u.role = 'etudiant' AND u.groupe_id = ?
    ORDER BY u.nom, u.prenom
");
$stmt_etudiants->execute([$groupe_id]);
$etudiants = $stmt_etudiants->fetchAll(PDO::FETCH_ASSOC);

if ($is_admin) {
    $stmt_cours = $db->prepare("
        SELECT c.titre, c.categorie, c.jour, c.heure_debut, c.heure_fin, c.salle
        FROM cours c
        WHERE c.groupe_id = ?
        ORDER BY c.titre
    ");
    $stmt_cours->execute([$groupe_id]);
} else {
// Cours associés à ce groupe enseignés par ce prof
    $stmt_cours = $db->prepare("
        SELECT titre, categorie, jour, heure_debut, heure_fin, salle
        FROM cours
        WHERE groupe_id = ? AND professeur_id = ?
        ORDER BY titre
    ");
    $stmt_cours->execute([$groupe_id, $prof_id]);
}
$cours_groupe = $stmt_cours->fetchAll(PDO::FETCH_ASSOC);

$nb_etudiants = count($etudiants);

// Génération des initiales pour l'avatar
function getInitials($prenom, $nom) {
    return strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
}

// Palette de couleurs pour les avatars (basée sur l'ID)
$avatar_colors = [
    ['bg' => '#e0e7ff', 'text' => '#4338ca'],
    ['bg' => '#d1fae5', 'text' => '#065f46'],
    ['bg' => '#fef3c7', 'text' => '#92400e'],
    ['bg' => '#fce7f3', 'text' => '#9d174d'],
    ['bg' => '#dbeafe', 'text' => '#1e40af'],
    ['bg' => '#ede9fe', 'text' => '#5b21b6'],
    ['bg' => '#ffedd5', 'text' => '#9a3412'],
    ['bg' => '#ecfdf5', 'text' => '#064e3b'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Groupe <?= htmlspecialchars($groupe['nom']) ?> - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ===== Lien retour ===== */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 24px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary); }

        /* ===== Bannière groupe ===== */
        .groupe-banner {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: 16px;
            padding: 36px 40px;
            color: white;
            margin-bottom: 28px;
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        .groupe-banner-left h1 {
            margin: 0 0 8px 0;
            color: white;
            font-size: 30px;
        }
        .groupe-banner-left p {
            margin: 0;
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }
        .groupe-banner-stat {
            text-align: center;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            padding: 16px 28px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .groupe-banner-stat-value {
            font-size: 36px;
            font-weight: 800;
            color: white;
            display: block;
            line-height: 1;
        }
        .groupe-banner-stat-label {
            font-size: 12px;
            color: rgba(255,255,255,0.75);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 4px;
            display: block;
        }

        /* ===== Barre de recherche ===== */
        .search-bar-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        .search-bar-wrapper svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: var(--text-muted);
            pointer-events: none;
        }
        .search-input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 1.5px solid var(--border, #e5e7eb);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            background: var(--bg-card, #fff);
            color: var(--text-main);
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary, #4f46e5);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .search-input::placeholder { color: var(--text-muted); }

        /* ===== Compteur ===== */
        .list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .list-header h2 { margin: 0; font-size: 17px; }
        .count-badge {
            background: var(--primary, #4f46e5);
            color: white;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
        }

        /* ===== Grille étudiants ===== */
        .etudiants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }

        /* ===== Carte étudiant ===== */
        .etudiant-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border: 1.5px solid var(--border, #e5e7eb);
            border-radius: 12px;
            background: var(--bg-card, #fff);
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
            cursor: default;
        }
        .etudiant-card:hover {
            border-color: var(--primary, #4f46e5);
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.08);
            transform: translateY(-1px);
        }
        .etudiant-card.hidden { display: none; }

        .etudiant-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 700;
            flex-shrink: 0;
            letter-spacing: 0.5px;
        }
        .etudiant-info { flex: 1; min-width: 0; }
        .etudiant-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            margin: 0 0 3px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .etudiant-email {
            font-size: 12px;
            color: var(--text-muted);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .etudiant-num {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            flex-shrink: 0;
            opacity: 0.5;
        }

        /* ===== État vide ===== */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        .empty-state .empty-icon { font-size: 40px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; margin: 0; }

        /* ===== Section cours liés ===== */
        .cours-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 6px;
        }
        .cours-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--bg-body, #f3f4f6);
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
        }
        .cours-pill .pill-cat {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--primary, #4f46e5);
        }

        /* ===== No-result ===== */
        #no-result {
            display: none;
            text-align: center;
            padding: 30px;
            color: var(--text-muted);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">

        <a href="mes_cours.php" class="back-link">
            ← Retour à mes cours
        </a>

        <!-- Bannière -->
        <div class="groupe-banner">
            <div class="groupe-banner-left">
                <span class="badge" style="background: rgba(255,255,255,0.25); color: white; margin-bottom: 10px; display: inline-block;">Groupe</span>
                <h1><?= htmlspecialchars($groupe['nom']) ?></h1>
                <p>Liste complète des étudiants inscrits dans ce groupe.</p>
            </div>
            <div class="groupe-banner-stat">
                <span class="groupe-banner-stat-value"><?= $nb_etudiants ?></span>
                <span class="groupe-banner-stat-label">étudiant<?= $nb_etudiants > 1 ? 's' : '' ?></span>
            </div>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr; align-items: start;">

            <!-- Colonne gauche : liste des étudiants -->
            <div class="card">
                <div class="list-header">
                    <h2>Étudiants du groupe</h2>
                    <span class="count-badge" id="visibleCount"><?= $nb_etudiants ?></span>
                </div>

                <!-- Barre de recherche -->
                <?php if ($nb_etudiants > 0): ?>
                <div class="search-bar-wrapper">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z" />
                    </svg>
                    <input 
                        type="text" 
                        class="search-input" 
                        id="searchInput"
                        placeholder="Rechercher un étudiant par nom ou prénom…"
                        oninput="filterEtudiants(this.value)"
                    >
                </div>
                <?php endif; ?>

                <?php if ($nb_etudiants === 0): ?>
                    <div class="empty-state">
                        <div class="empty-icon">👥</div>
                        <p>Aucun étudiant n'est inscrit dans ce groupe pour l'instant.</p>
                    </div>
                <?php else: ?>
                    <div class="etudiants-grid" id="etudiantsGrid">
                        <?php foreach ($etudiants as $index => $etudiant): 
                            $color = $avatar_colors[$etudiant['id'] % count($avatar_colors)];
                            $initiales = getInitials($etudiant['prenom'], $etudiant['nom']);
                        ?>
                            <div 
                                class="etudiant-card" 
                                data-search="<?= strtolower(htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom'])) ?>"
                            >
                                <div 
                                    class="etudiant-avatar" 
                                    style="background:<?= $color['bg'] ?>; color:<?= $color['text'] ?>;"
                                >
                                    <?= $initiales ?>
                                </div>
                                <div class="etudiant-info">
                                    <p class="etudiant-name"><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></p>
                                    <p class="etudiant-email"><?= htmlspecialchars($etudiant['email'] ?? '—') ?></p>
                                </div>
                                <span class="etudiant-num">#<?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p id="no-result">Aucun étudiant ne correspond à votre recherche.</p>
                <?php endif; ?>
            </div>

            <!-- Colonne droite : cours liés -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h2>Mes cours dans ce groupe</h2>
                    </div>
                    <?php if (empty($cours_groupe)): ?>
                        <p style="color: var(--text-muted); font-size: 14px;">Aucun cours associé.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($cours_groupe as $c): ?>
                            <div style="padding: 14px; border: 1px solid var(--border); border-radius: 10px;">
                                <?php if (!empty($c['categorie'])): ?>
                                    <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary);">
                                        <?= htmlspecialchars($c['categorie']) ?>
                                    </span>
                                <?php endif; ?>
                                <p style="font-weight: 700; font-size: 14px; color: var(--text-main); margin: 4px 0 8px 0;">
                                    <?= htmlspecialchars($c['titre']) ?>
                                </p>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <?php if (!empty($c['jour'])): ?>
                                        <span style="font-size: 12px; color: var(--text-muted);">
                                            📅 <?= htmlspecialchars($c['jour']) ?>
                                            <?php if (!empty($c['heure_debut'])): ?>
                                                — <?= htmlspecialchars(substr($c['heure_debut'], 0, 5)) ?>
                                                <?php if (!empty($c['heure_fin'])): ?>→ <?= htmlspecialchars(substr($c['heure_fin'], 0, 5)) ?><?php endif; ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($c['salle'])): ?>
                                        <span style="font-size: 12px; color: var(--text-muted);">📍 <?= htmlspecialchars($c['salle']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Info rapide -->
                <div class="card" style="margin-top: 16px;">
                    <div class="card-header"><h2>Infos rapides</h2></div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px;">
                            <span style="color: var(--text-muted);">Effectif total</span>
                            <strong style="color: var(--text-main);"><?= $nb_etudiants ?> étudiant<?= $nb_etudiants > 1 ? 's' : '' ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px;">
                            <span style="color: var(--text-muted);">Cours enseignés</span>
                            <strong style="color: var(--text-main);"><?= count($cours_groupe) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function filterEtudiants(query) {
            const cards = document.querySelectorAll('.etudiant-card');
            const noResult = document.getElementById('no-result');
            const countBadge = document.getElementById('visibleCount');
            const q = query.toLowerCase().trim();
            let visible = 0;

            cards.forEach(card => {
                const name = card.dataset.search || '';
                if (q === '' || name.includes(q)) {
                    card.classList.remove('hidden');
                    visible++;
                } else {
                    card.classList.add('hidden');
                }
            });

            countBadge.textContent = visible;
            noResult.style.display = (visible === 0 && q !== '') ? 'block' : 'none';
        }
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>