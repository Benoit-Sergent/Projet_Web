<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$admin_id = $_SESSION['utilisateur_id'];
$message_succes = ""; $message_erreur = "";

// ACTION : Créer une classe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'creer_groupe') {
    $nom_groupe = trim($_POST['nom_groupe']);
    if (!empty($nom_groupe)) {
        try {
            $stmt = $db->prepare("INSERT INTO groupes (nom) VALUES (?)");
            $stmt->execute([$nom_groupe]);
            $message_succes = "La classe '$nom_groupe' a été créée avec succès.";
        } catch (PDOException $e) { 
            $message_erreur = "Cette classe existe déjà dans le système."; 
        }
    }
}

// Données pour le dashboard
$groupes = $db->query("SELECT * FROM groupes ORDER BY nom")->fetchAll();
$utilisateurs = $db->query("SELECT u.*, g.nom as nom_groupe FROM utilisateurs u LEFT JOIN groupes g ON u.groupe_id = g.id ORDER BY u.role, u.nom")->fetchAll();

$nb_etudiants = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'etudiant'")->fetchColumn();
$nb_profs = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'professeur'")->fetchColumn();
$nb_classes = count($groupes);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* === STATS === */
        .stats-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { display: flex; align-items: center; padding: 24px; gap: 20px; }
        .stat-icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; }
        .icon-indigo { background: #e0e7ff; color: #4f46e5; }
        .icon-emerald { background: #d1fae5; color: #10b981; }
        .icon-amber { background: #fef3c7; color: #d97706; }
        .stat-info h2 { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .stat-info .stat-value { font-size: 32px; font-weight: 700; line-height: 1; color: var(--text-main); }
        .form-section { display: flex; flex-direction: column; gap: 20px; }

        /* === ANNUAIRE : lignes cliquables === */
        .user-row { cursor: pointer; transition: background 0.15s; }
        .user-row:hover { background: rgba(79, 70, 229, 0.04); }
        .user-row:hover td:first-child strong { color: var(--primary, #4f46e5); }

        /* === FILTRES === */
        .filtres-bar {
            display: flex; gap: 8px; flex-wrap: wrap;
            align-items: center; padding: 14px 0 4px;
        }
        .filtre-btn {
            padding: 6px 14px; border-radius: 8px;
            border: 1.5px solid var(--border, #e5e7eb);
            font-size: 13px; font-weight: 600; cursor: pointer;
            background: var(--bg, #fff); color: var(--text-muted);
            transition: all 0.18s; font-family: inherit;
        }
        .filtre-btn:hover { border-color: var(--primary, #4f46e5); color: var(--primary, #4f46e5); }
        .filtre-btn.actif { background: var(--primary, #4f46e5); color: #fff; border-color: var(--primary, #4f46e5); }
        .filtre-select {
            display: none;
            padding: 6px 12px; border-radius: 8px;
            border: 1.5px solid var(--border, #e5e7eb);
            font-size: 13px; font-weight: 600;
            color: var(--text-main); background: var(--bg, #fff);
            cursor: pointer; font-family: inherit;
        }
        .annuaire-compteur {
            margin-left: auto; font-size: 12px;
            color: var(--text-muted); font-style: italic;
        }

        /* === SPINNER === */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spin-icon { animation: spin 0.9s linear infinite; display: inline-block; }

        /* === OVERLAY === */
        .panel-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15, 15, 25, 0.35);
            backdrop-filter: blur(3px); z-index: 998;
        }
        .panel-overlay.actif { display: block; }

        /* === SLIDE-IN PANEL === */
        .profil-panel {
            position: fixed; top: 65px; right: 0; bottom: 0;
            width: 440px; max-width: 96vw;
            background: var(--bg-card, #fff);
            box-shadow: -10px 0 48px rgba(0,0,0,0.13);
            z-index: 999;
            transform: translateX(100%);
            transition: transform 0.32s cubic-bezier(.4,0,.2,1);
            display: flex; flex-direction: column; overflow: hidden;
        }
        .profil-panel.ouvert { transform: translateX(0); }

        /* --- Header du panel --- */
        .panel-header {
            padding: 22px 24px;
            display: flex; align-items: center; gap: 16px;
            border-bottom: 1px solid var(--border, #e5e7eb);
            flex-shrink: 0;
        }
        .panel-avatar {
            width: 54px; height: 54px; border-radius: 15px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 19px; font-weight: 800; color: #fff;
            letter-spacing: 0.04em;
        }
        .avatar-etudiant      { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); }
        .avatar-professeur    { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .avatar-administrateur{ background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); }

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
            transition: background 0.18s, color 0.18s;
        }
        .panel-close:hover { background: #e5e7eb; color: var(--text-main); }

        /* --- Corps du panel --- */
        .panel-body { flex: 1; overflow-y: auto; padding: 24px; }

        .panel-section { margin-bottom: 28px; }
        .panel-section-title {
            font-size: 10.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.08em; color: var(--text-muted);
            margin-bottom: 16px; padding-bottom: 9px;
            border-bottom: 1.5px solid var(--border, #e5e7eb);
        }

        /* --- Grille d'infos --- */
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
            outline: none;
            border-color: var(--primary, #4f46e5);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* --- Parcours académique --- */
        .moy-bloc {
            display: inline-flex; align-items: center; gap: 12px;
            padding: 13px 18px; border-radius: 13px;
            margin-bottom: 18px; width: 100%; box-sizing: border-box;
        }
        .moy-chiffre { font-size: 26px; font-weight: 800; line-height: 1; }
        .moy-label   { font-size: 12px; font-weight: 500; opacity: 0.75; margin-top: 2px; }
        .moy-good { background: #d1fae5; color: #065f46; }
        .moy-mid  { background: #fef3c7; color: #92400e; }
        .moy-bad  { background: #fee2e2; color: #991b1b; }
        .moy-none { background: #f3f4f6; color: #6b7280; }
        .moy-emoji { font-size: 22px; }

        .hist-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 10px;
        }
        .hist-item {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 10px 0; border-bottom: 1px solid var(--border, #f0f0f0);
        }
        .hist-item:last-child { border-bottom: none; }
        .hist-dot {
            width: 9px; height: 9px; border-radius: 50%;
            background: var(--primary, #4f46e5); flex-shrink: 0; margin-top: 4px;
        }
        .hist-nom   { font-weight: 600; font-size: 14px; color: var(--text-main); }
        .hist-annee { font-size: 12px; color: var(--text-muted); margin-top: 1px; }

        .hist-empty {
            font-size: 13px; color: var(--text-muted);
            font-style: italic; padding: 8px 0;
        }

        /* --- Pied du panel --- */
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

        /* --- Modes lecture / édition --- */
        .profil-panel.mode-lecture .edit-only  { display: none !important; }
        .profil-panel.mode-edition .read-only  { display: none !important; }

        /* --- Loader --- */
        .panel-loader {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 12px;
            height: 200px; color: var(--text-muted); font-size: 14px;
        }

        /* --- Toast de confirmation --- */
        .panel-toast {
            position: fixed; bottom: 28px; right: 28px;
            padding: 13px 22px; border-radius: 11px;
            font-size: 14px; font-weight: 600; color: #fff;
            z-index: 1001; opacity: 0; pointer-events: none;
            transform: translateY(8px);
            transition: opacity 0.25s, transform 0.25s;
        }
        .panel-toast.show   { opacity: 1; transform: translateY(0); }
        .panel-toast.success{ background: #065f46; }
        .panel-toast.error  { background: #991b1b; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="color:var(--primary);">Supervision de l'établissement</h1>
            <p style="color:var(--text-muted); margin:0;">Gérez les effectifs, les classes et les comptes utilisateurs.</p>
        </div>

        <?php if ($message_succes): ?><div class="alert alert-success"><span>✅ <?= $message_succes ?></span></div><?php endif; ?>
        <?php if ($message_erreur): ?><div class="alert alert-error"><span>⚠️ <?= $message_erreur ?></span></div><?php endif; ?>

        <div class="stats-container">
            <div class="card stat-card">
                <div class="stat-icon icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                </div>
                <div class="stat-info"><h2>Étudiants</h2><div class="stat-value"><?= $nb_etudiants ?></div></div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon icon-emerald">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                </div>
                <div class="stat-info"><h2>Classes</h2><div class="stat-value"><?= $nb_classes ?></div></div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon icon-amber">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                </div>
                <div class="stat-info"><h2>Enseignants</h2><div class="stat-value"><?= $nb_profs ?></div></div>
            </div>
        </div>

        <div class="dashboard-grid inverse">
            <div class="form-section">
                <div class="card">
                    <div class="card-header"><h2>Nouvelle Classe</h2></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="creer_groupe">
                        <label>Nom de la classe (Ex: ING2 - Grp A)</label>
                        <input type="text" name="nom_groupe" required placeholder="Saisir l'intitulé...">
                        <button type="submit" class="btn-action" style="width:100%; margin-top:5px;">Créer la classe</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><h2>Ouvrir un Compte</h2></div>
                    <form action="traitement_inscription.php" method="POST">
                        <div style="display:flex; gap:10px;">
                            <div style="flex:1;"><label>Prénom</label><input type="text" name="prenom" required></div>
                            <div style="flex:1;"><label>Nom</label><input type="text" name="nom" required></div>
                        </div>
                        <label>Email institutionnel</label><input type="email" name="email" required placeholder="nom@smartcampus.fr">
                        <label>Mot de passe provisoire</label><input type="password" name="mot_de_passe" required>
                        <label>Rôle attribué</label>
                        <select name="role" id="roleSelect" required onchange="toggleGroupSelect()">
                            <option value="etudiant">Étudiant</option>
                            <option value="professeur">Professeur</option>
                            <option value="administrateur">Administrateur</option>
                        </select>
                        <div id="groupSelectContainer">
                            <label>Affectation (Classe)</label>
                            <select name="groupe_id" id="groupSelect">
                                <option value="">-- Assigner à une classe --</option>
                                <?php foreach($groupes as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-action" style="width:100%; margin-top:10px;">Générer l'accès</button>
                    </form>
                </div>
            </div>

            <!-- ===== ANNUAIRE ===== -->
            <div class="card">
                <div class="card-header" style="flex-direction:column; align-items:flex-start; gap:4px;">
                    <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                        <h2>Annuaire</h2>
                        <span style="font-size:12px; color:var(--text-muted);">Cliquez sur un membre pour voir son profil</span>
                    </div>

                    <!-- Barre de filtres -->
                    <div class="filtres-bar">
                        <button class="filtre-btn actif" onclick="filtrerRole('tous', this)">
                            Tous
                            <span style="margin-left:5px; font-size:11px; opacity:0.7;"><?= count($utilisateurs) ?></span>
                        </button>
                        <button class="filtre-btn" onclick="filtrerRole('etudiant', this)">
                            Étudiants
                            <span style="margin-left:5px; font-size:11px; opacity:0.7;"><?= $nb_etudiants ?></span>
                        </button>
                        <button class="filtre-btn" onclick="filtrerRole('professeur', this)">
                            Professeurs
                            <span style="margin-left:5px; font-size:11px; opacity:0.7;"><?= $nb_profs ?></span>
                        </button>
                        <button class="filtre-btn" onclick="filtrerRole('administrateur', this)">
                            Admins
                        </button>

                        <!-- Filtre par classe, visible uniquement pour "Étudiants" -->
                        <select id="filtreClasse" class="filtre-select" onchange="appliquerFiltres()">
                            <option value="">— Toutes les classes —</option>
                            <?php foreach($groupes as $g): ?>
                                <option value="<?= htmlspecialchars($g['nom']) ?>"><?= htmlspecialchars($g['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <span class="annuaire-compteur" id="annuaireCompteur"><?= count($utilisateurs) ?> membres</span>
                    </div>
                </div>

                <table>
                    <tr><th>Identité</th><th>Rôle</th><th>Classe</th><th>Action</th></tr>
                    <?php foreach ($utilisateurs as $u): ?>
                        <tr class="user-row"
                            data-user-id="<?= $u['id'] ?>"
                            data-role="<?= $u['role'] ?>"
                            data-groupe="<?= htmlspecialchars($u['nom_groupe'] ?? '') ?>"
                            onclick="ouvrirProfil(<?= $u['id'] ?>)"
                            title="Voir le profil de <?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?>">
                            <td>
                                <strong><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?></strong><br>
                                <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></span>
                            </td>
                            <td>
                                <?php if($u['role'] == 'administrateur'): ?>
                                    <span class="badge badge-danger">Admin</span>
                                <?php elseif($u['role'] == 'professeur'): ?>
                                    <span class="badge badge-success">Professeur</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">Étudiant</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $u['nom_groupe']
                                    ? '<span style="color:var(--primary);font-weight:600;">'.htmlspecialchars($u['nom_groupe']).'</span>'
                                    : '—' ?>
                            </td>
                            <td>
                                <?php if($u['id'] != $_SESSION['utilisateur_id']): ?>
                                    <a href="supprimer_utilisateur.php?id=<?= $u['id'] ?>"
                                       style="color:var(--danger); font-size:13px; font-weight:600;"
                                       onclick="event.stopPropagation(); return confirm('Supprimer ce membre ?');">
                                        Révoquer
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== OVERLAY ===== -->
    <div class="panel-overlay" id="panelOverlay" onclick="fermerProfil()"></div>

    <!-- ===== SLIDE-IN PANEL ===== -->
    <div class="profil-panel mode-lecture" id="profilPanel">

        <!-- En-tête -->
        <div class="panel-header">
            <div class="panel-avatar avatar-etudiant" id="panelAvatar">??</div>
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

            <!-- Contenu (masqué pendant le chargement) -->
            <div id="panelContent" style="display:none;">

                <!-- SECTION : Informations personnelles -->
                <div class="panel-section">
                    <div class="panel-section-title">Informations personnelles</div>
                    <div class="info-grid">

                        <div class="info-item">
                            <label>Prénom</label>
                            <span class="info-value read-only" id="dispPrenom"></span>
                            <input type="text" class="edit-only" id="editPrenom" placeholder="Prénom">
                        </div>

                        <div class="info-item">
                            <label>Nom</label>
                            <span class="info-value read-only" id="dispNom"></span>
                            <input type="text" class="edit-only" id="editNom" placeholder="Nom">
                        </div>

                        <div class="info-item full">
                            <label>Adresse email</label>
                            <span class="info-value read-only" id="dispEmail"></span>
                            <input type="email" class="edit-only" id="editEmail" placeholder="email@smartcampus.fr">
                        </div>

                        <div class="info-item">
                            <label>Rôle</label>
                            <span class="info-value read-only" id="dispRole"></span>
                            <select class="edit-only" id="editRole" onchange="toggleGroupeEdit()">
                                <option value="etudiant">Étudiant</option>
                                <option value="professeur">Professeur</option>
                                <option value="administrateur">Administrateur</option>
                            </select>
                        </div>

                        <div class="info-item" id="groupeItem">
                            <label>Classe</label>
                            <span class="info-value read-only" id="dispGroupe"></span>
                            <select class="edit-only" id="editGroupe">
                                <option value="">— Sans classe —</option>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- SECTION : Parcours académique -->
                <div class="panel-section" id="sectionParcours">
                    <div class="panel-section-title">Parcours académique</div>
                    <div id="panelParcours"></div>
                </div>

                <div class="panel-section" id="sectionCours" style="display:none;">
                    <div class="panel-section-title">Cours enseignés</div>
                    <div id="panelCours"></div>
                </div>

            </div>
        </div>

        <!-- Pied du panel -->
        <div class="panel-footer">
            <button class="btn-panel btn-panel-primary read-only" onclick="basculerEdition(true)">
                ✏️ Modifier le profil
            </button>
            <button class="btn-panel btn-panel-success edit-only" id="btnSave" onclick="sauvegarderProfil()">
                💾 Enregistrer
            </button>
            <button class="btn-panel btn-panel-ghost edit-only" onclick="basculerEdition(false)">
                Annuler
            </button>
        </div>
    </div>

    <!-- Toast de confirmation -->
    <div class="panel-toast" id="panelToast"></div>

    <script>
        /* ---- Formulaire d'inscription ---- */
        function toggleGroupSelect() {
            var role = document.getElementById('roleSelect').value;
            document.getElementById('groupSelectContainer').style.display = (role === 'etudiant') ? 'block' : 'none';
        }
        window.onload = toggleGroupSelect;

        /* ======================================================
           FILTRES ANNUAIRE
        ====================================================== */

        let filtreRoleActif = 'tous';

        function filtrerRole(role, btn) {
            filtreRoleActif = role;

            // Mise à jour des boutons
            document.querySelectorAll('.filtre-btn').forEach(b => b.classList.remove('actif'));
            btn.classList.add('actif');

            // Affiche le select de classe uniquement pour les étudiants
            const filtreClasse = document.getElementById('filtreClasse');
            if (role === 'etudiant') {
                filtreClasse.style.display = 'inline-block';
            } else {
                filtreClasse.style.display = 'none';
                filtreClasse.value = '';
            }

            appliquerFiltres();
        }

        function appliquerFiltres() {
            const classeFiltre = document.getElementById('filtreClasse').value;
            let visible = 0;

            document.querySelectorAll('tr.user-row').forEach(row => {
                const roleOk   = filtreRoleActif === 'tous' || row.dataset.role === filtreRoleActif;
                const classeOk = !classeFiltre || row.dataset.groupe === classeFiltre;
                const afficher = roleOk && classeOk;

                row.style.display = afficher ? '' : 'none';
                if (afficher) visible++;
            });

            // Met à jour le compteur
            const label = visible > 1 ? 'membres' : 'membre';
            document.getElementById('annuaireCompteur').textContent = `${visible} ${label}`;
        }

        /* ======================================================
           SLIDE-IN PANEL — Profil utilisateur
        ====================================================== */

        let currentUserId = null;
        let panelData     = null;

        const ROLES_LABELS = {
            etudiant:       'Étudiant',
            professeur:     'Professeur',
            administrateur: 'Administrateur'
        };
        const ROLES_BADGES = {
            etudiant:       'badge-neutral',
            professeur:     'badge-success',
            administrateur: 'badge-danger'
        };

        /** Ouvre le panneau et charge les données du membre */
        function ouvrirProfil(id) {
            currentUserId = id;

            document.getElementById('panelOverlay').classList.add('actif');
            document.getElementById('profilPanel').classList.add('ouvert');

            basculerEdition(false);
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
                    panelData = data;
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
            basculerEdition(false);
        }

        /** Remplit le panneau avec les données reçues */
        function remplirPanel(data) {
            const u = data.user;

            const initiales = (u.prenom.charAt(0) + u.nom.charAt(0)).toUpperCase();
            const avatar = document.getElementById('panelAvatar');
            avatar.textContent = initiales;
            avatar.className   = `panel-avatar avatar-${u.role}`;

            document.getElementById('panelNomComplet').textContent = `${u.prenom} ${u.nom}`;
            document.getElementById('panelBadgeRole').innerHTML =
                `<span class="badge ${ROLES_BADGES[u.role] || 'badge-neutral'}">${ROLES_LABELS[u.role] || u.role}</span>`;

            document.getElementById('dispPrenom').textContent = u.prenom;
            document.getElementById('dispNom').textContent    = u.nom;
            document.getElementById('dispEmail').textContent  = u.email;
            document.getElementById('dispRole').textContent   = ROLES_LABELS[u.role] || u.role;
            document.getElementById('dispGroupe').textContent = u.nom_groupe || '—';

            document.getElementById('editPrenom').value = u.prenom;
            document.getElementById('editNom').value    = u.nom;
            document.getElementById('editEmail').value  = u.email;
            document.getElementById('editRole').value   = u.role;

            const sel = document.getElementById('editGroupe');
            sel.innerHTML = '<option value="">— Sans classe —</option>';
            data.groupes.forEach(g => {
                const opt = document.createElement('option');
                opt.value       = g.id;
                opt.textContent = g.nom;
                if (String(g.id) === String(u.groupe_id)) opt.selected = true;
                sel.appendChild(opt);
            });
            
            // Affiche la section académique uniquement pour les étudiants
            document.getElementById('sectionParcours').style.display = u.role === 'etudiant' ? '' : 'none';
                                    
            // Affiche la section cours uniquement pour les professeurs
            const sectionCours = document.getElementById('sectionCours');
            sectionCours.style.display = u.role === 'professeur' ? '' : 'none';
            if (u.role === 'professeur') afficherCours(data);

            toggleGroupeEdit();
            afficherParcours(data);

            document.getElementById('panelLoader').style.display  = 'none';
            document.getElementById('panelContent').style.display = 'block';
        }

        /** Affiche la section parcours (moyenne + détail par cours + parcours) */
        function afficherParcours(data) {
            let html = '';

            // Moyenne générale
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

            // Détail par cours
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

            // Parcours académique
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

        /** Affiche la liste des cours enseignés par un professeur */
        function afficherCours(data) {
            const el = document.getElementById('panelCours');

            if (!data.cours_enseignes || data.cours_enseignes.length === 0) {
                el.innerHTML = `<p class="hist-empty">Aucun cours assigné pour le moment.</p>`;
                return;
            }

            const CATEGORIE_COLORS = {
                'Sciences':     { bg: '#e0e7ff', color: '#3730a3' },
                'Informatique': { bg: '#d1fae5', color: '#065f46' },
                'Langues':      { bg: '#fef3c7', color: '#92400e' },
                'Management':   { bg: '#fee2e2', color: '#991b1b' },
            };

            let html = `
                <div style="font-size:12px; color:var(--text-muted); margin-bottom:12px;">
                    ${data.cours_enseignes.length} cours au total
                </div>
                <div>`;

            data.cours_enseignes.forEach(c => {
                const cat    = CATEGORIE_COLORS[c.categorie] || { bg: '#f3f4f6', color: '#6b7280' };
                const hDebut = c.heure_debut ? c.heure_debut.slice(0, 5) : '—';
                const hFin   = c.heure_fin   ? c.heure_fin.slice(0, 5)   : '—';

                html += `
                <div class="hist-item" style="align-items:flex-start; gap:12px; padding: 12px 0;">
                    <div class="hist-dot" style="background:#10b981; margin-top:6px; flex-shrink:0;"></div>
                    <div style="flex:1; min-width:0;">

                        <!-- Titre + badge catégorie -->
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:5px;">
                            <span class="hist-nom">${escHtml(c.titre)}</span>
                            <span style="font-size:10px; font-weight:700; padding:2px 8px; border-radius:6px;
                                        background:${cat.bg}; color:${cat.color}; flex-shrink:0;">
                                ${escHtml(c.categorie)}
                            </span>
                        </div>

                        <!-- Infos horaires -->
                        <div style="display:flex; flex-wrap:wrap; gap:10px; font-size:12px; color:var(--text-muted);">
                            <span>📅 ${escHtml(c.jour)}</span>
                            <span>🕒 ${hDebut} – ${hFin}</span>
                            <span>📍 ${escHtml(c.salle || '—')}</span>
                            <span style="color:var(--primary); font-weight:600;">
                                👥 ${escHtml(c.nom_groupe || 'Sans classe')}
                            </span>
                        </div>

                    </div>
                </div>`;
            });

            html += `</div>`;
            el.innerHTML = html;
        }

        /** Bascule entre mode lecture et édition */
        function basculerEdition(actif) {
            const panel = document.getElementById('profilPanel');
            if (actif) {
                panel.classList.remove('mode-lecture');
                panel.classList.add('mode-edition');
            } else {
                panel.classList.remove('mode-edition');
                panel.classList.add('mode-lecture');
            }
        }

        /** Affiche/masque le champ groupe selon le rôle sélectionné */
        function toggleGroupeEdit() {
            const role = document.getElementById('editRole').value;
            document.getElementById('groupeItem').style.display = (role === 'etudiant') ? '' : 'none';
        }

        /** Sauvegarde les modifications via AJAX */
        function sauvegarderProfil() {
            const payload = {
                id:        currentUserId,
                prenom:    document.getElementById('editPrenom').value.trim(),
                nom:       document.getElementById('editNom').value.trim(),
                email:     document.getElementById('editEmail').value.trim(),
                role:      document.getElementById('editRole').value,
                groupe_id: document.getElementById('editGroupe').value || null
            };

            if (!payload.prenom || !payload.nom || !payload.email) {
                afficherToast('Veuillez remplir tous les champs obligatoires.', 'error');
                return;
            }

            const btnSave = document.getElementById('btnSave');
            btnSave.textContent = 'Enregistrement…';
            btnSave.disabled    = true;

            fetch('modifier_profil.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    panelData.user.prenom    = payload.prenom;
                    panelData.user.nom       = payload.nom;
                    panelData.user.email     = payload.email;
                    panelData.user.role      = payload.role;
                    panelData.user.groupe_id = payload.groupe_id;
                    const groupe = panelData.groupes.find(g => String(g.id) === String(payload.groupe_id));
                    panelData.user.nom_groupe = groupe ? groupe.nom : null;

                    remplirPanel(panelData);
                    basculerEdition(false);
                    actualiserLigneTableau(payload);
                    afficherToast('✅ Profil mis à jour avec succès !', 'success');
                } else {
                    afficherToast('⚠️ ' + (data.error || 'Erreur lors de la mise à jour.'), 'error');
                }
            })
            .catch(() => afficherToast('⚠️ Erreur réseau. Veuillez réessayer.', 'error'))
            .finally(() => {
                btnSave.textContent = '💾 Enregistrer';
                btnSave.disabled    = false;
            });
        }

        /** Met à jour la ligne du tableau sans recharger la page */
        function actualiserLigneTableau(payload) {
            const row = document.querySelector(`tr[data-user-id="${currentUserId}"]`);
            if (!row) return;

            const groupe = panelData.groupes.find(g => String(g.id) === String(payload.groupe_id));

            // Met à jour les data-attributes pour que les filtres restent cohérents
            row.dataset.role   = payload.role;
            row.dataset.groupe = groupe ? groupe.nom : '';

            row.cells[0].innerHTML = `
                <strong>${escHtml(payload.nom)} ${escHtml(payload.prenom)}</strong><br>
                <span style="font-size:12px;color:var(--text-muted);">${escHtml(payload.email)}</span>`;

            row.cells[1].innerHTML =
                `<span class="badge ${ROLES_BADGES[payload.role] || 'badge-neutral'}">${ROLES_LABELS[payload.role] || payload.role}</span>`;

            row.cells[2].innerHTML = groupe
                ? `<span style="color:var(--primary);font-weight:600;">${escHtml(groupe.nom)}</span>`
                : '—';

            // Ré-applique les filtres en cours pour masquer la ligne si elle ne correspond plus
            appliquerFiltres();
        }

        /** Affiche un toast de notification */
        function afficherToast(message, type = 'success') {
            const t = document.getElementById('panelToast');
            t.textContent = message;
            t.className   = `panel-toast ${type} show`;
            setTimeout(() => { t.classList.remove('show'); }, 3200);
        }

        /** Échappe le HTML pour éviter les injections XSS côté JS */
        function escHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g,'&amp;')
                .replace(/</g,'&lt;')
                .replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;');
        }

        // Fermeture avec la touche Échap
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') fermerProfil();
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>