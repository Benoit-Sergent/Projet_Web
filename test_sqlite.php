<?php
require_once 'db.php';

// 1. Initialiser les tables (au cas où)
$sql_schema = file_get_contents('database/schema.sql');
$db->exec($sql_schema);

// 2. Insérer un test
try {
    $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Jean', 'Dupont', 'jean.dupont@test.fr', 'student']);
    echo "✅ Étudiant inséré avec succès !<br>";
} catch (Exception $e) {
    echo "⚠️ L'étudiant existe déjà ou erreur : " . $e->getMessage() . "<br>";
}

// 3. Lire et afficher
$res = $db->query("SELECT * FROM users");
echo "<h3>Liste des étudiants :</h3><ul>";
while ($row = $res->fetch()) {
    echo "<li>" . $row['first_name'] . " " . $row['last_name'] . " (" . $row['email'] . ")</li>";
}
echo "</ul>";
?>