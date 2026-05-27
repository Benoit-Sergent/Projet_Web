<?php
require_once 'db.php';

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            expediteur_id INTEGER NOT NULL,
            destinataire_id INTEGER NOT NULL,
            sujet TEXT NOT NULL,
            contenu TEXT NOT NULL,
            date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
            lu INTEGER DEFAULT 0,
            FOREIGN KEY(expediteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
            FOREIGN KEY(destinataire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
        );
    ");
    echo "<h1>✅ Table 'messages' ajoutée avec succès !</h1>";
    echo "<p>Tu peux maintenant supprimer ce fichier maj_bdd.php et aller sur la messagerie.</p>";
} catch (PDOException $e) {
    echo "<h1>⚠️ Erreur : " . $e->getMessage() . "</h1>";
}
?>