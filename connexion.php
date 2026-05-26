<?php
session_start();
require_once 'db.php';

$message_erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $mot_de_passe = $_POST['mot_de_passe'];

    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $utilisateur = $stmt->fetch();

    if ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe_hash'])) {
        // Connexion réussie
        $_SESSION['utilisateur_id'] = $utilisateur['id'];
        $_SESSION['role'] = $utilisateur['role'];

        // Redirection selon le rôle
        switch ($utilisateur['role']) {
            case 'administrateur':
                header("Location: dashboard_administrateur.php");
                break;
            case 'professeur':
                header("Location: dashboard_professeur.php");
                break;
            case 'etudiant':
                header("Location: dashboard_etudiant.php");
                break;
            default:
                header("Location: index.php");
        }
        exit();
    } else {
        $message_erreur = "Email ou mot de passe incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Haute École de Joaillerie</title>
    <style>
        /* Styles globaux pour un fond propre et centré */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        /* La carte de connexion */
        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        /* Gestion du logo */
        .logo {
            max-width: 150px;
            margin-bottom: 20px;
            /* Si l'image ne charge pas, on garde un espace propre */
            display: block; 
            margin-left: auto; 
            margin-right: auto;
        }

        /* Typographie */
        h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 30px;
            font-weight: 600;
        }

        /* Les champs de saisie */
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box; /* Pour que le padding ne dépasse pas la largeur */
            font-size: 14px;
            transition: border-color 0.3s;
        }

        /* Effet au clic sur un champ */
        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #C5A059; /* Une couleur dorée/laiton élégante */
            outline: none;
        }

        /* Le bouton de validation */
        button {
            width: 100%;
            padding: 12px;
            background-color: #C5A059; /* Doré/Joaillerie */
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #a68444; /* Un peu plus sombre au survol */
        }

        /* Message d'erreur */
        .erreur {
            background-color: #ffe6e6;
            color: #d9534f;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="login-container">
        <img src="images/logo.jpg" alt="Logo Haute École de Joaillerie" class="logo">
        
        <?php if ($message_erreur): ?>
            <div class="erreur"><?= htmlspecialchars($message_erreur) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="email" name="email" placeholder="Adresse email" required>
            <input type="password" name="mot_de_passe" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
    </div>

</body>
</html>