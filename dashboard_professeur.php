<?php
session_start();
// Barrière de sécurité : accès uniquement aux professeurs
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

// 1. Récupération des étudiants pour le menu déroulant
$etudiants = $db->query("SELECT id, prenom, nom FROM utilisateurs WHERE role = 'etudiant' ORDER BY nom");

// 2. Récupération des cours pour le menu déroulant
$cours = $db->query("SELECT id, titre FROM cours ORDER BY titre");

// 3. Récupération de toutes les notes déjà saisies pour affichage
$toutes_les_notes = $db->query("
    SELECT utilisateurs.nom, utilisateurs.prenom, cours.titre, notes.valeur_note 
    FROM notes 
    JOIN utilisateurs ON notes.etudiant_id = utilisateurs.id 
    JOIN cours ON notes.cours_id = cours.id
    ORDER BY cours.titre, utilisateurs.nom
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Professeur - Haute École de Joaillerie</title>
    <style>
        /* Mêmes styles globaux pour la cohérence */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            color: #333;
        }
        .navbar {
            background-color: white;
            padding: 15px 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar img.logo { height: 50px; }
        .btn-deconnexion {
            background-color: #333;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-deconnexion:hover { background-color: #C5A059; }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 2fr; /* Le formulaire à gauche, le tableau à droite */
            gap: 30px;
        }
        .card {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        h1 { text-align: center; color: #C5A059; margin-top: 30px; }
        h2 { color: #333; border-bottom: 2px solid #C5A059; padding-bottom: 10px; margin-top: 0; }
        
        /* Styles du formulaire */
        label { font-weight: bold; display: block; margin-bottom: 5px; margin-top: 15px;}
        select, input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #C5A059;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
        }
        button:hover { background-color: #a68444; }

        /* Styles du tableau */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; font-weight: bold; color: #C5A059; }
        .note-highlight { font-weight: bold; color: #333; }
    </style>
</head>
<body>

    <nav class="navbar">
        <img src="images/logo.jpg" alt="Logo HEJ" class="logo">
        <a href="deconnexion.php" class="btn-deconnexion">Déconnexion</a>
    </nav>

    <h1>Espace Pédagogique</h1>

    <div class="container">
        <div class="card">
            <h2>Saisir une évaluation</h2>
            <form action="traitement_note.php" method="POST">
                <label>Étudiant</label>
                <select name="etudiant_id" required>
                    <option value="">-- Choisir un étudiant --</option>
                    <?php while ($e = $etudiants->fetch()): ?>
                        <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option>
                    <?php endwhile; ?>
                </select>

                <label>Matière</label>
                <select name="cours_id" required>
                    <option value="">-- Choisir une matière --</option>
                    <?php while ($c = $cours->fetch()): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['titre']) ?></option>
                    <?php endwhile; ?>
                </select>

                <label>Note (sur 20)</label>
                <input type="number" name="valeur_note" step="0.25" min="0" max="20" placeholder="Ex: 15.5" required>
                <label>Appréciation (facultatif)</label>
<textarea name="commentaire" rows="3" placeholder="Ex: Excellent travail ce trimestre..." style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-family: inherit;"></textarea>
                <button type="submit">Enregistrer la note</button>
            </form>
        </div>

        <div class="card">
            <h2>Carnet de notes global</h2>
            <?php if (empty($toutes_les_notes)): ?>
                <p>Aucune note n'a encore été enregistrée dans le système.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Matière</th>
                        <th>Étudiant</th>
                        <th>Note</th>
                    </tr>
                    <?php foreach ($toutes_les_notes as $n): ?>
                        <tr>
                            <td><?= htmlspecialchars($n['titre']) ?></td>
                            <td><?= htmlspecialchars($n['nom'] . ' ' . $n['prenom']) ?></td>
                            <td class="note-highlight"><?= htmlspecialchars($n['valeur_note']) ?> / 20</td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>