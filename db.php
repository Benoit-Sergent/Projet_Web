<?php
// On utilise DIRECTORY_SEPARATOR pour être compatible avec Windows
$db_file = __DIR__ . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'smartcampus.db';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Retourne les résultats sous forme de tableau associatif par défaut pour simplifier le code
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON;');

    // 1. Table Groupes (Classes)
    $db->exec("CREATE TABLE IF NOT EXISTS groupes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL UNIQUE
    )");

    // 2. Table Utilisateurs (Ajout du groupe_id et du statut)
    $db->exec("CREATE TABLE IF NOT EXISTS utilisateurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prenom TEXT NOT NULL,
        nom TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        role TEXT CHECK(role IN ('etudiant', 'professeur', 'administrateur')) NOT NULL,
        mot_de_passe_hash TEXT NOT NULL,
        statut_compte TEXT DEFAULT 'actif',
        groupe_id INTEGER,
        FOREIGN KEY(groupe_id) REFERENCES groupes(id) ON DELETE SET NULL
    )");

    // 3. Table Cours (Ajout du groupe_id et professeur_id)
    $db->exec("CREATE TABLE IF NOT EXISTS cours (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titre TEXT NOT NULL,
        categorie TEXT NOT NULL,
        description TEXT,
        groupe_id INTEGER,
        professeur_id INTEGER,
        FOREIGN KEY(groupe_id) REFERENCES groupes(id) ON DELETE CASCADE,
        FOREIGN KEY(professeur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
    )");

    // 4. Table Créneaux Horaires (Pour le planning)
    $db->exec("CREATE TABLE IF NOT EXISTS cours_creneaux (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cours_id INTEGER NOT NULL,
        jour_semaine INTEGER NOT NULL CHECK(jour_semaine BETWEEN 1 AND 7),
        heure_debut TIME NOT NULL,
        heure_fin TIME NOT NULL,
        salle TEXT NOT NULL,
        FOREIGN KEY(cours_id) REFERENCES cours(id) ON DELETE CASCADE
    )");

    // 5. Table Notes
    $db->exec("CREATE TABLE IF NOT EXISTS notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        etudiant_id INTEGER,
        cours_id INTEGER,
        valeur_note REAL,
        commentaire TEXT,
        FOREIGN KEY(etudiant_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY(cours_id) REFERENCES cours(id) ON DELETE CASCADE
    )");

    // 6. Table Présences
    $db->exec("CREATE TABLE IF NOT EXISTS presences (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        etudiant_id INTEGER,
        cours_id INTEGER,
        date_cours DATE NOT NULL,
        statut TEXT NOT NULL CHECK(statut IN ('present', 'absent', 'retard')),
        justifie INTEGER DEFAULT NULL,
        FOREIGN KEY(etudiant_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY(cours_id) REFERENCES cours(id) ON DELETE CASCADE
    )");

    // 7. Auto-création de l'administrateur par défaut si la table est vide
    $stmt = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'administrateur'");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO utilisateurs (prenom, nom, email, role, mot_de_passe_hash, statut_compte) 
                   VALUES ('Super', 'Admin', 'admin@smartcampus.fr', 'administrateur', '$hash', 'actif')");
    }

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>