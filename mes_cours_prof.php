<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$prof_id = $_SESSION['utilisateur_id'];

// Cours dispensés par ce professeur, avec le nom du groupe
$stmt_cours = $db->prepare("
    SELECT c.*, g.nom AS groupe_nom, u.nom AS prof_nom, u.prenom AS prof_prenom
    FROM cours c 
    JOIN groupes g ON c.groupe_id = g.id
    LEFT JOIN utilisateurs u ON c.professeur_id = u.id
    WHERE c.professeur_id = ? 
    ORDER BY c.categorie, c.titre
");
$stmt_cours->execute([$prof_id]);
$liste_cours = $stmt_cours->fetchAll(PDO::FETCH_ASSOC);

// Nombre total d'étudiants dans toutes ses classes
$nb_etudiants = $db->query("
    SELECT COUNT(DISTINCT u.id) 
    FROM utilisateurs u
    WHERE u.role = 'etudiant'
      AND u.groupe_id IN (SELECT groupe_id FROM cours WHERE professeur_id = $prof_id)
")->fetchColumn();

// Nombre de catégories distinctes
$categories = array_unique(array_column($liste_cours, 'categorie'));
$nb_categories = count(array_filter($categories));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Cours - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ===== Statistiques rapides ===== */
        .cours-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .cours-stat-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px 22px;
        }
        .cours-stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e0e7ff;
            color: #4f46e5;
            flex-shrink: 0;
        }
        .cours-stat-info h2 {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .cours-stat-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }

        /* ===== Filtres par catégorie ===== */
        .filter-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .filter-btn {
            padding: 6px 16px;
            border-radius: 20px;
            border: 1.5px solid var(--border, #e5e7eb);
            background: var(--bg-body, #f9fafb);
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.18s;
            font-family: inherit;
        }
        .filter-btn:hover,
        .filter-btn.actif {
            background: var(--primary, #4f46e5);
            border-color: var(--primary, #4f46e5);
            color: #fff;
        }

        /* ===== Grille de cours ===== */
        .cours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 22px;
        }

        /* ===== Carte de cours ===== */
        .cours-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .cours-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(79, 70, 229, 0.1);
        }
        .cours-card-top { flex: 1; }

        .cours-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .cours-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-main);
            margin: 0 0 8px 0;
            line-height: 1.3;
        }

        .cours-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }
        .cours-meta-item {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            color: var(--text-muted);
        }
        .cours-meta-item svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            color: var(--primary, #4f46e5);
            opacity: 0.7;
        }

        .cours-description {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 18px;
        }

        .cours-card-footer {
            display: flex;
            gap: 10px;
            padding-top: 14px;
            border-top: 1px solid var(--border, #e5e7eb);
        }
        .btn-cours-detail {
            flex: 1;
            text-align: center;
            padding: 9px 14px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.18s;
        }
        .btn-cours-primary {
            background: var(--primary, #4f46e5);
            color: #fff;
        }
        .btn-cours-primary:hover { opacity: 0.88; }
        .btn-cours-ghost {
            background: var(--bg-body, #f3f4f6);
            color: var(--text-main);
            border: 1.5px solid var(--border, #e5e7eb);
        }
        .btn-cours-ghost:hover { background: #e9eaf0; }

        /* ===== Lien groupe ===== */
        .groupe-link {
            color: var(--primary, #4f46e5);
            font-weight: 700;
            text-decoration: none;
            border-bottom: 1.5px solid transparent;
            transition: border-color 0.18s, color 0.18s;
        }
        .groupe-link:hover {
            border-bottom-color: var(--primary, #4f46e5);
            color: #3730a3;
        }

        /* ===== État vide ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-state svg {
            width: 48px;
            height: 48px;
            margin-bottom: 16px;
            opacity: 0.35;
        }
        .empty-state p { font-size: 15px; margin: 0; }

        /* ===== Carte masquée (filtre) ===== */
        .cours-card.hidden { display: none; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">

        <!-- En-tête -->
        <div style="margin-bottom: 28px;">
            <h1 style="color:var(--primary); margin:0 0 6px 0;">Mes Cours</h1>
            <p style="color:var(--text-muted); margin:0;">Consultez l'ensemble des matières que vous enseignez.</p>
        </div>

        <!-- Statistiques rapides -->
        <div class="cours-stats">
            <div class="card cours-stat-card">
                <div class="cours-stat-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="22" height="22">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253" />
                    </svg>
                </div>
                <div class="cours-stat-info">
                    <h2>Cours dispensés</h2>
                    <div class="cours-stat-value"><?= count($liste_cours) ?></div>
                </div>
            </div>

            <div class="card cours-stat-card">
                <div class="cours-stat-icon" style="background:#d1fae5; color:#059669;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="22" height="22">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div class="cours-stat-info">
                    <h2>Étudiants encadrés</h2>
                    <div class="cours-stat-value"><?= $nb_etudiants ?></div>
                </div>
            </div>

            <div class="card cours-stat-card">
                <div class="cours-stat-icon" style="background:#fef3c7; color:#d97706;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="22" height="22">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
                <div class="cours-stat-info">
                    <h2>Catégories</h2>
                    <div class="cours-stat-value"><?= $nb_categories ?></div>
                </div>
            </div>
        </div>

        <?php if (empty($liste_cours)): ?>
            <div class="card">
                <div class="empty-state">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13" />
                    </svg>
                    <p>Aucun cours ne vous a encore été assigné.</p>
                </div>
            </div>
        <?php else: ?>

            <!-- Filtres par catégorie -->
            <?php if ($nb_categories > 1): ?>
                <div class="filter-bar">
                    <button class="filter-btn actif" onclick="filtrerCours('tous', this)">Tous (<?= count($liste_cours) ?>)</button>
                    <?php foreach(array_filter($categories) as $cat): ?>
                        <?php $nb_cat = count(array_filter($liste_cours, fn($c) => $c['categorie'] === $cat)); ?>
                        <button class="filter-btn" onclick="filtrerCours('<?= htmlspecialchars(addslashes($cat)) ?>', this)">
                            <?= htmlspecialchars($cat) ?> (<?= $nb_cat ?>)
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Grille de cours -->
            <div class="cours-grid" id="coursGrid">
                <?php foreach ($liste_cours as $cours): ?>
                    <div class="card cours-card" data-categorie="<?= htmlspecialchars($cours['categorie'] ?? '') ?>">
                        <div class="cours-card-top">

                            <div class="cours-card-header">
                                <?php if (!empty($cours['categorie'])): ?>
                                    <span class="badge badge-neutral"><?= htmlspecialchars($cours['categorie']) ?></span>
                                <?php else: ?>
                                    <span></span>
                                <?php endif; ?>
                            </div>

                            <h3 class="cours-title"><?= htmlspecialchars($cours['titre']) ?></h3>

                            <div class="cours-meta">
                                <!-- Groupe/Classe — rendu cliquable -->
                                <div class="cours-meta-item">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                                    </svg>
                                    Classe :&nbsp;
                                    <a href="detail_groupe.php?id=<?= $cours['groupe_id'] ?>" class="groupe-link">
                                        <?= htmlspecialchars($cours['groupe_nom']) ?>
                                    </a>
                                </div>
                                <!-- Horaire -->
                                <?php if (!empty($cours['jour'])): ?>
                                    <div class="cours-meta-item">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <?= htmlspecialchars($cours['jour']) ?>
                                        <?php if (!empty($cours['heure_debut'])): ?>
                                            — <?= htmlspecialchars(substr($cours['heure_debut'], 0, 5)) ?>
                                            <?php if (!empty($cours['heure_fin'])): ?>
                                                → <?= htmlspecialchars(substr($cours['heure_fin'], 0, 5)) ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <!-- Salle -->
                                <?php if (!empty($cours['salle'])): ?>
                                    <div class="cours-meta-item">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <?= htmlspecialchars($cours['salle']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <p class="cours-description">
                                <?php if (!empty($cours['description'])): ?>
                                    <?= htmlspecialchars(strlen($cours['description']) > 110 ? substr($cours['description'], 0, 110) . '…' : $cours['description']) ?>
                                <?php else: ?>
                                    <em>Aucune description fournie.</em>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="cours-card-footer">
                            <a href="cours_detail_prof.php?id=<?= $cours['id'] ?>" class="btn-cours-detail btn-cours-primary">
                                Voir le détail
                            </a>
                            <a href="faire_appel.php?cours_id=<?= $cours['id'] ?>" class="btn-cours-detail btn-cours-ghost">
                                Faire l'appel
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

    <script>
        function filtrerCours(categorie, btn) {
            // Mise à jour des boutons actifs
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('actif'));
            btn.classList.add('actif');

            // Affichage/masquage des cartes
            document.querySelectorAll('.cours-card').forEach(card => {
                if (categorie === 'tous' || card.dataset.categorie === categorie) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        }
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>