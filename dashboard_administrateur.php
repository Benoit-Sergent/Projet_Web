<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$stmt_admin = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_admin->execute([$_SESSION['utilisateur_id']]);
$admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1));

$utilisateurs = $db->query("SELECT id, prenom, nom, email, role FROM utilisateurs ORDER BY role, nom")->fetchAll(PDO::FETCH_ASSOC);
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
        <img src="images/logo.jpg" alt="Logo HEJ">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></strong>
                <span>Administrateur</span>
            </div>
            <div class="avatar-small" style="background:#2b2b2b;"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_administrateur.php" class="active">Membres</a>
        <a href="gestion_cours.php">Programme</a>
        <a href="parametres.php">Paramètres</a>
        <a href="deconnexion.php">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1 style="margin:0; color:var(--primary);">Gestion des Comptes</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Inscrivez ou retirez des accès à l'écosystème numérique.</p>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header"><h2>Annuaire des Membres (<?= count($utilisateurs) ?>)</h2></div>
                <table>
                    <tr><th>Nom complet</th><th>Email</th><th>Statut</th><th>Action</th></tr>
                    <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?></strong></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge badge-neutral" style="text-transform: capitalize;"><?= htmlspecialchars($u['role']) ?></span></td>
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

            <div class="card">
                <div class="card-header"><h2>Nouveau Compte</h2></div>
                <form action="traitement_inscription.php" method="POST">
                    <label>Prénom</label>
                    <input type="text" name="prenom" required placeholder="Ex: Jean">
                    <label>Nom de famille</label>
                    <input type="text" name="nom" required placeholder="Ex: Dupont">
                    <label>Adresse email</label>
                    <input type="email" name="email" required placeholder="j.dupont@univ.edu">
                    <label>Rôle affecté</label>
                    <select name="role" required>
                        <option value="etudiant">Étudiant</option>
                        <option value="professeur">Professeur</option>
                        <option value="administrateur">Administrateur</option>
                    </select>
                    <label>Mot de passe initial</label>
                    <input type="password" name="mot_de_passe" required>
                    <button type="submit" class="btn-action" style="width: 100%; margin-top: 20px;">Créer l'utilisateur</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>