<?php
session_start();
// Barrière de sécurité
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: connexion.php");
    exit();
}

require_once 'db.php';

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $etudiant_id = $_POST['etudiant_id'];
    $cours_id = $_POST['cours_id'];

    $stmt = $db->prepare("INSERT INTO inscriptions (etudiant_id, cours_id) VALUES (?, ?)");
    $stmt->execute([$etudiant_id, $cours_id]);
    echo "<p>✅ Inscription effectuée avec succès !</p>";
}

// Récupération des données pour les menus déroulants
$etudiants = $db->query("SELECT id, prenom, nom FROM utilisateurs WHERE role = 'etudiant'")->fetchAll();
$cours_disponibles = $db->query("SELECT id, titre FROM cours")->fetchAll();
?>

<h1>Assigner un cours à un étudiant</h1>
<form method="POST">
    <label>Étudiant :</label>
    <select name="etudiant_id" required>
        <?php foreach ($etudiants as $e): ?>
            <option value="<?= $e['id'] ?>"><?= $e['prenom'] . ' ' . $e['nom'] ?></option>
        <?php endforeach; ?>
    </select>

    <label>Cours :</label>
    <select name="cours_id" required>
        <?php foreach ($cours_disponibles as $c): ?>
            <option value="<?= $c['id'] ?>"><?= $c['titre'] ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Inscrire l'étudiant</button>
</form>