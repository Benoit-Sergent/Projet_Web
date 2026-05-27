<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$message_succes = "";
$message_erreur = "";

// LOGIQUE : Création d'un groupe (directement sur cette page pour simplifier)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'creer_groupe') {
    $nom_groupe = trim($_POST['nom_groupe']);
    if (!empty($nom_groupe)) {
        try {
            $stmt = $db->prepare("INSERT INTO groupes (nom) VALUES (?)");
            $stmt->execute([$nom_groupe]);
            $message_succes = "Le groupe '$nom_groupe' a été créé avec succès.";
        } catch (PDOException $e) {
            $message_erreur = "Erreur : Ce nom de groupe existe déjà.";
        }
    }
}

// Infos Admin
$stmt_admin = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_admin->execute([$_SESSION['utilisateur_id']]);
$admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1));

// Récupération des données
$groupes = $db->query("SELECT * FROM groupes ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$utilisateurs = $db->query("
    SELECT u.id, u.prenom, u.nom, u.email, u.role, g.nom as nom_groupe 
    FROM utilisateurs u 
    LEFT JOIN groupes g ON u.groupe_id = g.id 
    ORDER BY u.role, u.nom
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo HEJ" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></strong>
                <span>Administrateur</span>
            </div>
            <div class="avatar-small" style="background:#2b2b2b;"><?= $initiales ?></div>
        </div>
    </header>

<nav class="top-nav">
        <a href="dashboard_administrateur.php" <?= basename($_SERVER['PHP_SELF']) == 'dashboard_administrateur.php' ? 'class="active"' : '' ?>>Membres & Classes</a>
        <a href="gestion_cours.php" <?= basename($_SERVER['PHP_SELF']) == 'gestion_cours.php' ? 'class="active"' : '' ?>>Programme</a>
        <a href="gestion_absences.php" <?= basename($_SERVER['PHP_SELF']) == 'gestion_absences.php' ? 'class="active"' : '' ?>>Scolarité (Absences)</a>
        <a href="parametres.php">Paramètres</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1 style="margin:0; color:var(--primary);">Gestion des Comptes & Classes</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Gérez la scolarité et l'écosystème numérique.</p>
        </div>

        <?php if ($message_succes): ?><div class="alert alert-success"><?= $message_succes ?></div><?php endif; ?>
        <?php if ($message_erreur): ?><div class="alert alert-error"><?= $message_erreur ?></div><?php endif; ?>

        <div class="dashboard-grid" style="grid-template-columns: 1fr 2fr;">
            
            <div>
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><h2>Créer une Classe</h2></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="creer_groupe">
                        <input type="text" name="nom_groupe" required placeholder="Ex: L1 - Informatique">
                        <button type="submit" class="btn-action" style="width: 100%; margin-top: 10px;">Créer le groupe</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><h2>Nouveau Compte</h2></div>
                    <form action="traitement_inscription.php" method="POST">
                        <input type="text" name="prenom" required placeholder="Prénom">
                        <input type="text" name="nom" required placeholder="Nom">
                        <input type="email" name="email" required placeholder="Adresse email">
                        
                        <label>Rôle affecté</label>
                        <select name="role" id="selectRole" required onchange="toggleGroupeSelect()">
                            <option value="etudiant">Étudiant</option>
                            <option value="professeur">Professeur</option>
                            <option value="administrateur">Administrateur</option>
                        </select>
                        
                        <select name="groupe_id" id="selectGroupe">
                            <option value="">-- Assigner à une classe --</option>
                            <?php foreach($groupes as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="password" name="mot_de_passe" required placeholder="Mot de passe initial">
                        <button type="submit" class="btn-action" style="width: 100%; margin-top: 20px;">Créer l'utilisateur</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Annuaire des Membres (<?= count($utilisateurs) ?>)</h2></div>
                <table>
                    <tr><th>Nom complet</th><th>Rôle</th><th>Classe</th><th>Action</th></tr>
                    <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?></strong><br>
                                <span style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></span>
                            </td>
                            <td><span class="badge badge-neutral" style="text-transform: capitalize;"><?= htmlspecialchars($u['role']) ?></span></td>
                            
                            <td><?= $u['nom_groupe'] ? '<span style="color:var(--primary); font-weight:600; font-size:12px;">'.htmlspecialchars($u['nom_groupe']).'</span>' : '-' ?></td>
                            
                            <td>
                                <?php if ($u['id'] !== $_SESSION['utilisateur_id']): ?>
                                    <a href="supprimer_utilisateur.php?id=<?= $u['id'] ?>" style="color: var(--danger); font-weight: 600; text-decoration: none; font-size:13px;" onclick="return confirm('Confirmer la suppression définitive ?');">Retirer</a>
                                <?php else: ?>
                                    <span style="color: var(--border); font-size:12px; font-style:italic;">Votre compte</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

        </div>
    </div>

    <script>
        function toggleGroupeSelect() {
            var role = document.getElementById('selectRole').value;
            document.getElementById('selectGroupe').style.display = (role === 'etudiant') ? 'block' : 'none';
        }
        window.onload = toggleGroupeSelect; // Lancer au chargement de la page
    </script>
</body>
</html>