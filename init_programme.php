<?php
require_once 'db.php';

$programme = [
    ['Réalisations bijoutières', 'Réalisations techniques'],
    ['Volume technique', 'Réalisations techniques'],
    ['Infographie (CAO / DAO)', 'Réalisations techniques'],
    ['Technologie professionnelle', 'Réalisations techniques'],
    ['Dessin technique', 'Réalisations graphiques'],
    ['Dessin d’art', 'Réalisations graphiques'],
    ['Histoire de l’art', 'Réalisations graphiques'],
    ['Gemmologie', 'Enseignements complémentaires'],
    ['Économie et gestion', 'Enseignements complémentaires'],
    ['Réglementation', 'Enseignements complémentaires']
];

// Préparation de la requête avec les 3 colonnes de ton db.php
$stmt = $db->prepare("INSERT INTO cours (titre, categorie, description) VALUES (?, ?, ?)");

foreach ($programme as $item) {
    // On met une description vide par défaut si nécessaire
    $stmt->execute([$item[0], $item[1], 'Programme de 1ère année']);
}
echo "✅ Programme 1ère année importé avec succès !";
?>