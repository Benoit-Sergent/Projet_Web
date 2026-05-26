<?php
session_start();
// Barrière de sécurité
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

// Récupération de tous les utilisateurs
$utilisateurs = $db->query("SELECT id, prenom, nom, email, role FROM utilisateurs ORDER BY role, nom")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Administrateur - Haute École de Joaillerie</title>
    <style>
        /* Mêmes styles globaux que l'étudiant pour la cohérence */
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
            grid-template-columns: 2fr 1fr; /* La liste prend plus de place que le formulaire */
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
        
        /* Styles du tableau */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; font-weight: bold; color: #C5A059; }
        
        /* Styles du formulaire */
        input, select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
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
        }
        button:hover { background-color: #a68444; }
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .badge-admin { background-color: #d9534f; }
        .badge-prof { background-color: #5bc0de; }
        .badge-etu { background-color: #5cb85c; }
    </style>
</head>
<body>

    <nav class="navbar">
        <img src="images/logo.jpg" alt="Logo HEJ" class="logo">
        <a href="deconnexion.php" class="btn-deconnexion">Déconnexion</a>
    </nav>

    <h1>Administration du Campus</h1>

    <div class="container">
        <div class="card">
            <h2>Membres de l'école</h2>
            <table>
                <tr>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Action</th> </tr>
                <?php foreach ($utilisateurs as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nom']) ?></td>
                        <td><?= htmlspecialchars($u['prenom']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php 
                            if ($u['role'] === 'administrateur') echo '<span class="badge badge-admin">Admin</span>';
                            elseif ($u['role'] === 'professeur') echo '<span class="badge badge-prof">Professeur</span>';
                            else echo '<span class="badge badge-etu">Étudiant</span>';
                            ?>
                        </td>
                        <td>
                            <?php if ($u['id'] !== $_SESSION['utilisateur_id']): // On empêche l'admin de se supprimer lui-même ?>
                                <a href="supprimer_utilisateur.php?id=<?= $u['id'] ?>" 
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer <?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?> ?');" 
                                   style="color: #d9534f; text-decoration: none; font-weight: bold;">
                                   🗑️ Supprimer
                                </a>
                            <?php else: ?>
                                <span style="color: #ccc; font-size: 12px;">(Vous)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <h2>Ajouter un utilisateur</h2>
            <form action="traitement_inscription.php" method="POST">
                <input type="text" name="prenom" placeholder="Prénom" required>
                <input type="text" name="nom" placeholder="Nom" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="mot_de_passe" placeholder="Mot de passe" required>
                <select name="role" required>
                    <option value="etudiant">Étudiant</option>
                    <option value="professeur">Professeur</option>
                    <option value="administrateur">Administrateur</option>
                </select>
                <button type="submit">Créer le compte</button>
            </form>
        </div>
    </div>

</body>
</html>