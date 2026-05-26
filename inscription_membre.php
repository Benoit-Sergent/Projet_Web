<?php
session_start();
// Barrière de sécurité
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: connexion.php");
    exit();
}
?>

<h2>Inscrire un nouveau membre</h2>
<form action="traitement_inscription.php" method="POST">
    <input type="text" name="prenom" placeholder="Prénom" required>
    <input type="text" name="nom" placeholder="Nom" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="mot_de_passe" placeholder="Mot de passe provisoire" required>
    <select name="role">
        <option value="etudiant">Étudiant</option>
        <option value="professeur">Professeur</option>
        <option value="administrateur">Administrateur</option>
    </select>
    <button type="submit">Créer le compte</button>
</form>