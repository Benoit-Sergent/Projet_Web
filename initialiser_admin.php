<?php
require_once 'db.php';

// Données de l'administrateur par défaut
$prenom = 'Admin';
$nom = 'Principal';
$email = 'admin@joaillerie.fr';
$role = 'administrateur';
$mot_de_passe = 'Joaillerie2026'; // À changer dès la première connexion !

// Hachage du mot de passe
$hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

try {
    $stmt = $db->prepare("INSERT INTO utilisateurs (prenom, nom, email, role, mot_de_passe_hash) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$prenom, $nom, $email, $role, $hash]);
    echo "✅ Administrateur créé avec succès ! Connectez-vous avec : $email / $mot_de_passe";
} catch (PDOException $e) {
    echo "⚠️ Erreur (peut-être déjà créé) : " . $e->getMessage();
}
?>