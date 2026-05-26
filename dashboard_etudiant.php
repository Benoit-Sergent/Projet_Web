<?php
session_start();
// Barrière de sécurité
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

// 1. Récupération du programme total
$requete_prog = $db->query("SELECT * FROM cours ORDER BY categorie");
$liste_cours = $requete_prog->fetchAll(PDO::FETCH_ASSOC);
$total_cours = count($liste_cours); // On compte le nombre total de matières

// 2. Récupération des notes de l'étudiant
$stmt = $db->prepare("
    SELECT cours.titre, notes.valeur_note, notes.commentaire
    FROM notes 
    JOIN cours ON notes.cours_id = cours.id 
    WHERE notes.etudiant_id = ?
");
$stmt->execute([$_SESSION['utilisateur_id']]);
$mes_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Calculs des statistiques
$moyenne_generale = 0;
$nombre_notes = count($mes_notes);
$progression_pourcentage = 0;

if ($total_cours > 0) {
    $progression_pourcentage = round(($nombre_notes / $total_cours) * 100);
}

if ($nombre_notes > 0) {
    $somme = 0;
    foreach ($mes_notes as $note) {
        $somme += $note['valeur_note'];
    }
    $moyenne_generale = round($somme / $nombre_notes, 2);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace Étudiant - Haute École de Joaillerie</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; margin: 0; color: #333; }
        
        /* Navbar */
        .navbar { background-color: white; padding: 15px 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar img.logo { height: 50px; }
        .btn-deconnexion { background-color: #333; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background 0.3s; }
        .btn-deconnexion:hover { background-color: #C5A059; }

        /* Conteneur principal */
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .card { background-color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); }
        h1 { text-align: center; color: #C5A059; margin-top: 30px; }
        h2 { color: #333; border-bottom: 2px solid #C5A059; padding-bottom: 10px; margin-top: 0; }
        h3 { color: #666; font-size: 16px; margin-top: 20px; }

        /* Statistiques & Progression */
        .stats-container { display: flex; gap: 15px; margin-bottom: 20px; }
        .stat-box { flex: 1; background-color: #fcf8f2; border: 1px solid #C5A059; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-box span { display: block; font-size: 24px; font-weight: bold; color: #C5A059; margin-top: 5px; }

        .progress-bar-bg { width: 100%; background-color: #eee; border-radius: 10px; height: 12px; margin-top: 10px; overflow: hidden; }
        .progress-bar-fill { background-color: #C5A059; height: 100%; transition: width 0.5s ease-in-out; }

        /* Liste des cours */
        ul { list-style-type: none; padding: 0; }
        ul li { padding: 8px 0; border-bottom: 1px solid #eee; }
        ul li::before { content: "•"; color: #C5A059; font-weight: bold; display: inline-block; width: 1em; }

        /* Tableau des notes */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; font-weight: bold; color: #C5A059; }
        
        /* Badges de couleur pour les notes */
        .note-valide { color: #5cb85c; font-weight: bold; }
        .note-echec { color: #d9534f; font-weight: bold; }
    </style>
</head>
<body>

    <nav class="navbar">
        <img src="images/logo.jpg" alt="Logo HEJ" class="logo">
        <a href="deconnexion.php" class="btn-deconnexion">Déconnexion</a>
    </nav>

    <h1>Mon Espace Étudiant</h1>

    <div class="container">
        <div class="card">
            <h2>Programme de 1ère année</h2>
            
            <p><strong>Avancement des évaluations :</strong> <?= $nombre_notes ?> / <?= $total_cours ?> matières notées</p>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: <?= $progression_pourcentage ?>%;"></div>
            </div>
            <br>

            <?php 
            $categorie_actuelle = '';
            foreach ($liste_cours as $cours): 
                if ($cours['categorie'] !== $categorie_actuelle): 
                    $categorie_actuelle = $cours['categorie'];
                    echo "<h3>" . htmlspecialchars($categorie_actuelle) . "</h3><ul>";
                endif; 
                echo "<li>" . htmlspecialchars($cours['titre']) . "</li>";
                
                $index = array_search($cours, $liste_cours);
                if (!isset($liste_cours[$index + 1]) || $liste_cours[$index + 1]['categorie'] !== $categorie_actuelle) {
                    echo "</ul>";
                }
            endforeach; 
            ?>
        </div>

        <div class="card">
            <h2>Mes résultats</h2>
            
            <?php if ($nombre_notes > 0): ?>
                <div class="stats-container">
                    <div class="stat-box">
                        Moyenne Générale
                        <span><?= number_format($moyenne_generale, 2, ',', ' ') ?> / 20</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($mes_notes)): ?>
                <p>Aucune note enregistrée pour le moment.</p>
            <?php else: ?>
                <table>
    <tr>
        <th>Cours</th>
        <th>Note</th>
        <th>Appréciation</th> </tr>
    <?php foreach ($mes_notes as $note): 
        $classe_note = ($note['valeur_note'] >= 10) ? 'note-valide' : 'note-echec';
    ?>
        <tr>
            <td><?= htmlspecialchars($note['titre']) ?></td>
            <td class="<?= $classe_note ?>"><?= htmlspecialchars($note['valeur_note']) ?> / 20</td>
            <td style="font-style: italic; color: #666;">
                <?= !empty($note['commentaire']) ? htmlspecialchars($note['commentaire']) : '-' ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>