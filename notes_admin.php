<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: connexion.php");
    exit();
}
require_once 'db.php';

// ==========================================
// CHARGEMENT DES DONNÉES
// ==========================================

// Tous les groupes
$stmt_groupes = $db->query("SELECT * FROM groupes ORDER BY nom");
$groupes = $stmt_groupes->fetchAll();

// Groupe sélectionné
$groupe_sel = isset($_GET['groupe_id']) ? intval($_GET['groupe_id']) : null;
$groupe_info = null;
$cours_du_groupe = [];
$cours_sel = isset($_GET['cours_id']) ? intval($_GET['cours_id']) : null;
$notes_cours = [];
$cours_info = null;

if ($groupe_sel) {
    $chk = $db->prepare("SELECT * FROM groupes WHERE id = ?");
    $chk->execute([$groupe_sel]);
    $groupe_info = $chk->fetch();

    if ($groupe_info) {
        // Cours du groupe
        $stmt_cours = $db->prepare("
            SELECT c.*, u.nom AS prof_nom, u.prenom AS prof_prenom
            FROM cours c
            JOIN utilisateurs u ON c.professeur_id = u.id
            WHERE c.groupe_id = ?
            ORDER BY c.titre
        ");
        $stmt_cours->execute([$groupe_sel]);
        $cours_du_groupe = $stmt_cours->fetchAll();
    }
}

if ($cours_sel && $groupe_sel) {
    // Vérifier que ce cours appartient bien au groupe
    $chk2 = $db->prepare("
        SELECT c.*, u.nom AS prof_nom, u.prenom AS prof_prenom, g.nom AS groupe_nom
        FROM cours c
        JOIN utilisateurs u ON c.professeur_id = u.id
        JOIN groupes g ON c.groupe_id = g.id
        WHERE c.id = ? AND c.groupe_id = ?
    ");
    $chk2->execute([$cours_sel, $groupe_sel]);
    $cours_info = $chk2->fetch();

    if ($cours_info) {
        $stmt_notes = $db->prepare("
            SELECT n.id AS note_id, n.valeur_note, n.commentaire,
                   u.id AS etudiant_id, u.nom, u.prenom
            FROM utilisateurs u
            LEFT JOIN notes n ON n.etudiant_id = u.id AND n.cours_id = ?
            WHERE u.role = 'etudiant'
              AND u.groupe_id = ?
            ORDER BY u.nom, u.prenom
        ");
        $stmt_notes->execute([$cours_sel, $groupe_sel]);
        $notes_cours = $stmt_notes->fetchAll();
    }
}

// ==========================================
// STATISTIQUES GLOBALES ADMIN
// ==========================================

// Nombre total de groupes
$total_groupes = count($groupes);

// Nombre total d'étudiants
$stmt_etudiants = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'etudiant'");
$total_etudiants = $stmt_etudiants->fetchColumn();

// Nombre total de notes
$stmt_total_notes = $db->query("SELECT COUNT(*) FROM notes");
$total_notes = $stmt_total_notes->fetchColumn();

// Moyenne globale
$stmt_moy = $db->query("SELECT AVG(valeur_note) FROM notes");
$moy_globale = round((float) $stmt_moy->fetchColumn(), 2);

// Si un groupe est sélectionné : stats du groupe
if ($groupe_sel && $groupe_info) {
    $stmt_etudiants_grp = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE role = 'etudiant' AND groupe_id = ?");
    $stmt_etudiants_grp->execute([$groupe_sel]);
    $nb_etudiants_groupe = $stmt_etudiants_grp->fetchColumn();

    $stmt_moy_grp = $db->prepare("
        SELECT AVG(n.valeur_note)
        FROM notes n
        JOIN cours c ON n.cours_id = c.id
        WHERE c.groupe_id = ?
    ");
    $stmt_moy_grp->execute([$groupe_sel]);
    $moy_groupe = round((float) $stmt_moy_grp->fetchColumn(), 2);

    $stmt_notes_grp = $db->prepare("
        SELECT COUNT(n.id)
        FROM notes n
        JOIN cours c ON n.cours_id = c.id
        WHERE c.groupe_id = ?
    ");
    $stmt_notes_grp->execute([$groupe_sel]);
    $notes_groupe = $stmt_notes_grp->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Consultation des Notes par Groupe — SmartCampus Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Stats ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-mini {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 20px;
        }
        .stat-mini-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-mini-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 3px;
        }
        .stat-mini-val {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-main);
        }

        /* ── Sélecteurs en ligne ── */
        .selectors-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        /* ── Badge de note ── */
        .note-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
        }
        .note-good  { background: #d1fae5; color: #065f46; }
        .note-mid   { background: #fef3c7; color: #92400e; }
        .note-bad   { background: #fee2e2; color: #991b1b; }
        .note-empty { background: #f3f4f6; color: #9ca3af; font-weight: 500; font-size: 13px; }

        /* ── Étiquette admin (lecture seule) ── */
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ede9fe;
            color: #6d28d9;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* ── Barre de progression ── */
        .progress-bar-wrap {
            background: #f0f0f3;
            border-radius: 99px;
            height: 6px;
            width: 120px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin-left: 8px;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 99px;
            background: var(--primary, #6366f1);
        }

        /* ── Info prof ── */
        .prof-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0f9ff;
            color: #0369a1;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        .empty-state svg { opacity: 0.3; margin-bottom: 12px; }

        /* ── Note row ── */
        .note-row td { vertical-align: middle; }

        /* ── Résumé du cours ── */
        .course-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }
        .course-summary-left h2 { margin: 0 0 6px; }
        .course-meta { font-size: 13px; color: var(--text-muted); display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

        /* ── Distribution des notes ── */
        .dist-row {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .dist-item {
            flex: 1;
            min-width: 90px;
            background: #f9fafb;
            border-radius: 12px;
            padding: 12px 14px;
            text-align: center;
        }
        .dist-item .dist-count { font-size: 22px; font-weight: 800; }
        .dist-item .dist-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-top: 2px; }
        .dist-item.green { background: #d1fae5; }
        .dist-item.green .dist-count { color: #065f46; }
        .dist-item.yellow { background: #fef3c7; }
        .dist-item.yellow .dist-count { color: #92400e; }
        .dist-item.red { background: #fee2e2; }
        .dist-item.red .dist-count { color: #991b1b; }
        .dist-item.gray { background: #f3f4f6; }
        .dist-item.gray .dist-count { color: #6b7280; }

        @media (max-width: 700px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .selectors-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">

        <!-- En-tête -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="color:var(--primary); margin:0 0 6px;">Consultation des Notes</h1>
                <p style="color:var(--text-muted); margin:0;">Vue en lecture seule — sélectionnez un groupe puis un cours.</p>
            </div>
            <span class="admin-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Lecture seule
            </span>
        </div>

        <!-- Statistiques globales -->
        <div class="stats-row">
            <div class="card stat-mini">
                <div class="stat-mini-icon" style="background:#ede9fe; color:#7c3aed;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-mini-label">Groupes</div>
                    <div class="stat-mini-val"><?= $total_groupes ?></div>
                </div>
            </div>
            <div class="card stat-mini">
                <div class="stat-mini-icon" style="background:#dbeafe; color:#2563eb;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-mini-label">Étudiants</div>
                    <div class="stat-mini-val"><?= $total_etudiants ?></div>
                </div>
            </div>
            <div class="card stat-mini">
                <div class="stat-mini-icon" style="background:#d1fae5; color:#10b981;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-mini-label">Notes enregistrées</div>
                    <div class="stat-mini-val"><?= $total_notes ?></div>
                </div>
            </div>
            <div class="card stat-mini">
                <div class="stat-mini-icon" style="background:#fef3c7; color:#d97706;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-mini-label">Moyenne globale</div>
                    <div class="stat-mini-val"><?= $total_notes > 0 ? number_format($moy_globale, 2, ',', ' ') . ' /20' : '—' ?></div>
                </div>
            </div>
        </div>

        <!-- Sélecteurs -->
        <div class="selectors-row">
            <!-- Sélecteur de groupe -->
            <div class="card">
                <form method="GET" id="form-groupe">
                    <label>
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>
                        </svg>
                        Groupe
                    </label>
                    <select name="groupe_id" onchange="this.form.submit()">
                        <option value="">-- Choisir un groupe --</option>
                        <?php foreach($groupes as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $groupe_sel == $g['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Sélecteur de cours (visible uniquement si groupe choisi) -->
            <div class="card" <?= !$groupe_sel ? 'style="opacity:0.5; pointer-events:none;"' : '' ?>>
                <form method="GET">
                    <input type="hidden" name="groupe_id" value="<?= $groupe_sel ?>">
                    <label>
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5s3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18s-3.332.477-4.5 1.253"/>
                        </svg>
                        Cours
                    </label>
                    <select name="cours_id" onchange="this.form.submit()">
                        <option value="">-- Choisir un cours --</option>
                        <?php foreach($cours_du_groupe as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $cours_sel == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['titre']) ?> — <?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Statistiques du groupe sélectionné -->
        <?php if ($groupe_sel && $groupe_info): ?>
        <div class="card" style="margin-bottom: 20px; border-left: 4px solid var(--primary, #6366f1);">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                <div>
                    <span style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted);">Groupe sélectionné</span>
                    <h3 style="margin: 4px 0 0; font-size:18px;"><?= htmlspecialchars($groupe_info['nom']) ?></h3>
                </div>
                <div style="display:flex; gap:24px; flex-wrap:wrap;">
                    <div style="text-align:center;">
                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted);">Étudiants</div>
                        <div style="font-size:20px; font-weight:700;"><?= $nb_etudiants_groupe ?></div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted);">Cours</div>
                        <div style="font-size:20px; font-weight:700;"><?= count($cours_du_groupe) ?></div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted);">Notes</div>
                        <div style="font-size:20px; font-weight:700;"><?= $notes_groupe ?></div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted);">Moy. groupe</div>
                        <div style="font-size:20px; font-weight:700;"><?= $notes_groupe > 0 ? number_format($moy_groupe, 2, ',', ' ') . ' /20' : '—' ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tableau des notes -->
        <?php if ($cours_sel && $cours_info): ?>
            <?php
                $notes_saisies = array_filter($notes_cours, fn($n) => $n['note_id'] !== null);
                $nb_saisies = count($notes_saisies);
                $nb_total   = count($notes_cours);
                $pct = $nb_total > 0 ? round($nb_saisies / $nb_total * 100) : 0;

                // Distribution
                $nb_good  = 0; $nb_mid = 0; $nb_bad = 0;
                foreach ($notes_saisies as $n) {
                    $v = floatval($n['valeur_note']);
                    if ($v >= 14) $nb_good++;
                    elseif ($v >= 10) $nb_mid++;
                    else $nb_bad++;
                }
                $nb_absent = $nb_total - $nb_saisies;

                // Moyenne du cours
                $vals = array_map(fn($n) => floatval($n['valeur_note']), $notes_saisies);
                $moy_cours = count($vals) > 0 ? round(array_sum($vals) / count($vals), 2) : null;
            ?>
            <div class="card">
                <!-- En-tête du cours -->
                <div class="course-summary">
                    <div class="course-summary-left">
                        <h2><?= htmlspecialchars($cours_info['titre']) ?></h2>
                        <div class="course-meta">
                            <span class="prof-chip">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <?= htmlspecialchars($cours_info['prof_prenom'] . ' ' . $cours_info['prof_nom']) ?>
                            </span>
                            <span>Groupe : <strong><?= htmlspecialchars($cours_info['groupe_nom']) ?></strong></span>
                            <span>
                                <?= $nb_saisies ?> / <?= $nb_total ?> notes saisies
                                <span class="progress-bar-wrap">
                                    <span class="progress-bar-fill" style="width:<?= $pct ?>%;"></span>
                                </span>
                                <strong><?= $pct ?>%</strong>
                            </span>
                            <?php if ($moy_cours !== null): ?>
                                <span>Moyenne du cours : <strong><?= number_format($moy_cours, 2, ',', ' ') ?> /20</strong></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Distribution rapide -->
                <?php if ($nb_saisies > 0): ?>
                <div class="dist-row">
                    <div class="dist-item green">
                        <div class="dist-count"><?= $nb_good ?></div>
                        <div class="dist-label">≥ 14 / 20</div>
                    </div>
                    <div class="dist-item yellow">
                        <div class="dist-count"><?= $nb_mid ?></div>
                        <div class="dist-label">10 – 13</div>
                    </div>
                    <div class="dist-item red">
                        <div class="dist-count"><?= $nb_bad ?></div>
                        <div class="dist-label">&lt; 10 / 20</div>
                    </div>
                    <div class="dist-item gray">
                        <div class="dist-count"><?= $nb_absent ?></div>
                        <div class="dist-label">Non notés</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tableau -->
                <?php if (empty($notes_cours)): ?>
                    <div class="empty-state">
                        <p>Aucun étudiant dans cette classe.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>#</th>
                            <th>Étudiant</th>
                            <th>Note / 20</th>
                            <th>Appréciation</th>
                        </tr>
                        <?php foreach($notes_cours as $i => $n):
                            $init = strtoupper(substr($n['prenom'], 0, 1) . substr($n['nom'], 0, 1));
                            $av   = glob("uploads/avatars/avatar_" . $n['etudiant_id'] . ".*");
                            $hasNote = $n['note_id'] !== null;
                            $val = $hasNote ? floatval($n['valeur_note']) : null;
                            $badgeCls = !$hasNote ? 'note-empty' : ($val >= 14 ? 'note-good' : ($val >= 10 ? 'note-mid' : 'note-bad'));
                            $badgeTxt = !$hasNote ? 'Non noté' : number_format($val, 2, ',', ' ');
                        ?>
                        <tr class="note-row">
                            <td style="color:var(--text-muted); font-size:13px; width:36px;"><?= $i + 1 ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div class="avatar-small" style="width:30px;height:30px;font-size:10px;box-shadow:none;">
                                        <?php if(!empty($av)): ?>
                                            <img src="<?= $av[0] ?>" alt="">
                                        <?php else: ?>
                                            <?= $init ?>
                                        <?php endif; ?>
                                    </div>
                                    <strong><?= htmlspecialchars($n['nom'] . ' ' . $n['prenom']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="note-badge <?= $badgeCls ?>"><?= $badgeTxt ?></span>
                            </td>
                            <td style="font-size:13px; color:var(--text-muted); font-style:italic; max-width:260px;">
                                <?= $hasNote && !empty($n['commentaire']) ? htmlspecialchars($n['commentaire']) : '—' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($cours_sel && !$cours_info): ?>
            <div class="alert alert-error"><span>⚠️ Cours introuvable ou accès non autorisé.</span></div>

        <?php elseif ($groupe_sel && $groupe_info && empty($cours_du_groupe)): ?>
            <div class="card">
                <div class="empty-state">
                    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/>
                    </svg>
                    <p>Aucun cours n'est encore associé à ce groupe.</p>
                </div>
            </div>

        <?php elseif ($groupe_sel && $groupe_info): ?>
            <div class="card">
                <div class="empty-state">
                    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p>Sélectionnez un cours pour afficher les notes.</p>
                </div>
            </div>

        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>
                    </svg>
                    <p>Sélectionnez un groupe pour commencer.</p>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <?php include 'footer.php'; ?>
</body>
</html>