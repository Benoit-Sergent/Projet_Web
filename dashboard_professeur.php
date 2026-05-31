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
// 1. GESTION DU FORMULAIRE : AJOUT D'UNE NOTE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_note') {
    $etudiant_id = intval($_POST['etudiant_id']);
    $cours_id = intval($_POST['cours_id']);
    $valeur = floatval($_POST['valeur_note']);
    $commentaire = trim($_POST['commentaire']);

    if ($valeur >= 0 && $valeur <= 20) {
        $stmt = $db->prepare("
            INSERT INTO notes (etudiant_id, cours_id, valeur_note, commentaire) 
            VALUES (?, ?, ?, ?)
        ");
        if ($stmt->execute([$etudiant_id, $cours_id, $valeur, $commentaire])) {
            $message_succes = "L'évaluation a été enregistrée avec succès.";
        } else {
            $message_erreur = "Erreur lors de l'enregistrement de la note.";
        }
    } else {
        $message_erreur = "La note doit impérativement être comprise entre 0 et 20.";
    }
}

// ==========================================
// 2. CHARGEMENT DES DONNÉES DU TABLEAU DE BORD
// ==========================================
// Liste des cours dispensés par ce professeur
$mes_cours = $db->query("
    SELECT c.*, g.nom as groupe_nom 
    FROM cours c 
    JOIN groupes g ON c.groupe_id = g.id 
    WHERE c.professeur_id = $prof_id 
    ORDER BY c.titre
")->fetchAll();

$nb_cours = count($mes_cours);

// Présences à enregistrer aujourd'hui
$jours_fr = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi',
             'Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
$aujourdhui = $jours_fr[date('l')];
$today = date('Y-m-d');

$stmt_pres = $db->prepare("
    SELECT COUNT(*) FROM cours c
    WHERE c.professeur_id = ?
      AND c.jour = ?
      AND NOT EXISTS (
          SELECT 1 FROM presences p
          WHERE p.cours_id = c.id AND p.date_cours = ?
      )
");
$stmt_pres->execute([$prof_id, $aujourdhui, $today]);
$nb_presences_a_faire = (int) $stmt_pres->fetchColumn();

// 3 prochaines sessions de cours avec les vrais horaires (tri chronologique)
$prochains_cours_prof = $db->query("
    SELECT c.*, g.nom as groupe_nom 
    FROM cours c 
    JOIN groupes g ON c.groupe_id = g.id 
    WHERE c.professeur_id = $prof_id 
    ORDER BY CASE c.jour 
        WHEN 'Lundi' THEN 1 WHEN 'Mardi' THEN 2 WHEN 'Mercredi' THEN 3 
        WHEN 'Jeudi' THEN 4 WHEN 'Vendredi' THEN 5 ELSE 6 
    END, c.heure_debut ASC
    LIMIT 3
")->fetchAll();

// Liste complète des étudiants inscrits dans ses classes (pour le sélecteur)
$etudiants_possibles = $db->query("
    SELECT u.id, u.nom, u.prenom, u.groupe_id, g.nom as groupe_nom 
    FROM utilisateurs u 
    JOIN groupes g ON u.groupe_id = g.id 
    WHERE u.role = 'etudiant' 
      AND u.groupe_id IN (SELECT groupe_id FROM cours WHERE professeur_id = $prof_id)
    ORDER BY g.nom, u.nom
")->fetchAll();

// Historique global des notes attribuées par ce professeur
$historique_notes = $db->query("
    SELECT n.*, u.nom as etud_nom, u.prenom as etud_prenom, u.id as etud_id, c.titre as cours_titre 
    FROM notes n 
    JOIN utilisateurs u ON n.etudiant_id = u.id 
    JOIN cours c ON n.cours_id = c.id 
    WHERE c.professeur_id = $prof_id 
    ORDER BY n.id DESC
")->fetchAll();

$nb_notes_donnees = count($historique_notes);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Enseignant - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Grille des indicateurs */
        .stats-container { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 20px; 
            margin-bottom: 25px; 
        }
        .stat-card { 
            display: flex; 
            align-items: center; 
            padding: 24px; 
            gap: 20px; 
        }
        .stat-icon { 
            width: 54px; 
            height: 54px; 
            border-radius: 14px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: #e0e7ff; 
            color: #4f46e5; 
        }
        .stat-info h2 { 
            font-size: 12px; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            margin-bottom: 4px; 
            letter-spacing: 0.05em;
        }
        .stat-info .stat-value { 
            font-size: 32px; 
            font-weight: 700; 
            color: var(--text-main); 
        }
        
        /* Design de l'Aperçu Agenda */
        .timeline { 
            position: relative; 
            padding-left: 24px; 
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
            margin-bottom: 15px; 
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
            background: #10b981; 
            border: 2px solid var(--surface); 
            box-shadow: 0 0 0 3px #d1fae5; 
        }
        .timeline-content { 
            background: var(--bg-body); 
            padding: 12px 16px; 
            border-radius: var(--radius-md); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }

        .empty-state { 
            text-align: center; 
            padding: 40px 20px; 
            color: var(--text-muted); 
        }

        /* ===== LIGNES CLIQUABLES (tableau des évaluations) ===== */
        .student-row {
            cursor: pointer;
            transition: background 0.15s;
        }
        .student-row:hover {
            background: rgba(79, 70, 229, 0.04);
        }
        .student-row:hover td:first-child strong {
            color: var(--primary, #4f46e5);
        }

        /* ===== OVERLAY ===== */
        .panel-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 15, 25, 0.35);
            backdrop-filter: blur(3px);
            z-index: 998;
        }
        .panel-overlay.actif { display: block; }

        /* ===== SLIDE-IN PANEL ===== */
        .profil-panel {
            position: fixed;
            top: 65px;
            right: 0;
            bottom: 0;
            width: 440px;
            max-width: 96vw;
            background: var(--bg-card, #fff);
            box-shadow: -10px 0 48px rgba(0, 0, 0, 0.13);
            z-index: 999;
            transform: translateX(100%);
            transition: transform 0.32s cubic-bezier(.4, 0, .2, 1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .profil-panel.ouvert { transform: translateX(0); }

        /* --- En-tête du panel --- */
        .panel-header {
            padding: 22px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 1px solid var(--border, #e5e7eb);
            flex-shrink: 0;
        }
        .panel-avatar {
            width: 54px;
            height: 54px;
            border-radius: 15px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.04em;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }
        .panel-header-info { flex: 1; min-width: 0; }
        .panel-header-info h3 {
            font-size: 17px;
            font-weight: 700;
            margin: 0 0 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-main);
        }
        .panel-close {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: none;
            flex-shrink: 0;
            background: var(--bg, #f3f4f6);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            color: var(--text-muted);
            transition: background 0.18s, color 0.18s;
        }
        .panel-close:hover { background: #e5e5ea; color: var(--text-main); }

        /* --- Corps du panel --- */
        .panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        /* --- Sections --- */
        .panel-section { margin-bottom: 28px; }
        .panel-section-title {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 16px;
            padding-bottom: 9px;
            border-bottom: 1.5px solid var(--border, #e5e7eb);
        }

        /* --- Grille d'infos --- */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .info-item.full { grid-column: 1 / -1; }
        .info-item label {
            font-size: 10.5px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            min-height: 22px;
            display: block;
            line-height: 1.4;
        }

        /* --- Bloc moyenne --- */
        .moy-bloc {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 13px 18px;
            border-radius: 13px;
            margin-bottom: 18px;
            width: 100%;
            box-sizing: border-box;
        }
        .moy-chiffre { font-size: 26px; font-weight: 800; line-height: 1; }
        .moy-label   { font-size: 12px; font-weight: 500; opacity: 0.75; margin-top: 2px; }
        .moy-good { background: #d1fae5; color: #065f46; }
        .moy-mid  { background: #fef3c7; color: #92400e; }
        .moy-bad  { background: #fee2e2; color: #991b1b; }
        .moy-none { background: #f3f4f6; color: #6b7280; }
        .moy-emoji { font-size: 22px; }

        /* --- Historique de notes --- */
        .hist-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .hist-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border, #f0f0f0);
        }
        .hist-item:last-child { border-bottom: none; }
        .hist-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--primary, #4f46e5);
            flex-shrink: 0;
            margin-top: 4px;
        }
        .hist-nom   { font-weight: 600; font-size: 14px; color: var(--text-main); }
        .hist-annee { font-size: 12px; color: var(--text-muted); margin-top: 1px; }
        .hist-empty {
            font-size: 13px;
            color: var(--text-muted);
            font-style: italic;
            padding: 8px 0;
        }

        /* --- Loader --- */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spin-icon { animation: spin 0.9s linear infinite; display: inline-block; }
        .panel-loader {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            height: 200px;
            color: var(--text-muted);
            font-size: 14px;
        }

        /* --- Pied du panel --- */
        .panel-footer {
            padding: 16px 24px;
            flex-shrink: 0;
            border-top: 1px solid var(--border, #e5e7eb);
            display: flex;
            gap: 10px;
            background: var(--bg-card, #fff);
        }
        .btn-panel {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: opacity 0.18s, background 0.18s;
            font-family: inherit;
        }
        .btn-panel-ghost { background: #f0f0f3; color: var(--text-main); flex: 1; }
        .btn-panel-ghost:hover { background: #e5e5ea; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="color:var(--primary);">Espace Pédagogique</h1>
            <p style="color:var(--text-muted); margin:0;">Saisissez vos évaluations et suivez la progression de vos classes.</p>
        </div>

        <?php if ($message_succes): ?>
            <div class="alert alert-success"><span>✅ <?= $message_succes ?></span></div>
        <?php endif; ?>
        <?php if ($message_erreur): ?>
            <div class="alert alert-error"><span>⚠️ <?= $message_erreur ?></span></div>
        <?php endif; ?>

        <div class="stats-container">
            <div class="card stat-card">
                <div class="stat-icon" style="background:<?= $nb_presences_a_faire > 0 ? '#fef3c7' : '#d1fae5' ?>; color:<?= $nb_presences_a_faire > 0 ? '#d97706' : '#10b981' ?>;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <h2>Présences à enregistrer</h2>
                    <div class="stat-value"><?= $nb_presences_a_faire ?></div>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                        <?= $nb_presences_a_faire === 0 ? 'Tout est à jour ✅' : 'Appel(s) en attente aujourd\'hui' ?>
                    </div>
                </div>
            </div>
            
            <div class="card" style="padding: 15px 24px;">
                <h2 style="font-size:12px; text-transform:uppercase; color:var(--text-muted); margin-bottom:10px; letter-spacing:0.05em;">📅 Prochaines sessions</h2>
                <?php if (empty($prochains_cours_prof)): ?>
                    <p style="font-size:13px; color:var(--text-muted); margin:5px 0;">Aucun cours planifié.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach($prochains_cours_prof as $pcp): ?>
                            <div class="timeline-item">
                                <div class="timeline-badge"></div>
                                <div class="timeline-content" style="padding:8px 12px;">
                                    <div>
                                        <span style="font-size:13px; font-weight:600; display:block;">
                                            <?= htmlspecialchars($pcp['titre']) ?> (<?= htmlspecialchars($pcp['groupe_nom']) ?>)
                                        </span>
                                        <span style="font-size:11px; color:var(--text-muted);">
                                            <?= htmlspecialchars($pcp['salle'] ?? 'Salle à définir') ?>
                                        </span>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="badge badge-neutral" style="font-size:10px;">
                                            <?= htmlspecialchars($pcp['jour'] ?? 'Lundi') ?>
                                        </span><br>
                                        <span style="font-size:11px; font-weight:600; color:var(--primary);">
                                            <?= htmlspecialchars(substr($pcp['heure_debut'] ?? '08:30', 0, 5)) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid inverse">
            
            <div class="card" style="align-self: start;">
                <div class="card-header"><h2>Saisir une note</h2></div>
                <?php if ($nb_cours == 0): ?>
                    <p style="color:var(--text-muted); font-size:14px;">Vous n'avez pas de cours assigné.</p>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="ajouter_note">
                        
                        <label>Cours &amp; Classe rattachée</label>
                        <select name="cours_id" id="coursSelect" required onchange="filtrerEtudiants()">
                            <option value="">-- Choisir un cours --</option>
                            <?php foreach($mes_cours as $c): ?>
                                <option value="<?= $c['id'] ?>" data-groupe="<?= $c['groupe_id'] ?>">
                                    <?= htmlspecialchars($c['titre'] . ' (' . $c['groupe_nom'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label>Étudiant</label>
                        <select name="etudiant_id" id="etudiantSelect" required disabled>
                            <option value="">-- Choisir d'abord un cours --</option>
                            <?php foreach($etudiants_possibles as $e): ?>
                                <option value="<?= $e['id'] ?>" data-groupe="<?= $e['groupe_id'] ?>">
                                    <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label>Note / 20</label>
                        <input type="number" name="valeur_note" step="0.25" min="0" max="20" required placeholder="Ex: 15.5">
                        
                        <label>Appréciation générale</label>
                        <textarea name="commentaire" rows="3" placeholder="Saisir un commentaire..."></textarea>
                        
                        <button type="submit" class="btn-action" style="width:100%; margin-top:10px;">Enregistrer la note</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Dernières évaluations enregistrées</h2>
                    <?php if (!empty($historique_notes)): ?>
                        <span style="font-size:12px; color:var(--text-muted);">Cliquez sur un élève pour voir son profil</span>
                    <?php endif; ?>
                </div>
                <?php if (empty($historique_notes)): ?>
                    <div class="empty-state">
                        <p>Aucune note enregistrée pour le moment.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Étudiant</th>
                            <th>Matière</th>
                            <th>Note</th>
                        </tr>
                        <?php foreach(array_slice($historique_notes, 0, 10) as $n): ?>
                            <?php 
                                $e_avatar = glob("uploads/avatars/avatar_" . $n['etudiant_id'] . ".*");
                                $e_init = strtoupper(substr($n['etud_prenom'], 0, 1) . substr($n['etud_nom'], 0, 1));
                            ?>
                            <tr class="student-row"
                                onclick="ouvrirProfil(<?= intval($n['etud_id']) ?>)"
                                title="Voir le profil de <?= htmlspecialchars($n['etud_prenom'] . ' ' . $n['etud_nom']) ?>">
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div class="avatar-small" style="width:28px; height:28px; font-size:10px; box-shadow:none;">
                                            <?php if (!empty($e_avatar)): ?>
                                                <img src="<?= $e_avatar[0] ?>" alt="Pic">
                                            <?php else: ?>
                                                <?= $e_init ?>
                                            <?php endif; ?>
                                        </div>
                                        <strong><?= htmlspecialchars($n['etud_nom'] . ' ' . $n['etud_prenom']) ?></strong>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($n['cours_titre']) ?></td>
                                <td><span class="badge badge-success"><?= number_format($n['valeur_note'], 2, ',', ' ') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== OVERLAY ===== -->
    <div class="panel-overlay" id="panelOverlay" onclick="fermerProfil()"></div>

    <!-- ===== SLIDE-IN PANEL (lecture seule) ===== -->
    <div class="profil-panel" id="profilPanel">

        <!-- En-tête -->
        <div class="panel-header">
            <div class="panel-avatar" id="panelAvatar">??</div>
            <div class="panel-header-info">
                <h3 id="panelNomComplet">—</h3>
                <div id="panelBadgeRole"></div>
            </div>
            <button class="panel-close" onclick="fermerProfil()" title="Fermer le panneau">✕</button>
        </div>

        <!-- Corps -->
        <div class="panel-body">

            <!-- Loader -->
            <div class="panel-loader" id="panelLoader">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2.5" class="spin-icon">
                    <path stroke-linecap="round" d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
                Chargement du profil…
            </div>

            <!-- Contenu -->
            <div id="panelContent" style="display:none;">

                <!-- SECTION : Informations personnelles -->
                <div class="panel-section">
                    <div class="panel-section-title">Informations personnelles</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Prénom</label>
                            <span class="info-value" id="dispPrenom"></span>
                        </div>
                        <div class="info-item">
                            <label>Nom</label>
                            <span class="info-value" id="dispNom"></span>
                        </div>
                        <div class="info-item full">
                            <label>Adresse email</label>
                            <span class="info-value" id="dispEmail"></span>
                        </div>
                        <div class="info-item full">
                            <label>Classe</label>
                            <span class="info-value" id="dispGroupe"></span>
                        </div>
                    </div>
                </div>

                <!-- SECTION : Parcours académique -->
                <div class="panel-section" id="sectionParcours">
                    <div class="panel-section-title">Parcours académique</div>
                    <div id="panelParcours"></div>
                </div>

            </div>
        </div>

        <!-- Pied du panel -->
        <div class="panel-footer">
            <button class="btn-panel btn-panel-ghost" onclick="fermerProfil()">
                Fermer le profil
            </button>
        </div>
    </div>

    <script>
        // ================================================
        // Script existant : filtre étudiant par cours
        // ================================================
        function filtrerEtudiants() {
            var cs = document.getElementById('coursSelect'); 
            var es = document.getElementById('etudiantSelect');
            var sel = cs.options[cs.selectedIndex]; 
            
            if (!sel.value) { 
                es.disabled = true; 
                return; 
            }
            
            var grp = sel.getAttribute('data-groupe'); 
            es.disabled = false;
            
            for (var i = 0; i < es.options.length; i++) {
                if (es.options[i].value === "") {
                    continue;
                }
                if (es.options[i].getAttribute('data-groupe') === grp) {
                    es.options[i].style.display = 'block';
                } else {
                    es.options[i].style.display = 'none';
                }
            }
            es.selectedIndex = 0;
        }

        // ================================================
        // SLIDE-IN PANEL — Consultation du profil élève
        // ================================================

        /** Ouvre le panneau et charge les données de l'étudiant */
        function ouvrirProfil(id) {
            document.getElementById('panelOverlay').classList.add('actif');
            document.getElementById('profilPanel').classList.add('ouvert');

            // Réinitialise le panel
            document.getElementById('panelLoader').style.display  = 'flex';
            document.getElementById('panelContent').style.display = 'none';
            document.getElementById('panelNomComplet').textContent = '—';
            document.getElementById('panelBadgeRole').innerHTML    = '';

            fetch(`get_profil.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('panelLoader').innerHTML =
                            `<span style="color:var(--danger);">⚠️ ${data.error}</span>`;
                        return;
                    }
                    remplirPanel(data);
                })
                .catch(() => {
                    document.getElementById('panelLoader').innerHTML =
                        '<span style="color:var(--danger);">⚠️ Erreur réseau.</span>';
                });
        }

        /** Ferme le panneau */
        function fermerProfil() {
            document.getElementById('panelOverlay').classList.remove('actif');
            document.getElementById('profilPanel').classList.remove('ouvert');
        }

        /** Remplit le panneau avec les données reçues */
        function remplirPanel(data) {
            const u = data.user;

            // Avatar avec initiales
            const initiales = (u.prenom.charAt(0) + u.nom.charAt(0)).toUpperCase();
            document.getElementById('panelAvatar').textContent = initiales;

            document.getElementById('panelNomComplet').textContent = `${u.prenom} ${u.nom}`;
            document.getElementById('panelBadgeRole').innerHTML =
                `<span class="badge badge-neutral">Étudiant</span>`;

            // Infos personnelles
            document.getElementById('dispPrenom').textContent = u.prenom;
            document.getElementById('dispNom').textContent    = u.nom;
            document.getElementById('dispEmail').textContent  = u.email;
            document.getElementById('dispGroupe').textContent = u.nom_groupe || '—';

            // Section parcours (uniquement pour les étudiants)
            document.getElementById('sectionParcours').style.display = u.role === 'etudiant' ? '' : 'none';
            afficherParcours(data);

            document.getElementById('panelLoader').style.display  = 'none';
            document.getElementById('panelContent').style.display = 'block';
        }

        /** Affiche la section parcours : moyenne générale + détail par cours + parcours */
        function afficherParcours(data) {
            let html = '';

            // ---- Moyenne générale ----
            const moy = parseFloat(data.moyenne);
            if (!isNaN(moy) && data.moyenne !== null && data.moyenne !== '') {
                let cls, emoji;
                if (moy >= 14)      { cls = 'moy-good'; emoji = '🏆'; }
                else if (moy >= 10) { cls = 'moy-mid';  emoji = '📊'; }
                else                { cls = 'moy-bad';  emoji = '⚠️'; }

                html += `
                <div class="moy-bloc ${cls}">
                    <span class="moy-emoji">${emoji}</span>
                    <div>
                        <div class="moy-chiffre">${moy.toFixed(2)} <small style="font-size:14px;font-weight:500;">/ 20</small></div>
                        <div class="moy-label">Moyenne générale</div>
                    </div>
                </div>`;
            } else {
                html += `
                <div class="moy-bloc moy-none">
                    <span class="moy-emoji">📊</span>
                    <div>
                        <div style="font-size:15px;font-weight:700;">Aucune note enregistrée</div>
                        <div class="moy-label">Moyenne générale</div>
                    </div>
                </div>`;
            }

            // ---- Détail par cours ----
            if (data.notes_par_cours && data.notes_par_cours.length > 0) {
                html += `<div class="hist-title" style="margin-top:4px;">Détail par cours</div><div>`;
                data.notes_par_cours.forEach(c => {
                    const m = parseFloat(c.moyenne);
                    const couleur = m >= 14 ? '#065f46' : m >= 10 ? '#92400e' : '#991b1b';
                    const bg      = m >= 14 ? '#d1fae5' : m >= 10 ? '#fef3c7' : '#fee2e2';
                    html += `
                    <div class="hist-item" style="justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div class="hist-dot"></div>
                            <div class="hist-nom">${escHtml(c.titre)}</div>
                        </div>
                        <span style="font-size:13px;font-weight:700;padding:3px 10px;border-radius:8px;
                                     background:${bg};color:${couleur};flex-shrink:0;">
                            ${m.toFixed(2)} / 20
                        </span>
                    </div>`;
                });
                html += `</div>`;
            }

            // ---- Parcours académique ----
            html += `<div class="hist-title" style="margin-top:20px;">Parcours académique</div>`;
            if (data.parcours && data.parcours.length > 0) {
                html += `<div>`;
                data.parcours.forEach(p => {
                    const styleActuelle = p.actuelle ? 'font-weight:700; color:var(--primary);' : '';
                    const dotStyle      = p.actuelle ? 'background:var(--primary);' : 'background:#d1d5db;';
                    html += `
                    <div class="hist-item">
                        <div class="hist-dot" style="${dotStyle}"></div>
                        <div>
                            <div class="hist-nom" style="${styleActuelle}">${escHtml(p.label)}</div>
                            ${p.actuelle ? '<div class="hist-annee">Année en cours</div>' : ''}
                        </div>
                    </div>`;
                });
                html += `</div>`;
            } else {
                html += `<p class="hist-empty">Aucun parcours disponible.</p>`;
            }

            document.getElementById('panelParcours').innerHTML = html;
        }

        /** Échappe le HTML pour éviter les injections XSS côté JS */
        function escHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // Fermeture avec la touche Échap
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') fermerProfil();
        });
    </script>
    
    <?php include 'footer.php'; ?>
</body>
</html>
