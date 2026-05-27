<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$prof_id = $_SESSION['utilisateur_id'];
$message_succes = "";

// 1. ACTION : Sauvegarder l'appel en base de données
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sauvegarder_appel') {
    $cours_id = intval($_POST['cours_id']);
    $date_cours = $_POST['date_cours'];
    $statuts = $_POST['statuts']; // C'est un tableau contenant l'ID de l'étudiant et son statut

    $stmt_appel = $db->prepare("INSERT INTO presences (etudiant_id, cours_id, date_cours, statut) VALUES (?, ?, ?, ?)");
    
    foreach ($statuts as $etud_id => $statut) {
        $stmt_appel->execute([$etud_id, $cours_id, $date_cours, $statut]);
    }
    $message_succes = "✅ Le registre d'appel a été verrouillé et sauvegardé avec succès pour le " . date('d/m/Y', strtotime($date_cours)) . ".";
}

// Infos du prof
$stmt_prof = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
$stmt_prof->execute([$prof_id]);
$prof = $stmt_prof->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($prof['prenom'], 0, 1) . substr($prof['nom'], 0, 1));

// Récupération des matières enseignées par ce professeur
$stmt_cours = $db->prepare("
    SELECT c.id, c.titre, g.nom AS nom_groupe 
    FROM cours c 
    JOIN groupes g ON c.groupe_id = g.id 
    WHERE c.professeur_id = ?
");
$stmt_cours->execute([$prof_id]);
$mes_cours = $stmt_cours->fetchAll(PDO::FETCH_ASSOC);

// Si le prof a choisi une matière (Étape 1), on charge les élèves
$cours_selectionne = null;
$eleves = [];
if (isset($_GET['cours_id']) && !empty($_GET['cours_id'])) {
    $cours_selectionne = intval($_GET['cours_id']);
    
    // On récupère uniquement les étudiants du groupe lié à ce cours
    $stmt_eleves = $db->prepare("
        SELECT u.id, u.prenom, u.nom 
        FROM utilisateurs u 
        JOIN cours c ON u.groupe_id = c.groupe_id 
        WHERE c.id = ? AND u.role = 'etudiant' 
        ORDER BY u.nom
    ");
    $stmt_eleves->execute([$cours_selectionne]);
    $eleves = $stmt_eleves->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Faire l'appel - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .radio-group { display: flex; gap: 15px; }
        .radio-label { display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .text-present { color: var(--success); }
        .text-absent { color: var(--danger); }
        .text-retard { color: #d97706; }
    </style>
</head>
<body>
    <header class="top-bar">
        <img src="images/logo.jpg" alt="Logo SmartCampus" onerror="this.src='https://via.placeholder.com/120x45?text=SmartCampus'">
        <div class="user-widget">
            <div class="user-widget-info" style="text-align: right;">
                <strong><?= htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']) ?></strong>
                <span>Professeur</span>
            </div>
            <div class="avatar-small"><?= $initiales ?></div>
        </div>
    </header>

    <nav class="top-nav">
        <a href="dashboard_professeur.php">Évaluations</a>
        <a href="faire_appel.php" class="active">Faire l'appel</a>
        <a href="parametres.php">Paramètres</a>
        <a href="deconnexion.php" style="color:var(--danger);">Déconnexion</a>
    </nav>

    <div class="container">
        <div style="margin-bottom: 40px;">
            <h1 style="margin:0; color:var(--primary);">Registre de Présences</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Sélectionnez une matière pour afficher la liste des élèves.</p>
        </div>

        <?php if ($message_succes): ?><div class="alert alert-success"><?= $message_succes ?></div><?php endif; ?>

        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header"><h2>1. Sélection du cours</h2></div>
            <form method="GET" action="faire_appel.php" style="display:flex; gap:20px; align-items:flex-end;">
                <div style="flex-grow:1;">
                    <select name="cours_id" required style="margin-bottom:0;">
                        <option value="">-- Choisir une matière --</option>
                        <?php foreach($mes_cours as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($cours_selectionne == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['titre']) ?> (Classe : <?= htmlspecialchars($c['nom_groupe']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-action" style="background:var(--text-main);">Charger la classe</button>
            </form>
        </div>

        <?php if ($cours_selectionne !== null): ?>
            <div class="card">
                <div class="card-header"><h2>2. Feuille d'appel (<?= count($eleves) ?> inscrits)</h2></div>
                
                <?php if (empty($eleves)): ?>
                    <p style="color:var(--danger); font-weight:600; font-size:14px;">Aucun étudiant n'est assigné à la classe de ce cours.</p>
                <?php else: ?>
                    <form method="POST" action="faire_appel.php">
                        <input type="hidden" name="action" value="sauvegarder_appel">
                        <input type="hidden" name="cours_id" value="<?= htmlspecialchars($cours_selectionne) ?>">
                        
                        <label>Date de la séance</label>
                        <input type="date" name="date_cours" value="<?= date('Y-m-d') ?>" required style="max-width:200px; margin-bottom:20px;">
                        
                        <table>
                            <tr><th>Étudiant</th><th>Statut de présence</th></tr>
                            <?php foreach($eleves as $e): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($e['nom'].' '.$e['prenom']) ?></strong></td>
                                    <td>
                                        <div class="radio-group">
                                            <label class="radio-label text-present">
                                                <input type="radio" name="statuts[<?= $e['id'] ?>]" value="present" checked> Présent
                                            </label>
                                            <label class="radio-label text-absent">
                                                <input type="radio" name="statuts[<?= $e['id'] ?>]" value="absent"> Absent
                                            </label>
                                            <label class="radio-label text-retard">
                                                <input type="radio" name="statuts[<?= $e['id'] ?>]" value="retard"> En retard
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        
                        <div style="text-align:right; margin-top:20px;">
                            <button type="submit" class="btn-action">Verrouiller et Sauvegarder</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>