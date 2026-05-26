<?php 
require_once 'db.php'; 
include 'includes/header.php'; 
?>

<h1>Bienvenue sur SmartCampus</h1>
<p>Gestion des inscriptions</p>

<?php
$etudiants = $db->query("SELECT * FROM users")->fetchAll();
echo "<ul>";
foreach ($etudiants as $e) {
    echo "<li>{$e['first_name']} {$e['last_name']} - {$e['email']}</li>";
}
echo "</ul>";

include 'includes/footer.php'; 
?>