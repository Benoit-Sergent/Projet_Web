<?php
session_start();
// Barrière de sécurité : accès autorisé uniquement aux administrateurs
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage basique des entrées
    $prenom = trim($_POST['prenom']);
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    // Hachage du mot de passe
    $hash = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);

    try {
        // Préparation et exécution de la requête d'insertion
        $stmt = $db->prepare("INSERT INTO utilisateurs (prenom, nom, email, role, mot_de_passe_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$prenom, $nom, $email, $role, $hash]);
        
        // Message de succès stylisé
        echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
        echo "<h2 style='color: #5cb85c;'>✅ Utilisateur ajouté avec succès !</h2>";
        echo "<p><a href='dashboard_administrateur.php' style='padding: 10px 20px; background-color: #C5A059; color: white; text-decoration: none; border-radius: 5px;'>Retour au tableau de bord</a></p>";
        echo "</div>";

    } catch (PDOException $e) {
        // Gestion des erreurs
        echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
        
        // Code 23000 : Violation de la contrainte UNIQUE (l'email existe déjà)
        if ($e->getCode() == 23000) {
            echo "<h2 style='color: #d9534f;'>⚠️ Erreur : L'adresse email <strong>" . htmlspecialchars($email) . "</strong> est déjà utilisée par un autre compte.</h2>";
        } else {
            // Autre erreur technique
            echo "<h2 style='color: #d9534f;'>⚠️ Erreur technique : " . htmlspecialchars($e->getMessage()) . "</h2>";
        }
        
        echo "<p><a href='dashboard_administrateur.php' style='padding: 10px 20px; background-color: #333; color: white; text-decoration: none; border-radius: 5px;'>Retour au tableau de bord</a></p>";
        echo "</div>";
    }
} else {
    // Redirection automatique si on essaie d'accéder à ce fichier directement via l'URL sans passer par le formulaire POST
    header("Location: dashboard_administrateur.php");
    exit();
}
?>