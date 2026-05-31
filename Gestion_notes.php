<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') {
    header("Location: connexion.php");
    exit();
}
require_once 'db.php';

$prof_id = $_SESSION['utilisateur_id'];
$message_succes = "";
$message_erreur = "";

// ==========================================
// ACTIONS POST
// ==========================================

// Modifier une note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'modifier_note') {
        $note_id   = intval($_POST['note_id']);
        $valeur    = floatval($_POST['valeur_note']);
        $commentaire = trim($_POST['commentaire']);

        // Vérification que la note appartient bien à un cours de ce prof
        $check = $db->prepare("
            SELECT n.id FROM notes n
            JOIN cours c ON n.cours_id = c.id
            WHERE n.id = ? AND c.professeur_id = ?
        ");
        $check->execute([$note_id, $prof_id]);

        if ($check->fetch()) {
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
        } else {
            $message_erreur = "Action non autorisée.";
        }
    }

    if ($_POST['action'] === 'supprimer_note') {
        $note_id = intval($_POST['note_id']);

        $check = $db->prepare("
            SELECT n.id FROM notes n
            JOIN cours c ON n.cours_id = c.id
            WHERE n.id = ? AND c.professeur_id = ?
        ");
        $check->execute([$note_id, $prof_id]);

        if ($check->fetch()) {
            $db->prepare("DELETE FROM notes WHERE id = ?")->execute([$note_id]);
            $message_succes = "Note supprimée avec succès.";
        } else {
            $message_erreur = "Action non autorisée.";
        }
    }
}

// ==========================================
// CHARGEMENT DES DONNÉES
// ==========================================

