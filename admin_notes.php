<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: connexion.php");
    exit();
}
require_once 'db.php';

$message_succes = "";
$message_erreur = "";

// ==========================================
// ACTIONS POST
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'modifier_note') {
        $note_id     = intval($_POST['note_id']);
        $valeur      = floatval($_POST['valeur_note']);
        $commentaire = trim($_POST['commentaire']);

        if ($valeur >= 0 && $valeur <= 20) {
            $stmt = $db->prepare("UPDATE notes SET valeur_note = ?, commentaire = ? WHERE id = ?");
            if ($stmt->execute([$valeur, $commentaire, $note_id])) {
                $message_succes = "Note modifiée avec succès.";
            } else {
                $message_erreur = "Erreur lors de la modification.";
            }
        } else {
            $message_erreur = "La note doit être comprise entre 0 et 20.";
        }
    }

    if ($_POST['action'] === 'supprimer_note') {
        $note_id = intval($_POST['note_id']);
        $db->prepare("DELETE FROM notes WHERE id = ?")->execute([$note_id]);
        $message_succes = "Note supprimée.";
    }
}

// ==========================================
// CHARGEMENT DES DONNÉES
// ==========================================

// Filtres GET
$filtre_groupe = isset($_GET['groupe_id']) ? intval($_GET['groupe_id']) : null;
$filtre_prof   = isset($_GET['prof_id'])   ? intval($_GET['prof_id'])   : null;
$filtre_cours  = isset($_GET['cours_id'])  ? intval($_GET['cours_id'])  : null;

// Listes pour les filtres
$tous_groupes = $db->query("SELECT * FROM groupes ORDER BY nom")->fetchAll();
$tous_profs   = $db->query("SELECT id, nom, prenom FROM utilisateurs WHERE role = 'professeur' ORDER BY nom")->fetchAll();

// Cours filtrés dynamiquement
$where_cours = "1=1";
$params_cours = [];
if ($filtre_groupe) { $where_cours .= " AND c.groupe_id = ?";     $params_cours[] = $filtre_groupe; }
if ($filtre_prof)   { $where_cours .= " AND c.professeur_id = ?"; $params_cours[] = $filtre_prof; }

