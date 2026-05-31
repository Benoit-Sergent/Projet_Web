<?php
session_start();
// Barrière de sécurité : accès étudiant uniquement
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'etudiant') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

// 1. Informations de l'étudiant
$stmt_info = $db->prepare("SELECT id, prenom, nom, email FROM utilisateurs WHERE id = ?");
$stmt_info->execute([$_SESSION['utilisateur_id']]);
$user_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
$initiales = strtoupper(substr($user_info['prenom'], 0, 1) . substr($user_info['nom'], 0, 1));
$matricule = "E2026" . str_pad($user_info['id'], 4, "0", STR_PAD_LEFT); // Génération d'un faux matricule étudiant

// 2. Récupération des notes triées par catégorie
$stmt_notes = $db->prepare("
    SELECT c.titre, c.categorie, n.valeur_note, n.commentaire 
    FROM notes n 
    JOIN cours c ON n.cours_id = c.id 
    WHERE n.etudiant_id = ?
    ORDER BY c.categorie, c.titre
");
$stmt_notes->execute([$_SESSION['utilisateur_id']]);
$mes_notes = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);

// 3. Calcul de la moyenne générale
$moyenne_generale = 0;
$nombre_notes = count($mes_notes);
if ($nombre_notes > 0) {
    $moyenne_generale = round(array_sum(array_column($mes_notes, 'valeur_note')) / $nombre_notes, 2);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Notes - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Styles spécifiques à l'impression (Cachés à l'écran, actifs uniquement en impression PDF) */
        @media print {
            body { background-color: white !important; }
            .top-bar, .top-nav, .btn-print, .no-print { display: none !important; }
            .container { padding: 0 !important; margin: 0 !important; max-width: 100% !important; animation: none !important; }
            .card { box-shadow: none !important; border: 1px solid #000 !important; padding: 20px !important; }
            .print-header { display: block !important; margin-bottom: 30px; text-align: center; border-bottom: 2px solid #C5A059; padding-bottom: 10px; }
            .badge { border: 1px solid #000; color: #000; background: transparent; }
        }

        /* En-tête visible uniquement à l'impression */
        .print-header { display: none; }
        
        /* Tableaux spécifiques relevé de notes */
        .transcript-table th { background-color: #fdfaf5; color: var(--primary); font-size: 12px; }
        .transcript-table td { padding: 16px 10px; }
        .category-row td { background-color: #fafafa; font-weight: bold; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container">
        <div class="print-header">
            <h2>Haute École de Joaillerie</h2>
            <p>Relevé de notes officiel de l'année académique en cours.</p>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px;" class="no-print">
            <div>
                <h1 style="margin:0; color:var(--primary);">Bulletin de Notes</h1>
                <p style="margin:5px 0 0 0; color:var(--text-muted);">Consultez ou imprimez votre relevé officiel.</p>
            </div>
            <button onclick="window.print()" class="btn-action btn-print" style="background: var(--text-main);">
                🖨️ Télécharger en PDF / Imprimer
            </button>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid var(--border);">
                <div>
                    <h3 style="margin: 0 0 5px 0; color: var(--text-main); font-family: 'Inter', sans-serif; font-weight: 600;">
                        <?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?>
                    </h3>
                    <p style="margin: 0; color: var(--text-muted); font-size: 14px;">Numéro étudiant : <strong><?= $matricule ?></strong></p>
                </div>
                <div style="text-align: right;">
                    <p style="margin: 0 0 5px 0; color: var(--text-muted); font-size: 14px;">Année : <strong>2026/2027</strong></p>
                    <p style="margin: 0; color: var(--text-muted); font-size: 14px;">Statut : <strong>Inscrit(e)</strong></p>
                </div>
            </div>

            <?php if (empty($mes_notes)): ?>
                <div style="text-align: center; padding: 40px 0;">
                    <span style="font-size: 40px;">📝</span>
                    <p style="color: var(--text-muted); font-weight: 500; margin-top: 15px;">Aucune évaluation n'a encore été rattachée à votre dossier.</p>
                </div>
            <?php else: ?>
                <table class="transcript-table">
                    <tr>
                        <th style="width: 35%;">Matière</th>
                        <th style="width: 15%;">Note / 20</th>
                        <th style="width: 15%;">Résultat</th>
                        <th style="width: 35%;">Appréciation du jury / professeur</th>
                    </tr>
                    
                    <?php 
                    $categorie_actuelle = '';
                    foreach ($mes_notes as $note): 
                        // Séparateur de catégorie
                        if ($note['categorie'] !== $categorie_actuelle) {
                            $categorie_actuelle = $note['categorie'];
                            echo "<tr class='category-row'><td colspan='4'>" . htmlspecialchars($categorie_actuelle) . "</td></tr>";
                        }
                        
                        $badge_class = ($note['valeur_note'] >= 10) ? 'badge-success' : 'badge-danger';
                        $resultat_texte = ($note['valeur_note'] >= 10) ? 'Validé' : 'Non Validé';
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($note['titre']) ?></strong></td>
                            <td><strong style="font-size: 15px; color: var(--text-main);"><?= htmlspecialchars($note['valeur_note']) ?></strong></td>
                            <td><span class="badge <?= $badge_class ?>"><?= $resultat_texte ?></span></td>
                            <td style="font-style: italic; color: var(--text-muted); font-size: 13px;">
                                <?= !empty($note['commentaire']) ? htmlspecialchars($note['commentaire']) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <div style="margin-top: 30px; padding: 20px; background-color: var(--bg-body); border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; color: var(--text-main); text-transform: uppercase; font-size: 14px; letter-spacing: 1px;">Moyenne Générale</span>
                    <span style="font-size: 24px; font-weight: bold; color: var(--primary); font-family: 'Playfair Display', serif;">
                        <?= number_format($moyenne_generale, 2, ',', ' ') ?> / 20
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php include 'footer.php'; ?>
</body>
</html>