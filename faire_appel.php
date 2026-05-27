<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') { header("Location: connexion.php"); exit(); }
require_once 'db.php';

$prof_id = $_SESSION['utilisateur_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sauvegarder') {
    $c_id = intval($_POST['cours_id']); $date = $_POST['date_cours'];
    $stmt = $db->prepare("INSERT INTO presences (etudiant_id, cours_id, date_cours, statut) VALUES (?, ?, ?, ?)");
    foreach($_POST['statuts'] as $etud_id => $st) {
        $stmt->execute([$etud_id, $c_id, $date, $st]);
    }
    $message = "✅ Appel verrouillé avec succès.";
}

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
    <div class="container" style="margin-top:40px;">
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
                        <tr><th>Étudiant</th><th>Présence</th></tr>
                        <?php foreach($eleves as $e): ?>
                            <tr>
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
        <p style="margin-top:20px;clear:both;"><a href="dashboard_professeur.php" style="text-decoration:none;">Retour</a></p>
    </div>
</body>
</html>