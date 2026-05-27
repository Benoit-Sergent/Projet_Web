<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

// Compter les messages non lus pour le badge
$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$_SESSION['utilisateur_id']]);
$messages_non_lus = $stmt_unread->fetchColumn();

$message_succes = ""; $message_erreur = "";

// Action : Créer un groupe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'creer_groupe') {
    $nom_groupe = trim($_POST['nom_groupe']);
    if (!empty($nom_groupe)) {
        try {
            $stmt = $db->prepare("INSERT INTO groupes (nom) VALUES (?)");
            $stmt->execute([$nom_groupe]);
            $message_succes = "La classe '$nom_groupe' a été configurée.";
        } catch (PDOException $e) {
            $message_erreur = "Cette classe existe déjà.";
        }
    }
}

// Données d'affichage
$admin_id = $_SESSION['utilisateur_id'];
$admin_info = $db->query("SELECT prenom, nom FROM utilisateurs WHERE id = $admin_id")->fetch();
$initiales = strtoupper(substr($admin_info['prenom'], 0, 1) . substr($admin_info['nom'], 0, 1));

$groupes = $db->query("SELECT * FROM groupes ORDER BY nom")->fetchAll();
$utilisateurs = $db->query("
    SELECT u.*, g.nom as nom_groupe 
    FROM utilisateurs u 
    LEFT JOIN groupes g ON u.groupe_id = g.id 
    ORDER BY u.role, u.nom
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><title>Admin - SmartCampus</title><link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;"><strong><?= htmlspecialchars($admin_info['prenom'].' '.$admin_info['nom']) ?></strong><span>Administrateur</span></div>
            <div class="avatar-small" style="background:#2b2b2b;"><?= $initiales ?></div>
        </div>
    </header>
<nav class="top-nav">
    <a href="dashboard_administrateur.php" class="active">Membres & Classes</a>
    <a href="gestion_cours.php">Programme</a>
    <a href="gestion_absences.php">Scolarité (Absences)</a>
    <a href="rapports_admin.php">📊 Rapports</a> <a href="messagerie.php">Messagerie 💬<?php if ($messages_non_lus > 0): ?><span class="notification-badge"><?= $messages_non_lus ?></span><?php endif; ?></a>
    <a href="profil.php">Profil</a>
    <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
</nav>

    <div class="container">
        <h1>Gestion de l'Établissement</h1>
        <?php if ($message_succes): ?><div class="alert alert-success"><?= $message_succes ?></div><?php endif; ?>
        <?php if ($message_erreur): ?><div class="alert alert-error"><?= $message_erreur ?></div><?php endif; ?>

        <div class="dashboard-grid inverse">
            <div>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header"><h2>Créer une Classe / Groupe</h2></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="creer_groupe">
                        <input type="text" name="nom_groupe" required placeholder="Ex: ING2 - Groupe 1">
                        <button type="submit" class="btn-action" style="width:100%; margin-top:10px;">Créer la classe</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><h2>Ouvrir un Compte</h2></div>
                    <form action="traitement_inscription.php" method="POST">
                        <input type="text" name="prenom" required placeholder="Prénom">
                        <input type="text" name="nom" required placeholder="Nom">
                        <input type="email" name="email" required placeholder="Adresse email">
                        <input type="password" name="mot_de_passe" required placeholder="Mot de passe initial">
                        <label>Rôle</label>
                        <select name="role" id="roleSelect" required onchange="toggleGroupSelect()">
                            <option value="etudiant">Étudiant</option>
                            <option value="professeur">Professeur</option>
                            <option value="administrateur">Administrateur</option>
                        </select>
                        <select name="groupe_id" id="groupSelect">
                            <option value="">-- Assigner à une classe --</option>
                            <?php foreach($groupes as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nom']) ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-action" style="width:100%; margin-top:15px;">Générer le compte</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Membres Enregistrés (<?= count($utilisateurs) ?>)</h2></div>
                <table>
                    <tr><th>Nom Complet</th><th>Rôle</th><th>Classe</th><th>Action</th></tr>
                    <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?></strong><br><span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></span></td>
                            <td><span class="badge badge-neutral" style="text-transform:capitalize;"><?= $u['role'] ?></span></td>
                            <td><?= $u['nom_groupe'] ? '<span style="color:var(--primary);font-weight:600;">'.htmlspecialchars($u['nom_groupe']).'</span>' : '-' ?></td>
                            <td>
                                <?php if($u['id'] != $_SESSION['utilisateur_id']): ?>
                                    <a href="supprimer_utilisateur.php?id=<?= $u['id'] ?>" style="color:var(--danger);font-weight:600;text-decoration:none;" onclick="return confirm('Supprimer ce compte ?');">Retirer</a>
                                <?php else: ?><em>Vous</em><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <script>
        function toggleGroupSelect() {
            document.getElementById('groupSelect').style.display = (document.getElementById('roleSelect').value === 'etudiant') ? 'block' : 'none';
        }
        window.onload = toggleGroupSelect;
    </script>
</body>
</html>