$stmt_cours = $db->prepare("
    SELECT c.id, c.titre, g.nom as groupe_nom, u.nom as prof_nom, u.prenom as prof_prenom
    FROM cours c
    JOIN groupes g ON c.groupe_id = g.id
    JOIN utilisateurs u ON c.professeur_id = u.id
    WHERE $where_cours
    ORDER BY c.titre
");
$stmt_cours->execute($params_cours);
$tous_cours = $stmt_cours->fetchAll();

// Notes avec les filtres
$where_notes = "1=1";
$params_notes = [];
if ($filtre_groupe) { $where_notes .= " AND g.id = ?";            $params_notes[] = $filtre_groupe; }
if ($filtre_prof)   { $where_notes .= " AND c.professeur_id = ?"; $params_notes[] = $filtre_prof; }
if ($filtre_cours)  { $where_notes .= " AND n.cours_id = ?";      $params_notes[] = $filtre_cours; }

$stmt_notes = $db->prepare("
    SELECT n.id as note_id, n.valeur_note, n.commentaire,
           u.id as etudiant_id, u.nom as etud_nom, u.prenom as etud_prenom,
           c.id as cours_id, c.titre as cours_titre,
           g.nom as groupe_nom,
           p.nom as prof_nom, p.prenom as prof_prenom
    FROM notes n
    JOIN utilisateurs u ON n.etudiant_id = u.id
    JOIN cours c ON n.cours_id = c.id
    JOIN groupes g ON c.groupe_id = g.id
    JOIN utilisateurs p ON c.professeur_id = p.id
    WHERE $where_notes
    ORDER BY g.nom, c.titre, u.nom
");
$stmt_notes->execute($params_notes);
$toutes_notes = $stmt_notes->fetchAll();

// Statistiques globales
$total_notes_global = $db->query("SELECT COUNT(*) FROM notes")->fetchColumn();
$moy_globale        = round((float) $db->query("SELECT AVG(valeur_note) FROM notes")->fetchColumn(), 2);
$nb_moins_10        = $db->query("SELECT COUNT(*) FROM notes WHERE valeur_note < 10")->fetchColumn();
$nb_etudiants       = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'etudiant'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration des Notes - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Grille stats */
        .stats-admin {
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
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .stat-mini-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 3px; }
        .stat-mini-val   { font-size: 22px; font-weight: 700; color: var(--text-main); }

        /* Filtres */
        .filtres-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr) auto;
            gap: 12px;
            align-items: flex-end;
        }
        .filtres-grid select { margin-bottom: 0; }

        /* Tableau */
        .note-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
        }
        .note-good { background: #d1fae5; color: #065f46; }
        .note-mid  { background: #fef3c7; color: #92400e; }
        .note-bad  { background: #fee2e2; color: #991b1b; }

        .btn-edit, .btn-del {
            padding: 5px 11px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-family: inherit;
            transition: opacity 0.15s;
        }
        .btn-edit { background: #e0e7ff; color: #4338ca; }
        .btn-edit:hover { opacity: 0.8; }
        .btn-del  { background: #fee2e2; color: #991b1b; }
        .btn-del:hover  { opacity: 0.8; }

        /* Séparateur de groupe dans le tableau */
        .group-sep td {
            background: #f8fafc;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-muted);
            padding: 8px 12px;
        }

        /* Modale */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,15,25,0.4);
            backdrop-filter: blur(3px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.actif { display: flex; }
        .modal-box {
            background: var(--bg-card, #fff);
            border-radius: 18px;
            padding: 30px;
            width: 440px;
            max-width: 95vw;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
        }
        .modal-title { font-size: 17px; font-weight: 700; margin-bottom: 6px; color: var(--text-main); }
        .modal-sub   { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }

        .empty-state { text-align: center; padding: 50px 20px; color: var(--text-muted); }
        .empty-state svg { opacity: 0.3; margin-bottom: 12px; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 28px;">
            <h1 style="color:var(--primary);">Administration des Notes</h1>
            <p style="color:var(--text-muted); margin:0;">Vue globale de toutes les évaluations. Filtrez, modifiez ou supprimez.</p>
        </div>

        <?php if ($message_succes): ?>
            <div class="alert alert-success"><span>✅ <?= htmlspecialchars($message_succes) ?></span></div>
        <?php endif; ?>
        <?php if ($message_erreur): ?>
            <div class="alert alert-error"><span>⚠️ <?= htmlspecialchars($message_erreur) ?></span></div>
        <?php endif; ?>

        <!-- Statistiques globales -->
        <div class="stats-admin">
            <div class="card stat-mini">
                <div class="stat-mini-icon" style="background:#e0e7ff; color:#4f46e5;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-mini-label">Notes totales</div>
                    <div class="stat-mini-val"><?= $total_notes_global ?></div>
                </div>
            </div>
            <div class="card stat-mini">
                <div class="stat-mini-icon" style="background:#d1fae5; color:#10b981;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-mini-label">Moyenne générale</div>
                    <div class="stat-mini-val"><?= $total_notes_global > 0 ? number_format($moy_globale, 2, ',', ' ') : '—' ?></div>
                </div>
            </div>
            <div class="card stat-mini">
                <div class="stat-mini-icon" style="background:#fee2e2; color:#ef4444;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-mini-label">Notes sous 10</div>
                    <div class="stat-mini-val"><?= $nb_moins_10 ?></div>
                </div>
            </div>
            <div class="card stat-mini">
                <div class="stat-mini-icon" style="background:#fef3c7; color:#d97706;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-mini-label">Étudiants</div>
                    <div class="stat-mini-val"><?= $nb_etudiants ?></div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card" style="margin-bottom: 20px;">
            <form method="GET">
                <div class="filtres-grid">
                    <div>
                        <label>Classe</label>
                        <select name="groupe_id" onchange="this.form.submit()">
                            <option value="">Toutes les classes</option>
                            <?php foreach($tous_groupes as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= $filtre_groupe == $g['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Professeur</label>
                        <select name="prof_id" onchange="this.form.submit()">
                            <option value="">Tous les professeurs</option>
                            <?php foreach($tous_profs as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $filtre_prof == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Cours</label>
                        <select name="cours_id" onchange="this.form.submit()">
                            <option value="">Tous les cours</option>
                            <?php foreach($tous_cours as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $filtre_cours == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['titre'] . ' — ' . $c['groupe_nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <?php if ($filtre_groupe || $filtre_prof || $filtre_cours): ?>
                            <a href="admin_notes.php" class="btn-action" style="display:inline-block; text-decoration:none; padding: 10px 16px; font-size:13px; white-space:nowrap;">
                                ✕ Réinitialiser
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tableau des notes -->
        <div class="card">
            <div class="card-header" style="margin-bottom:16px;">
                <h2>
                    <?= count($toutes_notes) ?> note(s)
                    <?php if ($filtre_groupe || $filtre_prof || $filtre_cours): ?>
                        <span style="font-size:13px; font-weight:400; color:var(--text-muted);">— filtres actifs</span>
                    <?php endif; ?>
                </h2>
            </div>

            <?php if (empty($toutes_notes)): ?>
                <div class="empty-state">
                    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p>Aucune note ne correspond aux filtres sélectionnés.</p>
                </div>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Étudiant</th>
                        <th>Classe</th>
                        <th>Cours</th>
                        <th>Professeur</th>
                        <th>Note / 20</th>
                        <th>Appréciation</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                    <?php
                    $groupe_affiché = '';
                    foreach($toutes_notes as $n):
                        // Séparateur de groupe
                        if ($n['groupe_nom'] !== $groupe_affiché && !$filtre_cours):
                            $groupe_affiché = $n['groupe_nom'];
                            echo "<tr class='group-sep'><td colspan='7'>📁 " . htmlspecialchars($groupe_affiché) . "</td></tr>";
                        endif;

                        $val = floatval($n['valeur_note']);
                        $badgeCls = $val >= 14 ? 'note-good' : ($val >= 10 ? 'note-mid' : 'note-bad');
                        $init = strtoupper(substr($n['etud_prenom'], 0, 1) . substr($n['etud_nom'], 0, 1));
                        $av = glob("uploads/avatars/avatar_" . $n['etudiant_id'] . ".*");
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:9px;">
                                <div class="avatar-small" style="width:28px;height:28px;font-size:10px;box-shadow:none;">
                                    <?php if(!empty($av)): ?><img src="<?= $av[0] ?>" alt=""><?php else: ?><?= $init ?><?php endif; ?>
                                </div>
                                <strong><?= htmlspecialchars($n['etud_nom'] . ' ' . $n['etud_prenom']) ?></strong>
                            </div>
                        </td>
                        <td style="font-size:13px;"><?= htmlspecialchars($n['groupe_nom']) ?></td>
                        <td style="font-size:13px;"><?= htmlspecialchars($n['cours_titre']) ?></td>
                        <td style="font-size:13px; color:var(--text-muted);"><?= htmlspecialchars($n['prof_nom'] . ' ' . $n['prof_prenom']) ?></td>
                        <td><span class="note-badge <?= $badgeCls ?>"><?= number_format($val, 2, ',', ' ') ?></span></td>
                        <td style="font-size:13px; color:var(--text-muted); font-style:italic; max-width:180px;">
                            <?= !empty($n['commentaire']) ? htmlspecialchars($n['commentaire']) : '—' ?>
                        </td>
                        <td style="text-align:right; white-space:nowrap;">
                            <button class="btn-edit"
                                onclick="ouvrirModale(
                                    <?= $n['note_id'] ?>,
                                    <?= $val ?>,
                                    '<?= htmlspecialchars(addslashes($n['commentaire'] ?? '')) ?>',
                                    '<?= htmlspecialchars($n['etud_prenom'] . ' ' . $n['etud_nom']) ?>',
                                    '<?= htmlspecialchars($n['cours_titre']) ?>'
                                )">
                                ✏️ Modifier
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette note définitivement ?');">
                                <input type="hidden" name="action" value="supprimer_note">
                                <input type="hidden" name="note_id" value="<?= $n['note_id'] ?>">
                                <button type="submit" class="btn-del">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modale de modification -->
    <div class="modal-overlay" id="modaleEdit">
        <div class="modal-box">
            <div class="modal-title" id="modaleTitre">Modifier la note</div>
            <div class="modal-sub" id="modaleSub"></div>
            <form method="POST">
                <input type="hidden" name="action" value="modifier_note">
                <input type="hidden" name="note_id" id="modaleNoteId">

                <?php // Conserver les filtres après POST
                $qs = http_build_query(array_filter([
                    'groupe_id' => $filtre_groupe,
                    'prof_id'   => $filtre_prof,
                    'cours_id'  => $filtre_cours,
                ]));
                if ($qs): ?>
                    <input type="hidden" name="redirect_qs" value="<?= htmlspecialchars($qs) ?>">
                <?php endif; ?>

                <label>Note / 20</label>
                <input type="number" name="valeur_note" id="modaleValeur" step="0.25" min="0" max="20" required>

                <label>Appréciation</label>
                <textarea name="commentaire" id="modaleCommentaire" rows="3" placeholder="Commentaire..."></textarea>

                <div class="modal-actions">
                    <button type="button"
                        onclick="fermerModale()"
                        style="padding:10px 20px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none; background:#f0f0f3; font-family:inherit;">
                        Annuler
                    </button>
                    <button type="submit" class="btn-action">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function ouvrirModale(noteId, valeur, commentaire, nomEtudiant, coursTitre) {
            document.getElementById('modaleNoteId').value      = noteId;
            document.getElementById('modaleValeur').value      = valeur;
            document.getElementById('modaleCommentaire').value = commentaire;
            document.getElementById('modaleTitre').textContent = 'Modifier — ' + nomEtudiant;
            document.getElementById('modaleSub').textContent   = coursTitre;
            document.getElementById('modaleEdit').classList.add('actif');
        }
        function fermerModale() {
            document.getElementById('modaleEdit').classList.remove('actif');
        }
        document.getElementById('modaleEdit').addEventListener('click', function(e) {
            if (e.target === this) fermerModale();
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerModale(); });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>