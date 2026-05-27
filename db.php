<?php
// db.php
$db_dir = __DIR__ . DIRECTORY_SEPARATOR . 'database';
$db_file = $db_dir . DIRECTORY_SEPARATOR . 'smartcampus.db';

// Création du dossier database s'il n'existe pas
if (!is_dir($db_dir)) {
    mkdir($db_dir, 0777, true);
}

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON;');

    // 1. Table Groupes (Classes)
    $db->exec("CREATE TABLE IF NOT EXISTS groupes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL UNIQUE
    )");

    // 2. Table Utilisateurs
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

    // 3. Table Cours
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

    // 4. Table Créneaux Horaires (Planning)
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
        valeur_note REAL CHECK(valeur_note >= 0 AND valeur_note <= 20),
        commentaire TEXT,
        FOREIGN KEY(etudiant_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY(cours_id) REFERENCES cours(id) ON DELETE CASCADE
    )");

    // 6. Table Présences (Absences / Retards)
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

    // 7. Table Messagerie
    $db->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        expediteur_id INTEGER NOT NULL,
        destinataire_id INTEGER NOT NULL,
        sujet TEXT NOT NULL,
        contenu TEXT NOT NULL,
        date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
        lu INTEGER DEFAULT 0,
        FOREIGN KEY(expediteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY(destinataire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
    )");

    // Auto-création de l'admin par défaut si la table est vide
    $stmt = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'administrateur'");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO utilisateurs (prenom, nom, email, role, mot_de_passe_hash, statut_compte) 
                   VALUES ('Super', 'Admin', 'admin@smartcampus.fr', 'administrateur', '$hash', 'actif')");
    }

} catch (PDOException $e) {
    die("Erreur critique de connexion : " . $e->getMessage());
}
?>
