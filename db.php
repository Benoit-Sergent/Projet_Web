<?php
// On utilise DIRECTORY_SEPARATOR pour être compatible avec Windows
$db_file = __DIR__ . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'smartcampus.db';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON;');

    // On sépare bien les requêtes pour éviter les erreurs de syntaxe globales
    $db->exec("CREATE TABLE IF NOT EXISTS utilisateurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prenom TEXT NOT NULL,
        nom TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        role TEXT CHECK(role IN ('etudiant', 'professeur', 'administrateur')) NOT NULL,
        mot_de_passe_hash TEXT NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS cours (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titre TEXT NOT NULL,
        categorie TEXT NOT NULL,
        description TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        etudiant_id INTEGER,
        cours_id INTEGER,
        valeur_note REAL,
        FOREIGN KEY(etudiant_id) REFERENCES utilisateurs(id),
        FOREIGN KEY(cours_id) REFERENCES cours(id)
    )");
    
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>