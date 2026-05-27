<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['role'] === 'administrateur') {
    $prenom = trim($_POST['prenom']);
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['mot_de_passe'];
    $role = $_POST['role'];
    
    // On capture le groupe_id seulement s'il est fourni ET que c'est un étudiant
    $groupe_id = (!empty($_POST['groupe_id']) && $role === 'etudiant') ? intval($_POST['groupe_id']) : null;

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $db->prepare("INSERT INTO utilisateurs (prenom, nom, email, mot_de_passe_hash, role, groupe_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$prenom, $nom, $email, $hash, $role, $groupe_id]);
    } catch (PDOException $e) {
        // En cas d'email en doublon, l'erreur est interceptée mais on redirige normalement. 
        // L'idéal serait d'utiliser les variables de session pour passer un message d'erreur.
    }
}

// Redirection vers le tableau de bord
header("Location: dashboard_administrateur.php");
exit();
?>