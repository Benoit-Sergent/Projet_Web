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
        .stats-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { display: flex; align-items: center; padding: 24px; gap: 20px; }
        .stat-icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; }
        .icon-indigo { background: #e0e7ff; color: #4f46e5; }
        .icon-emerald { background: #d1fae5; color: #10b981; }
        .icon-amber { background: #fef3c7; color: #d97706; }
        .stat-info h2 { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .stat-info .stat-value { font-size: 32px; font-weight: 700; line-height: 1; color: var(--text-main); }
        .form-section { display: flex; flex-direction: column; gap: 20px; }
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
                            <option value="etudiant">Étudiant</option><option value="professeur">Professeur</option><option value="administrateur">Administrateur</option>
                        </select>
                        
                        <div id="groupSelectContainer">
                            <label>Affectation (Classe)</label>
                            <select name="groupe_id" id="groupSelect">
                                <option value="">-- Assigner à une classe --</option>
                                <?php foreach($groupes as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nom']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-action" style="width:100%; margin-top:10px;">Générer l'accès</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Annuaire (<?= count($utilisateurs) ?> membres)</h2></div>
                <table>
                    <tr><th>Identité</th><th>Rôle</th><th>Classe</th><th>Action</th></tr>
                    <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?></strong><br>
                                <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></span>
                            </td>
                            <td>
                                <?php if($u['role'] == 'administrateur'): ?><span class="badge badge-danger">Admin</span>
                                <?php elseif($u['role'] == 'professeur'): ?><span class="badge badge-success">Professeur</span>
                                <?php else: ?><span class="badge badge-neutral">Étudiant</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $u['nom_groupe'] ? '<span style="color:var(--primary);font-weight:600;">'.htmlspecialchars($u['nom_groupe']).'</span>' : '-' ?></td>
                            <td>
                                <?php if($u['id'] != $_SESSION['utilisateur_id']): ?>
                                    <a href="supprimer_utilisateur.php?id=<?= $u['id'] ?>" style="color:var(--danger); font-size:13px; font-weight:600;" onclick="return confirm('Supprimer ce membre ?');">Révoquer</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleGroupSelect() { 
            var role = document.getElementById('roleSelect').value;
            document.getElementById('groupSelectContainer').style.display = (role === 'etudiant') ? 'block' : 'none'; 
        }
        window.onload = toggleGroupSelect;
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>