<?php
session_start();
if (!isset($_SESSION['utilisateur_id'])) { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$mon_id = $_SESSION['utilisateur_id'];
$mon_role = $_SESSION['role'];
$message_succes = "";

// Action : Marquer comme lu
if (isset($_GET['action']) && $_GET['action'] === 'lire' && isset($_GET['id'])) {
    $db->prepare("UPDATE messages SET lu = 1 WHERE id = ? AND destinataire_id = ?")->execute([intval($_GET['id']), $mon_id]);
    header("Location: messagerie.php"); exit();
}

// Action : Envoyer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'envoyer') {
    $dest = intval($_POST['destinataire_id']); $sujet = trim($_POST['sujet']); $cont = trim($_POST['contenu']);
    if (!empty($dest) && !empty($sujet) && !empty($cont)) {
        $db->prepare("INSERT INTO messages (expediteur_id, destinataire_id, sujet, contenu) VALUES (?, ?, ?, ?)")->execute([$mon_id, $dest, $sujet, $cont]);
        $message_succes = "Message envoyé.";
    }
}

// Recalcul du badge
$stmt_unread = $db->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
$stmt_unread->execute([$mon_id]); $messages_non_lus = $stmt_unread->fetchColumn();

$inbox = $db->query("SELECT m.*, u.nom, u.prenom, u.role FROM messages m JOIN utilisateurs u ON m.expediteur_id = u.id WHERE m.destinataire_id = $mon_id ORDER BY m.date_envoi DESC")->fetchAll();
$contacts = $db->query("SELECT id, nom, prenom, role FROM utilisateurs WHERE id != $mon_id ORDER BY role, nom")->fetchAll();

$retour_dash = "dashboard_" . $mon_role . ".php";
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Messagerie - SmartCampus</title><link rel="stylesheet" href="style.css">
    <style>
        .msg-card { background: white; border: 1px solid var(--border); border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .msg-card.non-lu { border-left: 4px solid var(--primary); background: #f0f7ff; }
        .msg-header { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
    </style>
</head>
<body>
    <nav class="top-nav">
        <a href="<?= $retour_dash ?>">← Retour au Dashboard</a>
        <a href="messagerie.php" class="active">Messagerie & Notifications (<?= $messages_non_lus ?>)</a>
    </nav>

    <div class="container" style="margin-top:30px;">
        <h1>Messagerie Interne</h1>
        <?php if($message_succes) echo "<div class='alert alert-success'>✅ $message_succes</div>"; ?>

        <div class="dashboard-grid inverse">
            <div class="card">
                <div class="card-header"><h2>Nouveau Message</h2></div>
                <form method="POST">
                    <input type="hidden" name="action" value="envoyer">
                    <label>Destinataire</label>
                    <select name="destinataire_id" required>
                        <option value="">-- Choisir un contact --</option>
                        <?php foreach($contacts as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom'].' '.$c['prenom'].' ('.ucfirst($c['role']).')') ?></option><?php endforeach; ?>
                    </select>
                    <label>Sujet</label><input type="text" name="sujet" required>
                    <label>Message</label><textarea name="contenu" rows="4" required></textarea>
                    <button type="submit" class="btn-action" style="width:100%;margin-top:10px;">Envoyer</button>
                </form>
            </div>

            <div>
                <?php foreach($inbox as $m): ?>
                    <div class="msg-card <?= $m['lu'] == 0 ? 'non-lu' : '' ?>">
                        <div class="msg-header"><span>De: <strong><?= htmlspecialchars($m['nom'].' '.$m['prenom']) ?></strong> (<?= ucfirst($m['role']) ?>)</span><span><?= $m['date_envoi'] ?></span></div>
                        <div style="font-weight:bold;margin-bottom:5px;"><?= htmlspecialchars($m['sujet']) ?></div>
                        <p style="font-size:14px;color:var(--text-main);"><?= nl2br(htmlspecialchars($m['contenu'])) ?></p>
                        <?php if($m['lu'] == 0): ?><div style="text-align:right;"><a href="messagerie.php?action=lire&id=<?= $m['id'] ?>" class="badge badge-success" style="text-decoration:none;">Marquer comme lu</a></div><?php endif; ?>
                    </div>
                <?php endforeach; if(empty($inbox)) echo "<div class='card'><p style='text-align:center;color:var(--text-muted);'>Boîte vide.</p></div>"; ?>
            </div>
        </div>
    </div>
</body>
</html>