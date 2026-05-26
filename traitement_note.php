<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professeur') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $etudiant_id = $_POST['etudiant_id'];
    $cours_id = $_POST['cours_id'];
    $valeur_note = $_POST['valeur_note'];
    // On récupère le commentaire (qui peut être vide)
    $commentaire = trim($_POST['commentaire']);

    $stmt = $db->prepare("INSERT INTO notes (etudiant_id, cours_id, valeur_note, commentaire) VALUES (?, ?, ?, ?)");
    
    if ($stmt->execute([$etudiant_id, $cours_id, $valeur_note, $commentaire])) {
        // Redirection invisible vers le dashboard après succès
        header("Location: dashboard_professeur.php");
        exit();
    } else {
        echo "⚠️ Erreur lors de l'enregistrement.";
    }
} else {
    header("Location: dashboard_professeur.php");
    exit();
}
?>