<?php
session_start();
if (!isset($_SESSION['utilisateur_id'])) { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$mon_id = $_SESSION['utilisateur_id'];

// 1. GESTION DES ACTIONS (ENVOI OU MARQUER COMME LU)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'envoyer') {
        $dest = intval($_POST['destinataire_id']);
        $msg = trim($_POST['message']);
        if (!empty($msg) && $dest > 0) {
            $stmt = $db->prepare("INSERT INTO messages (expediteur_id, destinataire_id, contenu) VALUES (?, ?, ?)");
            $stmt->execute([$mon_id, $dest, $msg]);
        }
    } elseif ($_POST['action'] === 'marquer_lu') {
        $stmt = $db->prepare("UPDATE messages SET lu = 1 WHERE destinataire_id = ?");
        $stmt->execute([$mon_id]);
    }
}

// 2. RÉCUPÉRATION DES MESSAGES
$messages = $db->prepare("
    SELECT m.*, u.prenom, u.nom 
    FROM messages m 
    JOIN utilisateurs u ON m.expediteur_id = u.id 
    WHERE m.destinataire_id = ? OR m.expediteur_id = ? 
    ORDER BY m.date_envoi DESC
");
$messages->execute([$mon_id, $mon_id]);
$msgs = $messages->fetchAll(PDO::FETCH_ASSOC);

// Liste des contacts (pour le formulaire d'envoi)
$contacts = $db->query("SELECT id, nom, prenom, role FROM utilisateurs WHERE id != $mon_id ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Messagerie - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .msg-list { list-style: none; padding: 0; }
        .msg-item { padding: 15px; border-bottom: 1px solid var(--border); display: flex; gap: 15px; }
        .msg-item.unread { background: var(--primary-light); }
        .msg-meta { font-size: 12px; color: var(--text-muted); }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1>Messagerie Interne</h1>
            <p style="color:var(--text-muted);">Échanges directs avec les membres de l'établissement.</p>
        </div>

        <div class="dashboard-grid inverse">
            <div class="card" style="align-self: start;">
                <div class="card-header"><h2>Nouveau Message</h2></div>
                <form method="POST">
                    <input type="hidden" name="action" value="envoyer">
                    <label>Destinataire</label>
                    <select name="destinataire_id" required>
                        <option value="">-- Choisir un contact --</option>
                        <?php foreach($contacts as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom'].' '.$c['prenom'].' ('.$c['role'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Message</label>
                    <textarea name="message" rows="4" required></textarea>
                    <button type="submit" class="btn-action" style="width:100%;">Envoyer</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h2>Historique des échanges</h2>
                    <form method="POST"><input type="hidden" name="action" value="marquer_lu"><button class="btn-action" style="padding:5px 10px; font-size:11px;">Tout marquer comme lu</button></form>
                </div>
                <ul class="msg-list">
                    <?php if(empty($msgs)): ?><li class="msg-item">Aucun message pour le moment.</li><?php endif; ?>
                    <?php foreach($msgs as $m): ?>
                        <li class="msg-item <?= $m['lu']==0 && $m['destinataire_id']==$mon_id ? 'unread' : '' ?>">
                            <div style="width:40px;height:40px;background:var(--bg-body);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;"><?= strtoupper(substr($m['prenom'],0,1)) ?></div>
                            <div>
                                <strong><?= htmlspecialchars($m['prenom'].' '.$m['nom']) ?></strong>
                                <p style="margin:5px 0; font-size:14px;"><?= htmlspecialchars($m['contenu']) ?></p>
                                <span class="msg-meta"><?= date('d/m/Y H:i', strtotime($m['date_envoi'])) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>