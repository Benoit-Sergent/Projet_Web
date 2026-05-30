<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$message_succes = ""; $message_erreur = "";

// ==========================================
// ACTION : AJOUTER UN COURS AU PLANNING
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_cours') {
    $titre = trim($_POST['titre']);
    $categorie = trim($_POST['categorie']);
    $prof_id = intval($_POST['professeur_id']);
    $groupe_id = intval($_POST['groupe_id']);
    $jour = $_POST['jour'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $salle = trim($_POST['salle']);

    if (!empty($titre) && $prof_id > 0 && $groupe_id > 0) {

        // --- Détection de conflit ---
        $stmtConflit = $db->prepare("
            SELECT titre, heure_debut, heure_fin
            FROM cours
            WHERE groupe_id = ?
            AND jour      = ?
            AND ?         < heure_fin
            AND ?         > heure_debut
            LIMIT 1
        ");
        $stmtConflit->execute([$groupe_id, $jour, $heure_debut, $heure_fin]);
        $conflit = $stmtConflit->fetch();

        if ($conflit) {
            $hd = substr($conflit['heure_debut'], 0, 5);
            $hf = substr($conflit['heure_fin'],   0, 5);
            $message_erreur = "Conflit détecté : cette classe a déjà « "
                . htmlspecialchars($conflit['titre'])
                . " » de {$hd} à {$hf} ce jour-là.";
        } else {
            $stmt = $db->prepare("
                INSERT INTO cours (titre, categorie, professeur_id, groupe_id, jour, heure_debut, heure_fin, salle) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$titre, $categorie, $prof_id, $groupe_id, $jour, $heure_debut, $heure_fin, $salle])) {
                $message_succes = "Le cours a été planifié avec succès.";
            } else {
                $message_erreur = "Erreur lors de la création du cours.";
            }
        }

    } else {
        $message_erreur = "Veuillez remplir tous les champs obligatoires.";
    }
}

