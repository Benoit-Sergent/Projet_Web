<?php
session_start();
require_once 'db.php';

$erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];

    if (!empty($email) && !empty($mot_de_passe)) {
        $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($mot_de_passe, $user['mot_de_passe_hash'])) {
            if ($user['statut_compte'] === 'actif') {
                $_SESSION['utilisateur_id'] = $user['id'];
                $_SESSION['prenom'] = $user['prenom'];
                $_SESSION['nom'] = $user['nom'];
                $_SESSION['role'] = $user['role'];

                // Redirection selon le rôle
                if ($user['role'] === 'administrateur') {
                    header("Location: dashboard_administrateur.php");
                } elseif ($user['role'] === 'professeur') {
                    header("Location: dashboard_professeur.php");
                } else {
                    header("Location: dashboard_etudiant.php");
                }
                exit();
            } else {
                $erreur = "Votre compte a été suspendu ou est inactif.";
            }
        } else {
            $erreur = "Identifiants incorrects.";
        }
    } else {
        $erreur = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Surcharge spécifique pour l'écran de connexion (Layout deux colonnes) */
        .login-wrapper {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            min-height: 100vh;
            background: #ffffff;
        }

        /* Colonne visuelle gauche (Cachée sur mobile si besoin, mais conservée ici pour desktop) */
        .login-visual {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.9), rgba(67, 56, 202, 1)), 
                        url('assets/images/Bckgrnd.webp');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 60px;
            color: #ffffff;
        }

        .visual-brand h2 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.03em;
        }

        .visual-quote h3 {
            font-size: 32px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 15px;
            letter-spacing: -0.02em;
        }

        .visual-quote p {
            font-size: 16px;
            color: var(--primary-light);
            margin: 0;
        }

        /* Colonne formulaire droite */
        .login-form-container {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background-color: var(--bg-body);
        }

        .login-box {
            width: 100%;
            max-width: 420px;
        }

        .login-header {
            margin-bottom: 35px;
        }

        .login-header img {
            height: 45px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .login-header h1 {
            margin: 0;
            font-size: 32px;
            color: var(--text-main);
        }

        .login-header p {
            margin: 5px 0 0 0;
            color: var(--text-muted);
            font-size: 15px;
        }

        .form-group {
            position: relative;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            margin-top: 10px;
            box-shadow: var(--shadow-sm);
        }

        .login-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        
        <div class="login-visual">
            <div class="visual-brand">
                <h2>SmartCampus.</h2>
            </div>
            <div class="visual-quote">
                <h3>L'université connectée, <br>partout avec vous.</h3>
                <p>Gérez vos cours, planifiez vos feuilles d'appel et suivez vos évaluations en temps réel sur une interface repensée.</p>
            </div>
            <div style="font-size: 12px; color: rgba(255,255,255,0.6);">
                &copy; 2026 SmartCampus - Projet Web d'Ingénierie Scolaire.
            </div>
        </div>

        <div class="login-form-container">
            <div class="login-box">
                
                <div class="login-header">
                    <img src="images/logo.png" alt="Logo SmartCampus" onerror="this.src='images/logo.jpg'">
                    <h1>Bienvenue</h1>
                    <p>Connectez-vous pour accéder à votre espace numérique.</p>
                </div>

                <?php if (!empty($erreur)): ?>
                    <div class="alert alert-error">
                        <span>⚠️ <?= htmlspecialchars($erreur) ?></span>
                    </div>
                <?php endif; ?>

                <div class="card" style="padding: 30px;">
                    <form action="connexion.php" method="POST">
                        <div class="form-group">
                            <label for="email">Adresse email institutionnelle</label>
                            <input type="email" name="email" id="email" required placeholder="nom.prenom@smartcampus.fr" autocomplete="username">
                        </div>

                        <div class="form-group">
                            <label for="mot_de_passe">Mot de passe</label>
                            <input type="password" name="mot_de_passe" id="mot_de_passe" required placeholder="••••••••" autocomplete="current-password">
                        </div>

                        <button type="submit" class="btn-action btn-login">
                            Se connecter
                        </button>
                    </form>
                </div>

                <div class="login-footer">
                    Un problème d'accès ? <a href="messagerie.php" style="color: var(--primary); font-weight: 500;">Contactez l'administration</a>.
                </div>

            </div>
        </div>

    </div>

</body>
</html>