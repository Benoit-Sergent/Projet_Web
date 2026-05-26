<?php
session_start();
require_once 'db.php';
$message_erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];

    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $utilisateur = $stmt->fetch();

    if ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe_hash'])) {
        $_SESSION['utilisateur_id'] = $utilisateur['id'];
        $_SESSION['role'] = $utilisateur['role'];

        header("Location: dashboard_" . $utilisateur['role'] . ".php");
        exit();
    } else {
        $message_erreur = "Identifiants incorrects.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <img src="images/logo.jpg" alt="Logo HEJ" onerror="this.src='https://via.placeholder.com/140x50?text=SmartCampus'">
        <?php if ($message_erreur): ?>
            <div class="alert alert-error"><?= htmlspecialchars($message_erreur) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Adresse email" required>
            <input type="password" name="mot_de_passe" placeholder="Mot de passe" required>
            <button type="submit" class="btn-action" style="width:100%; margin-top:15px;">Se connecter</button>
        </form>
    </div>
</body>
</html>