// Récupération des données pour les formulaires
$professeurs = $db->query("SELECT id, nom, prenom FROM utilisateurs WHERE role = 'professeur' ORDER BY nom")->fetchAll();
$groupes = $db->query("SELECT * FROM groupes ORDER BY nom")->fetchAll();
$cours = $db->query("
    SELECT c.*, u.nom as prof_nom, g.nom as groupe_nom 
    FROM cours c 
    LEFT JOIN utilisateurs u ON c.professeur_id = u.id 
    LEFT JOIN groupes g ON c.groupe_id = g.id 
    ORDER BY 
        CASE c.jour
            WHEN 'Lundi'    THEN 1
            WHEN 'Mardi'    THEN 2
            WHEN 'Mercredi' THEN 3
            WHEN 'Jeudi'    THEN 4
            WHEN 'Vendredi' THEN 5
        END,
        c.heure_debut
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><title>Programme - SmartCampus</title><link rel="stylesheet" href="style.css">
    <style>
        /* === LIGNES CLIQUABLES === */
        .cours-row { cursor: pointer; transition: background 0.15s; }
        .cours-row:hover { background: rgba(79, 70, 229, 0.04); }
        .cours-row:hover td:first-child strong { color: var(--primary, #4f46e5); }

        /* === OVERLAY === */
        .panel-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15, 15, 25, 0.35);
            backdrop-filter: blur(3px); z-index: 998;
        }
        .panel-overlay.actif { display: block; }

        /* === SLIDE-IN PANEL === */
        .cours-panel {
            position: fixed; top: 65px; right: 0; bottom: 0;
            width: 440px; max-width: 96vw;
            background: var(--bg-card, #fff);
            box-shadow: -10px 0 48px rgba(0,0,0,0.13);
            z-index: 999;
            transform: translateX(100%);
            transition: transform 0.32s cubic-bezier(.4,0,.2,1);
            display: flex; flex-direction: column; overflow: hidden;
        }
        .cours-panel.ouvert { transform: translateX(0); }

        /* --- Header --- */
        .panel-header {
            padding: 22px 24px;
            display: flex; align-items: center; gap: 16px;
            border-bottom: 1px solid var(--border, #e5e7eb);
            flex-shrink: 0;
        }
        .panel-avatar {
            width: 54px; height: 54px; border-radius: 15px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; background: #e0e7ff;
        }
        .panel-header-info { flex: 1; min-width: 0; }
        .panel-header-info h3 {
            font-size: 17px; font-weight: 700; margin: 0 0 5px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            color: var(--text-main);
        }
        .panel-close {
            width: 34px; height: 34px; border-radius: 9px; border: none; flex-shrink: 0;
            background: var(--bg, #f3f4f6); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: var(--text-muted);
            transition: background 0.18s;
        }
        .panel-close:hover { background: #e5e7eb; }

        /* --- Corps --- */
        .panel-body { flex: 1; overflow-y: auto; padding: 24px; }
        .panel-section { margin-bottom: 24px; }
        .panel-section-title {
            font-size: 10.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.08em; color: var(--text-muted);
            margin-bottom: 16px; padding-bottom: 9px;
            border-bottom: 1.5px solid var(--border, #e5e7eb);
        }

        /* --- Grille --- */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .info-item.full { grid-column: 1 / -1; }
        .info-item label {
            font-size: 10.5px; color: var(--text-muted); font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
            display: block; margin-bottom: 5px;
        }
        .info-value {
            font-size: 14px; font-weight: 600; color: var(--text-main);
            min-height: 22px; display: block; line-height: 1.4;
        }
        .info-item input, .info-item select {
            width: 100%; padding: 9px 12px; border-radius: 9px;
            border: 1.5px solid var(--border, #e5e7eb);
            font-size: 14px; color: var(--text-main);
            background: var(--bg, #fff);
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box; font-family: inherit;
        }
        .info-item input:focus, .info-item select:focus {
            outline: none; border-color: var(--primary, #4f46e5);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* --- Pied --- */
        .panel-footer {
            padding: 16px 24px; flex-shrink: 0;
            border-top: 1px solid var(--border, #e5e7eb);
            display: flex; gap: 10px; background: var(--bg-card, #fff);
        }
        .btn-panel {
            padding: 10px 20px; border-radius: 10px;
            font-size: 14px; font-weight: 600; cursor: pointer;
            border: none; transition: opacity 0.18s, background 0.18s;
            font-family: inherit;
        }
        .btn-panel-primary { background: var(--primary, #4f46e5); color: #fff; flex: 1; }
        .btn-panel-primary:hover { opacity: 0.87; }
        .btn-panel-success { background: #10b981; color: #fff; flex: 1; }
        .btn-panel-success:hover { opacity: 0.87; }
        .btn-panel-ghost   { background: #f0f0f3; color: var(--text-main); }
        .btn-panel-ghost:hover { background: #e5e5ea; }
        .btn-panel:disabled { opacity: 0.55; cursor: not-allowed; }

        /* --- Modes --- */
        .cours-panel.mode-lecture .edit-only { display: none !important; }
        .cours-panel.mode-edition .read-only { display: none !important; }

        /* --- Toast --- */
        .panel-toast {
            position: fixed; bottom: 28px; right: 28px;
            padding: 13px 22px; border-radius: 11px;
            font-size: 14px; font-weight: 600; color: #fff;
            z-index: 1001; opacity: 0; pointer-events: none;
            transform: translateY(8px);
            transition: opacity 0.25s, transform 0.25s;
        }
        .panel-toast.show    { opacity: 1; transform: translateY(0); }
        .panel-toast.success { background: #065f46; }
        .panel-toast.error   { background: #991b1b; }

        /* --- Spinner --- */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spin-icon { animation: spin 0.9s linear infinite; display: inline-block; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="color:var(--primary);">Ingénierie Pédagogique</h1>
            <p style="color:var(--text-muted); margin:0;">Créez les cours et assignez-les aux créneaux de l'emploi du temps.</p>
        </div>

        <?php if ($message_succes): ?><div class="alert alert-success">✅ <?= $message_succes ?></div><?php endif; ?>
        <?php if ($message_erreur): ?><div class="alert alert-error">⚠️ <?= $message_erreur ?></div><?php endif; ?>

        <div class="dashboard-grid inverse">
            
            <div class="card" style="align-self: start;">
                <div class="card-header"><h2>Planifier un nouvel enseignement</h2></div>
                <form method="POST">
                    <input type="hidden" name="action" value="ajouter_cours">
                    
                    <label>Titre du cours</label>
                    <input type="text" name="titre" required placeholder="Ex: Mathématiques Avancées">
                    
                    <label>Catégorie (Module)</label>
                    <select name="categorie" required>
                        <option value="Sciences">Sciences & Ingénierie</option>
                        <option value="Informatique">Informatique & Tech</option>
                        <option value="Langues">Langues & Culture</option>
                        <option value="Management">Management & Droit</option>
                    </select>

                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;">
                            <label>Professeur</label>
                            <select name="professeur_id" required>
                                <option value="">-- Assigner --</option>
                                <?php foreach($professeurs as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom'].' '.$p['prenom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label>Classe (Groupe)</label>
                            <select name="groupe_id" required>
                                <option value="">-- Assigner --</option>
                                <?php foreach($groupes as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border); margin: 15px 0; padding-top: 15px;">
                        <label style="color:var(--primary); font-size:14px; margin-bottom:10px;">🕒 Horaires & Salle</label>
                    </div>

                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;">
                            <label>Jour de la semaine</label>
                            <select name="jour" required>
                                <option value="Lundi">Lundi</option><option value="Mardi">Mardi</option>
                                <option value="Mercredi">Mercredi</option><option value="Jeudi">Jeudi</option>
                                <option value="Vendredi">Vendredi</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label>Salle</label>
                            <input type="text" name="salle" required placeholder="Ex: Amphi A, Salle 302">
                        </div>
                    </div>

                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;"><label>Heure Début</label><input type="time" name="heure_debut" required value="08:30"></div>
                        <div style="flex:1;"><label>Heure Fin</label><input type="time" name="heure_fin" required value="10:30"></div>
                    </div>
                                        <!-- Alerte conflit temps réel -->
                    <div id="alerteConflit" style="
                        display:none; margin-top:12px; padding:11px 15px;
                        background:#fef2f2; border:1.5px solid #fca5a5; border-radius:10px;
                        color:#991b1b; font-size:13px; font-weight:600; line-height:1.5;">
                    </div>

                    <button type="submit" id="btnSubmitCours" class="btn-action" style="width:100%; margin-top:15px;">
                        Inscrire au planning
                    </button>
                </form>
            </div>

            <div class="card">
                <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <h2 style="margin:0;">Maquette Pédagogique Globale</h2>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <select id="filtreCategorie" onchange="filtrerCours()" style="padding:7px 12px; border-radius:8px; border:1.5px solid var(--border); font-size:13px; font-weight:600; color:var(--text-main); background:var(--bg);">
                            <option value="">Toutes les catégories</option>
                            <option value="Sciences">Sciences & Ingénierie</option>
                            <option value="Informatique">Informatique & Tech</option>
                            <option value="Langues">Langues & Culture</option>
                            <option value="Management">Management & Droit</option>
                        </select>
                        <select id="filtreNiveau" onchange="filtrerCours()" style="padding:7px 12px; border-radius:8px; border:1.5px solid var(--border); font-size:13px; font-weight:600; color:var(--text-main); background:var(--bg);">
                            <option value="">Tous les niveaux</option>
                            <option value="1">1ère année</option>
                            <option value="2">2ème année</option>
                            <option value="3">3ème année</option>
                        </select>
                    </div>
                </div>
                <?php if (empty($cours)): ?>
                    <p style="color:var(--text-muted); text-align:center;">Aucun cours programmé.</p>
                <?php else: ?>
                    <table>
                        <!-- Ligne d'en-tête : simple, pas cliquable -->
                        <tr>
                            <th>Enseignement</th><th>Créneau</th><th>Salle</th><th>Professeur</th><th>Classe</th>
                        </tr>

                        <?php foreach($cours as $c): ?>
                            <tr class="cours-row"
                                data-cours-id="<?= $c['id'] ?>"
                                onclick="ouvrirCours(
                                    <?= $c['id'] ?>,
                                    <?= htmlspecialchars(json_encode([
                                        'id'            => $c['id'],
                                        'titre'         => $c['titre'],
                                        'categorie'     => $c['categorie'],
                                        'professeur_id' => $c['professeur_id'],
                                        'groupe_id'     => $c['groupe_id'],
                                        'jour'          => $c['jour'],
                                        'heure_debut'   => $c['heure_debut'],
                                        'heure_fin'     => $c['heure_fin'],
                                        'salle'         => $c['salle'],
                                        'prof_nom'      => $c['prof_nom'],
                                        'groupe_nom'    => $c['groupe_nom'],
                                    ]), ENT_QUOTES) ?>
                                )"
                                title="Modifier ce cours">
                                <td>
                                    <strong><?= htmlspecialchars($c['titre']) ?></strong><br>
                                    <span style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($c['categorie']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-neutral"><?= htmlspecialchars($c['jour']) ?></span><br>
                                    <span style="font-size:12px; color:var(--primary); font-weight:600;"><?= $c['heure_debut'] ?> - <?= $c['heure_fin'] ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars($c['salle']) ?></strong></td>
                                <td><?= htmlspecialchars($c['prof_nom']) ?></td>
                                <td><span style="color:var(--primary); font-weight:600;"><?= htmlspecialchars($c['groupe_nom']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- ===== OVERLAY ===== -->
<div class="panel-overlay" id="coursOverlay" onclick="fermerCours()"></div>

<!-- ===== SLIDE-IN PANEL COURS ===== -->
<div class="cours-panel mode-lecture" id="coursPanel">

    <div class="panel-header">
        <div class="panel-avatar">📚</div>
        <div class="panel-header-info">
            <h3 id="cpTitre">—</h3>
            <div id="cpBadge"></div>
        </div>
        <button class="panel-close" onclick="fermerCours()">✕</button>
    </div>

    <div class="panel-body">
        <div class="panel-section">
            <div class="panel-section-title">Informations du cours</div>
            <div class="info-grid">

                <div class="info-item full">
                    <label>Titre</label>
                    <span class="info-value read-only" id="cpDispTitre"></span>
                    <input type="text" class="edit-only" id="cpEditTitre" placeholder="Titre du cours">
                </div>

                <div class="info-item full">
                    <label>Catégorie</label>
                    <span class="info-value read-only" id="cpDispCategorie"></span>
                    <select class="edit-only" id="cpEditCategorie">
                        <option value="Sciences">Sciences & Ingénierie</option>
                        <option value="Informatique">Informatique & Tech</option>
                        <option value="Langues">Langues & Culture</option>
                        <option value="Management">Management & Droit</option>
                    </select>
                </div>

                <div class="info-item full">
                    <label>Professeur</label>
                    <span class="info-value read-only" id="cpDispProf"></span>
                    <select class="edit-only" id="cpEditProf">
                        <?php foreach($professeurs as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="info-item full">
                    <label>Classe</label>
                    <span class="info-value read-only" id="cpDispGroupe"></span>
                    <select class="edit-only" id="cpEditGroupe">
                        <?php foreach($groupes as $g): ?>
                            <option value="<?= $g['id'] ?>">
                                <?= htmlspecialchars($g['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
        </div>

        <div class="panel-section">
            <div class="panel-section-title">Horaires & Salle</div>
            <div class="info-grid">

                <div class="info-item full">
                    <label>Jour</label>
                    <span class="info-value read-only" id="cpDispJour"></span>
                    <select class="edit-only" id="cpEditJour">
                        <option value="Lundi">Lundi</option>
                        <option value="Mardi">Mardi</option>
                        <option value="Mercredi">Mercredi</option>
                        <option value="Jeudi">Jeudi</option>
                        <option value="Vendredi">Vendredi</option>
                    </select>
                </div>

                <div class="info-item">
                    <label>Heure début</label>
                    <span class="info-value read-only" id="cpDispDebut"></span>
                    <input type="time" class="edit-only" id="cpEditDebut">
                </div>

                <div class="info-item">
                    <label>Heure fin</label>
                    <span class="info-value read-only" id="cpDispFin"></span>
                    <input type="time" class="edit-only" id="cpEditFin">
                </div>

                <div class="info-item full">
                    <label>Salle</label>
                    <span class="info-value read-only" id="cpDispSalle"></span>
                    <input type="text" class="edit-only" id="cpEditSalle" placeholder="Ex: Amphi A">
                </div>

            </div>
        </div>
    </div>

    <div class="panel-footer">
        <button class="btn-panel btn-panel-primary read-only" onclick="basculerEditionCours(true)">
            ✏️ Modifier le cours
        </button>
        <button class="btn-panel btn-panel-success edit-only" id="btnSaveCours" onclick="sauvegarderCours()">
            💾 Enregistrer
        </button>
        <button class="btn-panel btn-panel-ghost edit-only" onclick="basculerEditionCours(false)">
            Annuler
        </button>
    </div>
</div>

<script>
    const CATEGORIE_LABELS = {
        'Sciences':     'Sciences & Ingénierie',
        'Informatique': 'Informatique & Tech',
        'Langues':      'Langues & Culture',
        'Management':   'Management & Droit',
    };
    const CATEGORIE_COLORS = {
        'Sciences':     { bg: '#e0e7ff', color: '#3730a3' },
        'Informatique': { bg: '#d1fae5', color: '#065f46' },
        'Langues':      { bg: '#fef3c7', color: '#92400e' },
        'Management':   { bg: '#fee2e2', color: '#991b1b' },
    };

    let currentCours = null;

    function ouvrirCours(id, data) {
        currentCours = data;
        basculerEditionCours(false);
        remplirPanelCours(data);
        document.getElementById('coursOverlay').classList.add('actif');
        document.getElementById('coursPanel').classList.add('ouvert');
    }

    function fermerCours() {
        document.getElementById('coursOverlay').classList.remove('actif');
        document.getElementById('coursPanel').classList.remove('ouvert');
        basculerEditionCours(false);
    }

    function remplirPanelCours(c) {
        const cat = CATEGORIE_COLORS[c.categorie] || { bg: '#f3f4f6', color: '#6b7280' };

        document.getElementById('cpTitre').textContent = c.titre;
        document.getElementById('cpBadge').innerHTML =
            `<span style="font-size:12px;font-weight:700;padding:2px 10px;border-radius:6px;
                          background:${cat.bg};color:${cat.color};">
                ${escHtml(CATEGORIE_LABELS[c.categorie] || c.categorie)}
             </span>`;

        // Champs lecture
        document.getElementById('cpDispTitre').textContent    = c.titre;
        document.getElementById('cpDispCategorie').textContent = CATEGORIE_LABELS[c.categorie] || c.categorie;
        document.getElementById('cpDispProf').textContent     = c.prof_nom    || '—';
        document.getElementById('cpDispGroupe').textContent   = c.groupe_nom  || '—';
        document.getElementById('cpDispJour').textContent     = c.jour        || '—';
        document.getElementById('cpDispDebut').textContent    = c.heure_debut ? c.heure_debut.slice(0,5) : '—';
        document.getElementById('cpDispFin').textContent      = c.heure_fin   ? c.heure_fin.slice(0,5)   : '—';
        document.getElementById('cpDispSalle').textContent    = c.salle       || '—';

        // Champs édition
        document.getElementById('cpEditTitre').value     = c.titre;
        document.getElementById('cpEditCategorie').value = c.categorie;
        document.getElementById('cpEditProf').value      = c.professeur_id;
        document.getElementById('cpEditGroupe').value    = c.groupe_id;
        document.getElementById('cpEditJour').value      = c.jour;
        document.getElementById('cpEditDebut').value     = c.heure_debut ? c.heure_debut.slice(0,5) : '';
        document.getElementById('cpEditFin').value       = c.heure_fin   ? c.heure_fin.slice(0,5)   : '';
        document.getElementById('cpEditSalle').value     = c.salle;
    }

    function basculerEditionCours(actif) {
        const panel = document.getElementById('coursPanel');
        panel.classList.toggle('mode-lecture', !actif);
        panel.classList.toggle('mode-edition',  actif);
    }

    function sauvegarderCours() {
        const payload = {
            id:            currentCours.id,
            titre:         document.getElementById('cpEditTitre').value.trim(),
            categorie:     document.getElementById('cpEditCategorie').value,
            professeur_id: document.getElementById('cpEditProf').value,
            groupe_id:     document.getElementById('cpEditGroupe').value,
            jour:          document.getElementById('cpEditJour').value,
            heure_debut:   document.getElementById('cpEditDebut').value,
            heure_fin:     document.getElementById('cpEditFin').value,
            salle:         document.getElementById('cpEditSalle').value.trim(),
        };

        if (!payload.titre) {
            afficherToastCours('Le titre est obligatoire.', 'error');
            return;
        }

        const btn = document.getElementById('btnSaveCours');
        btn.textContent = 'Enregistrement…';
        btn.disabled    = true;

        fetch('modifier_cours.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Mise à jour locale
                const profOpt   = document.getElementById('cpEditProf');
                const groupeOpt = document.getElementById('cpEditGroupe');
                currentCours = {
                    ...currentCours,
                    ...payload,
                    prof_nom:   profOpt.options[profOpt.selectedIndex].text,
                    groupe_nom: groupeOpt.options[groupeOpt.selectedIndex].text,
                };
                remplirPanelCours(currentCours);
                basculerEditionCours(false);
                actualiserLigneCours(currentCours);
                afficherToastCours('✅ Cours mis à jour avec succès !', 'success');
            } else {
                afficherToastCours('⚠️ ' + (data.error || 'Erreur lors de la mise à jour.'), 'error');
            }
        })
        .catch(() => afficherToastCours('⚠️ Erreur réseau. Veuillez réessayer.', 'error'))
        .finally(() => {
            btn.textContent = '💾 Enregistrer';
            btn.disabled    = false;
        });
    }

    /** Met à jour la ligne du tableau sans recharger la page */
    function actualiserLigneCours(c) {
        const row = document.querySelector(`tr.cours-row[data-cours-id="${c.id}"]`);
        if (!row) return;

        const cat = CATEGORIE_COLORS[c.categorie] || { bg: '#f3f4f6', color: '#6b7280' };

        row.cells[0].innerHTML = `
            <strong>${escHtml(c.titre)}</strong><br>
            <span style="font-size:11px;color:var(--text-muted);">${escHtml(c.categorie)}</span>`;
        row.cells[1].innerHTML = `
            <span class="badge badge-neutral">${escHtml(c.jour)}</span><br>
            <span style="font-size:12px;color:var(--primary);font-weight:600;">
                ${c.heure_debut.slice(0,5)} - ${c.heure_fin.slice(0,5)}
            </span>`;
        row.cells[2].innerHTML = `<strong>${escHtml(c.salle)}</strong>`;
        row.cells[3].textContent = c.prof_nom;
        row.cells[4].innerHTML =
            `<span style="color:var(--primary);font-weight:600;">${escHtml(c.groupe_nom)}</span>`;
    }

    function afficherToastCours(message, type = 'success') {
        const t = document.getElementById('coursToast');
        t.textContent = message;
        t.className   = `panel-toast ${type} show`;
        setTimeout(() => t.classList.remove('show'), 3200);
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function filtrerCours() {
        const valeurCat    = document.getElementById('filtreCategorie').value;
        const valeurNiveau = document.getElementById('filtreNiveau').value;

        document.querySelectorAll('tr.cours-row').forEach(row => {
            // Catégorie : span dans la 1ère cellule
            const cat = row.cells[0].querySelector('span')?.textContent?.trim() || '';

            // Niveau : extrait le chiffre après "A" dans le nom du groupe (ex: "G1-A3" → "3")
            const groupeNom = row.cells[4].textContent.trim();
            const match     = groupeNom.match(/A(\d+)/i);
            const niveau    = match ? match[1] : '';

            const catOk    = !valeurCat    || cat    === valeurCat;
            const niveauOk = !valeurNiveau || niveau === valeurNiveau;

            row.style.display = (catOk && niveauOk) ? '' : 'none';
        });
    }

    // ── Données existantes pour la vérification côté client ──
    const coursExistants = <?= json_encode(array_map(fn($c) => [
        'groupe_id'   => $c['groupe_id'],
        'groupe_nom'  => $c['groupe_nom'],
        'jour'        => $c['jour'],
        'heure_debut' => $c['heure_debut'],
        'heure_fin'   => $c['heure_fin'],
        'titre'       => $c['titre'],
    ], $cours)) ?>;

    function verifierConflitFormulaire() {
        const groupeId   = document.querySelector('[name="groupe_id"]').value;
        const jour       = document.querySelector('[name="jour"]').value;
        const heureDebut = document.querySelector('[name="heure_debut"]').value;
        const heureFin   = document.querySelector('[name="heure_fin"]').value;
        const alerte     = document.getElementById('alerteConflit');
        const btnSubmit  = document.getElementById('btnSubmitCours');

        // Champs incomplets → pas de vérification
        if (!groupeId || !jour || !heureDebut || !heureFin) {
            alerte.style.display = 'none';
            btnSubmit.disabled   = false;
            return;
        }

        // Chevauchement : nouveau_debut < fin_existant AND nouveau_fin > debut_existant
        const conflit = coursExistants.find(c =>
            String(c.groupe_id) === String(groupeId) &&
            c.jour              === jour             &&
            heureDebut          <  c.heure_fin.slice(0, 5) &&
            heureFin            >  c.heure_debut.slice(0, 5)
        );

        if (conflit) {
            alerte.innerHTML    = `⛔ Conflit : cette classe a déjà <strong>« ${escHtml(conflit.titre)} »</strong>`
                                + ` de ${conflit.heure_debut.slice(0,5)} à ${conflit.heure_fin.slice(0,5)} ce jour-là.`;
            alerte.style.display = 'block';
            btnSubmit.disabled   = true;
        } else {
            alerte.style.display = 'none';
            btnSubmit.disabled   = false;
        }
    }

    // Écouter les 4 champs déclencheurs
    ['[name="groupe_id"]','[name="jour"]','[name="heure_debut"]','[name="heure_fin"]']
        .forEach(sel => document.querySelector(sel)
            ?.addEventListener('change', verifierConflitFormulaire));

    document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerCours(); });
</script>

<!-- Toast -->
<div class="panel-toast" id="coursToast"></div>
</body>
</html>
</html>