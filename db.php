<?php
$db_file = __DIR__ . '/database/smartcampus.db';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON;');

    // AUTO-CRÉATION : Si la table users n'existe pas, on l'exécute
    $schema = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        role TEXT CHECK(role IN ('student', 'teacher', 'admin')) NOT NULL,
        password_hash TEXT NOT NULL
    );";
    $db->exec($schema);
    
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>