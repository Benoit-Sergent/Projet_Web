<?php
require_once 'db.php';
$query = $db->query("SELECT * FROM users");
$etudiants = $query->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>Liste des étudiants inscrits</h1>";
echo "<table border='1'><tr><th>Prénom</th><th>Nom</th><th>Email</th></tr>";
foreach ($etudiants as $e) {
    echo "<tr><td>{$e['first_name']}</td><td>{$e['last_name']}</td><td>{$e['email']}</td></tr>";
}
echo "</table>";
?>