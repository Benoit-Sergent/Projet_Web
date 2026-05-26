<?php
// 1. On récupère la session en cours
session_start();

// 2. On vide complètement le tableau des variables de session
$_SESSION = array();

// 3. On détruit la session côté serveur
session_destroy();

// 4. On redirige l'utilisateur vers la page de connexion
header("Location: connexion.php");
exit();
?>