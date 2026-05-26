<?php
session_start();
// Barrière de sécurité : seul l'administrateur peut supprimer un compte
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

// Vérification qu'un ID a bien été envoyé dans l'URL
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_a_supprimer = $_GET['id'];

    // Sécurité supplémentaire : on vérifie que l'admin n'essaie pas de se supprimer lui-même
    if ($id_a_supprimer != $_SESSION['utilisateur_id']) {
        try {
            // Optionnel mais recommandé : supprimer d'abord les notes liées si c'est un étudiant
            // pour garder une base de données propre (respect des clés étrangères)
            $stmt_notes = $db->prepare("DELETE FROM notes WHERE etudiant_id = ?");
            $stmt_notes->execute([$id_a_supprimer]);

            // Suppression de l'utilisateur
            $stmt = $db->prepare("DELETE FROM utilisateurs WHERE id = ?");
            $stmt->execute([$id_a_supprimer]);
        } catch (PDOException $e) {
            // En cas d'erreur (ex: problème de base de données)
            die("Erreur lors de la suppression : " . $e->getMessage());
        }
    }
}

// Redirection immédiate vers le dashboard après l'action
header("Location: dashboard_administrateur.php");
exit();
?>