<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['role'] === 'professeur') {
    $etudiant_id = intval($_POST['etudiant_id']);
    $cours_id = intval($_POST['cours_id']);
    $valeur_note = floatval($_POST['valeur_note']);
    $commentaire = trim($_POST['commentaire']);

    // Sécurité basique : la note doit être entre 0 et 20
    if ($valeur_note >= 0 && $valeur_note <= 20) {
        $stmt = $db->prepare("INSERT INTO notes (etudiant_id, cours_id, valeur_note, commentaire) VALUES (?, ?, ?, ?)");
        $stmt->execute([$etudiant_id, $cours_id, $valeur_note, $commentaire]);
        
        // Redirection avec un message de succès
        header("Location: dashboard_professeur.php?succes=1");
        exit();
    }
}

// En cas d'erreur ou d'accès direct, on redirige normalement
header("Location: dashboard_professeur.php");
exit();
?>