// Cours du professeur
$stmt_cours = $db->prepare("
    SELECT c.*, g.nom as groupe_nom
    FROM cours c
    JOIN groupes g ON c.groupe_id = g.id
    WHERE c.professeur_id = ?
    ORDER BY c.titre
");
$stmt_cours->execute([$prof_id]);
$mes_cours = $stmt_cours->fetchAll();

// Cours sélectionné
$cours_sel = isset($_GET['cours_id']) ? intval($_GET['cours_id']) : null;
$notes_cours = [];
$cours_info = null;

if ($cours_sel) {
    // Vérifier que ce cours appartient au prof
    $chk = $db->prepare("SELECT c.*, g.nom as groupe_nom FROM cours c JOIN groupes g ON c.groupe_id = g.id WHERE c.id = ? AND c.professeur_id = ?");
    $chk->execute([$cours_sel, $prof_id]);
    $cours_info = $chk->fetch();

    if ($cours_info) {
        // Notes de tous les étudiants pour ce cours
        $stmt_notes = $db->prepare("
            SELECT n.id as note_id, n.valeur_note, n.commentaire,
                   u.id as etudiant_id, u.nom, u.prenom
            FROM utilisateurs u
            LEFT JOIN notes n ON n.etudiant_id = u.id AND n.cours_id = ?
            WHERE u.role = 'etudiant'
              AND u.groupe_id = (SELECT groupe_id FROM cours WHERE id = ?)
            ORDER BY u.nom, u.prenom
        ");
        $stmt_notes->execute([$cours_sel, $cours_sel]);
        $notes_cours = $stmt_notes->fetchAll();
    }
}

// Statistiques globales du prof
$stat_notes = $db->prepare("SELECT COUNT(*) FROM notes n JOIN cours c ON n.cours_id = c.id WHERE c.professeur_id = ?");
$stat_notes->execute([$prof_id]);
$total_notes = $stat_notes->fetchColumn();

if ($cours_sel && $cours_info) {
    $stat_moy = $db->prepare("SELECT AVG(n.valeur_note) FROM notes n WHERE n.cours_id = ?");
    $stat_moy->execute([$cours_sel]);
} else {
    $stat_moy = $db->prepare("SELECT AVG(n.valeur_note) FROM notes n JOIN cours c ON n.cours_id = c.id WHERE c.professeur_id = ?");
    $stat_moy->execute([$prof_id]);
}
$moy_globale = round((float) $stat_moy->fetchColumn(), 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Notes - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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

        /* Tableau de notes */
        .note-row td { vertical-align: middle; }

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

        /* Boutons d'action */
        .btn-edit, .btn-del {
            padding: 6px 12px;
            border-radius: 8px;
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

        /* Modale d'édition */
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
            width: 420px;
            max-width: 95vw;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
        }
        .modal-title {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-main);
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        .empty-state svg { opacity: 0.3; margin-bottom: 12px; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 28px;">
            <h1 style="color:var(--primary);">Gestion des Notes</h1>
            <p style="color:var(--text-muted); margin:0;">Consultez, modifiez ou supprimez les notes de vos classes.</p>
        </div>

        <?php if ($message_succes): ?>
            <div class="alert alert-success"><span>✅ <?= htmlspecialchars($message_succes) ?></span></div>
        <?php endif; ?>
        <?php if ($message_erreur): ?>
            <div class="alert alert-error"><span>⚠️ <?= htmlspecialchars($message_erreur) ?></span></div>
        <?php endif; ?>

        <!-- Statistiques rapides -->
        <div class="stats-row">
            <div class="card stat-mini">
                <div class="stat-mini-icon" style="background:#e0e7ff; color:#4f46e5;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-mini-label">Cours enseignés</div>
                    <div class="stat-mini-val"><?= count($mes_cours) ?></div>
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
                    <div class="stat-mini-label">Moyenne générale</div>
                    <div class="stat-mini-val"><?= $total_notes > 0 ? number_format($moy_globale, 2, ',', ' ') . ' /20' : '—' ?></div>
                </div>
            </div>
        </div>

        <!-- Sélecteur de cours -->
        <div class="card" style="margin-bottom: 20px;">
            <form method="GET">
                <label>Sélectionner un cours</label>
                <div style="display:flex; gap:12px; align-items:flex-end;">
                    <select name="cours_id" style="flex:1;" onchange="this.form.submit()">
                        <option value="">-- Choisir un cours --</option>
                        <?php foreach($mes_cours as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $cours_sel == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['titre'] . ' (' . $c['groupe_nom'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Tableau des notes -->
        <?php if ($cours_sel && $cours_info): ?>
            <div class="card">
                <div class="card-header" style="margin-bottom: 16px;">
                    <div>
                        <h2 style="margin:0 0 4px;"><?= htmlspecialchars($cours_info['titre']) ?></h2>
                        <span style="font-size:13px; color:var(--text-muted);">
                            Classe : <strong><?= htmlspecialchars($cours_info['groupe_nom']) ?></strong>
                            &nbsp;·&nbsp; <?= count($notes_cours) ?> étudiant(s)
                        </span>
                    </div>
                    <?php
                    $notes_saisies = array_filter($notes_cours, fn($n) => $n['note_id'] !== null);
                    $nb_saisies = count($notes_saisies);
                    $pct = count($notes_cours) > 0 ? round($nb_saisies / count($notes_cours) * 100) : 0;
                    ?>
                    <span style="font-size:13px; color:var(--text-muted);">
                        <?= $nb_saisies ?> / <?= count($notes_cours) ?> notes saisies (<?= $pct ?>%)
                    </span>
                </div>

                <?php if (empty($notes_cours)): ?>
                    <div class="empty-state">
                        <p>Aucun étudiant dans cette classe.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Étudiant</th>
                            <th>Note / 20</th>
                            <th>Appréciation</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                        <?php foreach($notes_cours as $n):
                            $init = strtoupper(substr($n['prenom'], 0, 1) . substr($n['nom'], 0, 1));
                            $av   = glob("uploads/avatars/avatar_" . $n['etudiant_id'] . ".*");
                            $hasNote = $n['note_id'] !== null;
                            $val = $hasNote ? floatval($n['valeur_note']) : null;
                            $badgeCls = !$hasNote ? 'note-empty' : ($val >= 14 ? 'note-good' : ($val >= 10 ? 'note-mid' : 'note-bad'));
                            $badgeTxt = !$hasNote ? 'Non noté' : number_format($val, 2, ',', ' ');
                        ?>
                        <tr class="note-row">
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div class="avatar-small" style="width:30px;height:30px;font-size:10px;box-shadow:none;">
                                        <?php if(!empty($av)): ?><img src="<?= $av[0] ?>" alt=""><?php else: ?><?= $init ?><?php endif; ?>
                                    </div>
                                    <strong><?= htmlspecialchars($n['nom'] . ' ' . $n['prenom']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="note-badge <?= $badgeCls ?>"><?= $badgeTxt ?></span>
                            </td>
                            <td style="font-size:13px; color:var(--text-muted); font-style:italic; max-width:220px;">
                                <?= $hasNote && !empty($n['commentaire']) ? htmlspecialchars($n['commentaire']) : '—' ?>
                            </td>
                            <td style="text-align:right;">
                                <?php if($hasNote): ?>
                                    <button class="btn-edit"
                                        onclick="ouvrirModale(<?= $n['note_id'] ?>, <?= $val ?>, '<?= htmlspecialchars(addslashes($n['commentaire'] ?? '')) ?>', '<?= htmlspecialchars($n['prenom'] . ' ' . $n['nom']) ?>')">
                                        ✏️ Modifier
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette note ?');">
                                        <input type="hidden" name="action" value="supprimer_note">
                                        <input type="hidden" name="note_id" value="<?= $n['note_id'] ?>">
                                        <input type="hidden" name="cours_id" value="<?= $cours_sel ?>">
                                        <button type="submit" class="btn-del">🗑️ Supprimer</button>
                                    </form>
                                <?php else: ?>
                                    <a href="dashboard_professeur.php" class="btn-edit" style="text-decoration:none;">+ Ajouter</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($cours_sel && !$cours_info): ?>
            <div class="alert alert-error"><span>⚠️ Cours introuvable ou accès non autorisé.</span></div>

        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p>Sélectionnez un cours pour afficher les notes.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modale de modification -->
    <div class="modal-overlay" id="modaleEdit">
        <div class="modal-box">
            <div class="modal-title" id="modaleTitre">Modifier la note</div>
            <form method="POST">
                <input type="hidden" name="action" value="modifier_note">
                <input type="hidden" name="note_id" id="modaleNoteId">
                <input type="hidden" name="cours_id" value="<?= $cours_sel ?>">

                <label>Note / 20</label>
                <input type="number" name="valeur_note" id="modaleValeur" step="0.25" min="0" max="20" required>

                <label>Appréciation</label>
                <textarea name="commentaire" id="modaleCommentaire" rows="3" placeholder="Commentaire..."></textarea>

                <div class="modal-actions">
                    <button type="button" class="btn-panel btn-panel-ghost" onclick="fermerModale()" style="padding:10px 20px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none; background:#f0f0f3; font-family:inherit;">
                        Annuler
                    </button>
                    <button type="submit" class="btn-action">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function ouvrirModale(noteId, valeur, commentaire, nomEtudiant) {
            document.getElementById('modaleNoteId').value     = noteId;
            document.getElementById('modaleValeur').value     = valeur;
            document.getElementById('modaleCommentaire').value = commentaire;
            document.getElementById('modaleTitre').textContent = 'Modifier la note — ' + nomEtudiant;
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