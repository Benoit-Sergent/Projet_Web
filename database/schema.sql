-- 1. Table des utilisateurs (Gère les accès Admin, Prof, Élève)
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    prenom TEXT NOT NULL,
    nom TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    role TEXT CHECK(role IN ('etudiant', 'professeur', 'administrateur')) NOT NULL,
    mot_de_passe_hash TEXT NOT NULL
);

-- Table des cours avec catégorie pour regrouper vos matières
CREATE TABLE IF NOT EXISTS cours (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titre TEXT NOT NULL,
    categorie TEXT NOT NULL, -- Ex: 'Réalisations techniques', 'Réalisations graphiques', etc.
    description TEXT
);

-- 3. Table des inscriptions (Lien entre élève et cours)
-- Permet de savoir quel élève est inscrit à quel cours
CREATE TABLE IF NOT EXISTS inscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    etudiant_id INTEGER,
    cours_id INTEGER,
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(etudiant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY(cours_id) REFERENCES cours(id)
);

-- 4. Table des notes (Lien entre l'inscription et la note finale)
CREATE TABLE IF NOT EXISTS notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inscription_id INTEGER,
    valeur_note REAL,
    FOREIGN KEY(inscription_id) REFERENCES inscriptions(id)
);