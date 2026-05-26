<?php
require_once 'db.php'; // Votre script de connexion PDO

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $prenom = $_POST['first_name'];
    $nom = $_POST['last_name'];
    $email = $_POST['email'];
    $role = 'student'; 
    $pass_default = password_hash('etudiant2026', PASSWORD_DEFAULT); // Mot de passe par défaut

    try {
        // Préparation de la requête pour éviter les injections SQL
        $sql = "INSERT INTO users (first_name, last_name, email, role, password_hash) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$prenom, $nom, $email, $role, $pass_default]);
        
        echo "✅ Étudiant inscrit avec succès dans la base de données.";
    } catch (PDOException $e) {
        echo "❌ Erreur : " . $e->getMessage();
    }
}
?>