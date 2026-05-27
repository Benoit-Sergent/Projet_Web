<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$prof_id = $_SESSION['utilisateur_id'];
$message_succes = "";

// 1. ACTION : SAUVEGARDER L'APPEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sauvegarder') {
    $c_id = intval($_POST['cours_id']); 
    $date = $_POST['date_cours'];
    
    $stmt = $db->prepare("INSERT INTO presences (etudiant_id, cours_id, date_cours, statut) VALUES (?, ?, ?, ?)");
    foreach($_POST['statuts'] as $etud_id => $st) {
        $stmt->execute([$etud_id, $c_id, $date, $st]);
    }
    $message_succes = "L'appel a été verrouillé avec succès pour cette séance.";
}

// 2. RÉCUPÉRATION DES COURS DU PROFESSEUR
$stmt_c = $db->prepare("SELECT c.*, g.nom as nom_groupe FROM cours c JOIN groupes g ON c.groupe_id = g.id WHERE c.professeur_id = ?");
$stmt_c->execute([$prof_id]); 
$mes_cours = $stmt_c->fetchAll();

// 3. RÉCUPÉRATION DES ÉLÈVES SI UN COURS EST SÉLECTIONNÉ
$c_sel = isset($_GET['cours_id']) ? intval($_GET['cours_id']) : null;
$eleves = [];
if ($c_sel) {
    $stmt_e = $db->prepare("SELECT id, nom, prenom FROM utilisateurs WHERE role = 'etudiant' AND groupe_id = (SELECT groupe_id FROM cours WHERE id = ?)");
    $stmt_e->execute([$c_sel]); 
    $eleves = $stmt_e->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Faire l'appel - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="color:var(--primary);">Registre d'Appel Numérique</h1>
            <p style="color:var(--text-muted); margin:0;">Sélectionnez un cours pour afficher le trombinoscope et valider les présences.</p>
        </div>

        <?php if($message_succes): ?>
            <div class="alert alert-success"><span>✅ <?= $message_succes ?></span></div>
        <?php endif; ?>
        
        <div class="card" style="margin-bottom:20px;">
            <form method="GET">
                <label>1. Sélectionner l'enseignement</label>
                <select name="cours_id" required onchange="this.form.submit()">
                    <option value="">-- Choisir un cours --</option>
                    <?php foreach($mes_cours as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c_sel == $c['id'] ? 'selected' : ''?>>
                            <?= htmlspecialchars($c['titre'] . ' (' . $c['nom_groupe'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if($c_sel && !empty($eleves)): ?>
            <div class="card">
                <form method="POST">
                    <input type="hidden" name="action" value="sauvegarder">
                    <input type="hidden" name="cours_id" value="<?= $c_sel ?>">
                    
                    <label>Date de la séance</label>
                    <input type="date" name="date_cours" value="<?= date('Y-m-d') ?>" required style="max-width:200px; margin-bottom:20px;">
                    
                    <table>
                        <tr>
                            <th>Profil</th>
                            <th>Étudiant</th>
                            <th>Statut de présence</th>
                        </tr>
                        <?php foreach($eleves as $e): 
                            $e_avatar = glob("uploads/avatars/avatar_" . $e['id'] . ".*");
                            $e_init = strtoupper(substr($e['prenom'],0,1) . substr($e['nom'],0,1));
                        ?>
                            <tr>
                                <td>
                                    <div class="avatar-small" style="width:30px; height:30px; font-size:11px; box-shadow:none;">
                                        <?php if(!empty($e_avatar)): ?>
                                            <img src="<?= $e_avatar[0] ?>" alt="Trombi">
                                        <?php else: ?>
                                            <?= $e_init ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong><?= htmlspecialchars($e['nom'].' '.$e['prenom']) ?></strong></td>
                                <td>
                                    <label style="display:inline; margin-right:15px; color:var(--success); cursor:pointer;">
                                        <input type="radio" name="statuts[<?= $e['id'] ?>]" value="present" checked> Présent
                                    </label>
                                    <label style="display:inline; margin-right:15px; color:var(--danger); cursor:pointer;">
                                        <input type="radio" name="statuts[<?= $e['id'] ?>]" value="absent"> Absent
                                    </label>
                                    <label style="display:inline; color:#d97706; cursor:pointer;">
                                        <input type="radio" name="statuts[<?= $e['id'] ?>]" value="retard"> Retard
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <div style="display:flex; justify-content:flex-end; margin-top:20px;">
                        <button type="submit" class="btn-action">Soumettre la feuille d'appel</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>