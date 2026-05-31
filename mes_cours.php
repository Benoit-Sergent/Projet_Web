<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

$stmt_user = $db->prepare("SELECT groupe_id FROM utilisateurs WHERE id = ?");
$stmt_user->execute([$_SESSION['utilisateur_id']]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// On ne charge QUE les cours attribués au groupe de cet étudiant
$liste_cours = [];
if ($user['groupe_id']) {
    $stmt_cours = $db->prepare("
        SELECT c.*, u.nom AS prof_nom, u.prenom AS prof_prenom 
        FROM cours c 
        LEFT JOIN utilisateurs u ON c.professeur_id = u.id 
        WHERE c.groupe_id = ? 
        ORDER BY c.categorie, c.titre
    ");
    $stmt_cours->execute([$user['groupe_id']]);
    $liste_cours = $stmt_cours->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Cours - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h1 style="margin:0; color:var(--primary);">Livret de Formation</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Consultez le catalogue des matières de votre cursus annuel.</p>
        </div>

        <?php if (!$user['groupe_id']): ?>
            <div class="alert alert-error">Vous n'êtes assigné à aucune classe. Aucun cours à afficher.</div>
        <?php elseif (empty($liste_cours)): ?>
            <div class="alert alert-neutral">Aucun cours n'a encore été programmé pour votre classe par la scolarité.</div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px;">
                <?php foreach ($liste_cours as $cours): ?>
                    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <span class="badge badge-neutral" style="margin-bottom: 10px;"><?= htmlspecialchars($cours['categorie']) ?></span>
                            <h3 style="margin: 0 0 10px 0; color: var(--text-main); font-size: 18px;"><?= htmlspecialchars($cours['titre']) ?></h3>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 15px;">
                                👨‍🏫 <?= $cours['prof_nom'] ? htmlspecialchars($cours['prof_prenom'] . ' ' . $cours['prof_nom']) : 'Enseignant non assigné' ?>
                            </p>
                            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 20px;">
                                <?= htmlspecialchars(strlen($cours['description']) > 100 ? substr($cours['description'], 0, 100) . '...' : $cours['description']) ?>
                                <?php if(empty($cours['description'])) echo "<em>Aucune description fournie.</em>"; ?>
                            </p>
                        </div>
                        <a href="detail_cours.php?id=<?= $cours['id'] ?>" class="btn-action" style="width: 100%; box-sizing: border-box; display: block; text-align: center;">Accéder au cours</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php include 'footer.php'; ?>
</body>
</html>