<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrateur') { 
    header("Location: connexion.php"); 
    exit(); 
}
require_once 'db.php';

// ==========================================
// PRÉPARATION DES DONNÉES POUR LES GRAPHIQUES
// ==========================================

// 1. Répartition des étudiants par classe
$repartition = $db->query("
    SELECT g.nom, COUNT(u.id) as nb_etudiants 
    FROM groupes g 
    LEFT JOIN utilisateurs u ON u.groupe_id = g.id AND u.role = 'etudiant' 
    GROUP BY g.id
")->fetchAll();

$labels_classes = []; $data_classes = [];
foreach ($repartition as $row) {
    $labels_classes[] = $row['nom'];
    $data_classes[] = $row['nb_etudiants'];
}

// 2. Moyenne générale par cours
$moyennes = $db->query("
    SELECT c.titre, ROUND(AVG(n.valeur_note), 2) as moyenne 
    FROM cours c 
    JOIN notes n ON c.id = n.cours_id 
    GROUP BY c.id
")->fetchAll();

$labels_cours = []; $data_moyennes = [];
foreach ($moyennes as $row) {
    $labels_cours[] = $row['titre'];
    $data_moyennes[] = $row['moyenne'];
}

// 3. Taux d'absentéisme par matière
$absences = $db->query("
    SELECT c.titre, COUNT(p.id) as nb_absences 
    FROM cours c 
    JOIN presences p ON c.id = p.cours_id 
    WHERE p.statut = 'absent' 
    GROUP BY c.id
")->fetchAll();

$labels_abs = []; $data_abs = [];
foreach ($absences as $row) {
    $labels_abs[] = $row['titre'];
    $data_abs[] = $row['nb_absences'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapports - SmartCampus</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-grid .card { min-width: 0; overflow: hidden; }
        .chart-container { position: relative; height: 300px; width: 100%; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container" style="margin-top:30px;">
        <div style="margin-bottom: 30px;">
            <h1 style="margin:0; color:var(--primary);">Tableau de Bord Analytique</h1>
            <p style="margin:5px 0 0 0; color:var(--text-muted);">Visualisez les performances et l'assiduité de l'établissement.</p>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header"><h2>Effectifs par classe</h2></div>
                <div class="chart-container">
                    <canvas id="chartClasses"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Volume d'absences par matière</h2></div>
                <div class="chart-container">
                    <canvas id="chartAbsences"></canvas>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <div class="card-header"><h2>Moyennes générales par enseignement</h2></div>
            <div class="chart-container" style="height: 350px;">
                <canvas id="chartMoyennes"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Graphique Classes
        new Chart(document.getElementById('chartClasses'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($labels_classes) ?>,
                datasets: [{
                    data: <?= json_encode($data_classes) ?>,
                    backgroundColor: ['#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe'],
                    borderWidth: 1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Graphique Absences
        new Chart(document.getElementById('chartAbsences'), {
            type: 'line',
            data: {
                labels: <?= json_encode($labels_abs) ?>,
                datasets: [{
                    label: 'Nombre d\'absences',
                    data: <?= json_encode($data_abs) ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });

        // Graphique Moyennes
        new Chart(document.getElementById('chartMoyennes'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels_cours) ?>,
                datasets: [{
                    label: 'Moyenne / 20',
                    data: <?= json_encode($data_moyennes) ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 20 } },
                plugins: { legend: { display: false } }
            }
        });
    </script>
    
    <?php include 'footer.php'; ?>
</body>
</html>