<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

// 1. Définir les en-têtes HTTP pour forcer le téléchargement du fichier
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="historique_absences_' . date('Y-m-d') . '.csv"');

// 2. Ouvrir le flux de sortie
$output = fopen('php://output', 'w');

// 3. Ajouter le BOM UTF-8 pour qu'Excel lise correctement les accents (é, à, etc.)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// 4. Écrire la ligne d'en-tête des colonnes (séparées par un point-virgule pour le format européen)
fputcsv($output, ['Date', 'Élève', 'Classe', 'Matière', 'Statut', 'Justifié'], ';');

// 5. Récupérer les données de la base de données
$absences = $db->query("
    SELECT p.date_cours, u.nom, u.prenom, g.nom as nom_groupe, c.titre as cours, p.statut, p.justifie
    FROM presences p
    JOIN cours c ON p.cours_id = c.id
    JOIN utilisateurs u ON p.etudiant_id = u.id
    LEFT JOIN groupes g ON u.groupe_id = g.id
    WHERE p.statut IN ('absent', 'retard')
    ORDER BY p.date_cours DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 6. Boucler sur les données et les écrire ligne par ligne dans le fichier
foreach ($absences as $row) {
    // Traduire le statut booléen en texte clair
    $justification = '⏳ En attente';
    if ($row['justifie'] === 1) $justification = '✅ Oui';
    elseif ($row['justifie'] === 0) $justification = '❌ Non';

    fputcsv($output, [
        date('d/m/Y', strtotime($row['date_cours'])),
        $row['nom'] . ' ' . $row['prenom'],
        $row['nom_groupe'] ?? '-',
        $row['cours'],
        ucfirst($row['statut']),
        $justification
    ], ';');
}

// 7. Fermer le fichier
fclose($output);
exit();
?>