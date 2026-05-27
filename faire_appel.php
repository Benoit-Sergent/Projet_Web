<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$prof_id = $_SESSION['utilisateur_id'];

$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$prof_id]); $messages_non_lus = $stmt_unread->fetchColumn();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sauvegarder') {
    $c_id = intval($_POST['cours_id']); $date = $_POST['date_cours'];
    $stmt = $db->prepare("INSERT INTO presences (etudiant_id, cours_id, date_cours, statut) VALUES (?, ?, ?, ?)");
    foreach($_POST['statuts'] as $etud_id => $st) {
        $stmt->execute([$etud_id, $c_id, $date, $st]);
    }
    $message = "✅ Appel verrouillé avec succès.";
}

$prof_info = $db->query("SELECT prenom, nom FROM utilisateurs WHERE id = $prof_id")->fetch();
$prof_init = strtoupper(substr($prof_info['prenom'],0,1).substr($prof_info['nom'],0,1));
$prof_avatar = glob("uploads/avatars/avatar_" . $prof_id . ".*");

$stmt_c = $db->prepare("SELECT c.*, g.nom as nom_groupe FROM cours c JOIN groupes g ON c.groupe_id = g.id WHERE c.professeur_id = ?");
$stmt_c->execute([$prof_id]); $mes_cours = $stmt_c->fetchAll();

$c_sel = isset($_GET['cours_id']) ? intval($_GET['cours_id']) : null;
$eleves = [];
if ($c_sel) {
    $stmt_e = $db->prepare("SELECT id, nom, prenom FROM utilisateurs WHERE role='etudiant' AND groupe_id = (SELECT groupe_id FROM cours WHERE id = ?)");
    $stmt_e->execute([$c_sel]); $eleves = $stmt_e->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Appel - SmartCampus</title><link rel="stylesheet" href="style.css"></head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;"><strong><?= htmlspecialchars($prof_info['prenom'].' '.$prof_info['nom']) ?></strong><span>Professeur</span></div>
            <div class="avatar-small">
                <?php if(!empty($prof_avatar)): ?><img src="<?= $prof_avatar[0] ?>" alt="Prof"><?php else: ?><?= $prof_init ?><?php endif; ?>
            </div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_professeur.php">Évaluations</a><a href="faire_appel.php" class="active">Faire l'appel</a>
        <a href="messagerie.php">Messagerie 💬<?php if ($messages_non_lus > 0): ?><span class="notification-badge"><?= $messages_non_lus ?></span><?php endif; ?></a><a href="profil.php">Profil</a><a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        <h1>Registre d'Appel Numérique</h1>
        <?php if($message) echo "<div class='alert alert-success'>$message</div>"; ?>
        
        <div class="card" style="margin-bottom:20px;">
            <form method="GET">
                <label>1. Sélectionner le cours</label>
                <select name="cours_id" required onchange="this.form.submit()">
                    <option value="">-- Choisir un cours --</option>
                    <?php foreach($mes_cours as $c): ?><option value="<?= $c['id'] ?>" <?= $c_sel==$c['id']?'selected':''?>><?= htmlspecialchars($c['titre'].' ('.$c['nom_groupe'].')') ?></option><?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if($c_sel && !empty($eleves)): ?>
            <div class="card">
                <form method="POST">
                    <input type="hidden" name="action" value="sauvegarder">
                    <input type="hidden" name="cours_id" value="<?= $c_sel ?>">
                    <label>Date de la séance</label><input type="date" name="date_cours" value="<?= date('Y-m-d') ?>" required style="max-width:200px;margin-bottom:20px;">
                    <table>
                        <tr><th>Trombi</th><th>Étudiant</th><th>Présence</th></tr>
                        <?php foreach($eleves as $e): 
                            $e_avatar = glob("uploads/avatars/avatar_" . $e['id'] . ".*");
                            $e_init = strtoupper(substr($e['prenom'],0,1).substr($e['nom'],0,1));
                        ?>
                            <tr>
                                <td>
                                    <div class="avatar-small" style="width:30px;height:30px;font-size:11px;">
                                        <?php if(!empty($e_avatar)): ?><img src="<?= $e_avatar[0] ?>" alt="Trombi"><?php else: ?><?= $e_init ?><?php endif; ?>
                                    </div>
                                </td>
                                <td><strong><?= htmlspecialchars($e['nom'].' '.$e['prenom']) ?></strong></td>
                                <td>
                                    <label style="display:inline;margin-right:15px;color:var(--success);"><input type="radio" name="statuts[<?= $e['id'] ?>]" value="present" checked> Présent</label>
                                    <label style="display:inline;margin-right:15px;color:var(--danger);"><input type="radio" name="statuts[<?= $e['id'] ?>]" value="absent"> Absent</label>
                                    <label style="display:inline;color:#d97706;"><input type="radio" name="statuts[<?= $e['id'] ?>]" value="retard"> Retard</label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <button type="submit" class="btn-action" style="margin-top:20px;float:right;">Soumettre la feuille d'appel</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>