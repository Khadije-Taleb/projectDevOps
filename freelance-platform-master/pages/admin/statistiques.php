<?php
require_once '../../includes/header.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant qu\'administrateur pour accéder à cette page.');
    redirect('../../index.php');
}

$database = new Database();
$conn = $database->getConnection();


$stmt = $conn->query("SELECT COUNT(*) FROM UTILISATEUR WHERE id_role = 2"); 
$nb_clients = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM UTILISATEUR WHERE id_role = 3"); 
$nb_freelancers = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM PROJET");
$nb_projets = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM PROPOSITION");
$nb_propositions = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM EMBAUCHE");
$nb_embauches = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM PROJET WHERE statut = 'terminé'");
$nb_projets_termines = $stmt->fetchColumn();

$stats_mois = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m-01', strtotime("-$i months"));
    $date_fin = date('Y-m-t', strtotime("-$i months"));
    $mois = date('m/Y', strtotime("-$i months"));
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM UTILISATEUR WHERE date_inscription BETWEEN ? AND ?");
    $stmt->execute([$date, $date_fin]);
    $nouveaux_utilisateurs = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM PROJET WHERE date_crea BETWEEN ? AND ?");
    $stmt->execute([$date, $date_fin]);
    $nouveaux_projets = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION WHERE date_proposition BETWEEN ? AND ?");
    $stmt->execute([$date, $date_fin]);
    $nouvelles_propositions = $stmt->fetchColumn();
    
    $stats_mois[] = [
        'mois' => $mois,
        'utilisateurs' => $nouveaux_utilisateurs,
        'projets' => $nouveaux_projets,
        'propositions' => $nouvelles_propositions
    ];
}

$stmt = $conn->query("
    SELECT categorie, COUNT(*) as nb_projets
    FROM PROJET
    WHERE categorie IS NOT NULL AND categorie != ''
    GROUP BY categorie
    ORDER BY nb_projets DESC
    LIMIT 5
");
$top_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("
    SELECT statut, COUNT(*) as nb_projets
    FROM PROJET
    GROUP BY statut
    ORDER BY nb_projets DESC
");
$projets_par_statut = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("
    SELECT 
        COUNT(*) as total_propositions,
        SUM(CASE WHEN statut = 'acceptée' THEN 1 ELSE 0 END) as propositions_acceptees,
        SUM(CASE WHEN statut = 'refusée' THEN 1 ELSE 0 END) as propositions_refusees,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as propositions_en_attente
    FROM PROPOSITION
");
$stats_propositions = $stmt->fetch(PDO::FETCH_ASSOC);
$taux_conversion = $stats_propositions['total_propositions'] > 0 ? 
    round(($stats_propositions['propositions_acceptees'] / $stats_propositions['total_propositions']) * 100, 2) : 0;

$stmt = $conn->query("SELECT AVG(budget) FROM PROJET");
$budget_moyen = $stmt->fetchColumn();


$stmt = $conn->query("SELECT AVG(note) FROM EVALUATION");
$note_moyenne = $stmt->fetchColumn();
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Statistiques</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Retour au tableau de bord
        </a>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-primary mb-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5 class="card-title">Utilisateurs</h5>
                    <p class="card-text display-6"><?php echo $nb_clients + $nb_freelancers; ?></p>
                    <div class="d-flex justify-content-around">
                        <div>
                            <small class="text-muted">Clients</small>
                            <p class="mb-0 fw-bold"><?php echo $nb_clients; ?></p>
                        </div>
                        <div>
                            <small class="text-muted">Freelances</small>
                            <p class="mb-0 fw-bold"><?php echo $nb_freelancers; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-success mb-3">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h5 class="card-title">Projets</h5>
                    <p class="card-text display-6"><?php echo $nb_projets; ?></p>
                    <div class="d-flex justify-content-around">
                        <div>
                            <small class="text-muted">Terminés</small>
                            <p class="mb-0 fw-bold"><?php echo $nb_projets_termines; ?></p>
                        </div>
                        <div>
                            <small class="text-muted">Budget moyen</small>
                            <p class="mb-0 fw-bold"><?php echo number_format($budget_moyen ?? 0, 2); ?> UM-N</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-info mb-3">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h5 class="card-title">Propositions</h5>
                    <p class="card-text display-6"><?php echo $nb_propositions; ?></p>
                    <div class="d-flex justify-content-around">
                        <div>
                            <small class="text-muted">Acceptées</small>
                            <p class="mb-0 fw-bold"><?php echo $stats_propositions['propositions_acceptees']; ?></p>
                        </div>
                        <div>
                            <small class="text-muted">Taux de conversion</small>
                            <p class="mb-0 fw-bold"><?php echo $taux_conversion; ?>%</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
      
    </div>
    
    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Évolution sur les 12 derniers mois</h5>
                </div>
                <div class="card-body">
                    <canvas id="evolutionChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Top 5 des catégories</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoriesChart" height="200"></canvas>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Statut des projets</h5>
                </div>
                <div class="card-body">
                    <canvas id="statutsChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Répartition des propositions</h5>
                </div>
                <div class="card-body">
                    <canvas id="propositionsChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Répartition des utilisateurs</h5>
                </div>
                <div class="card-body">
                    <canvas id="usersChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
   
    const evolutionData = {
        labels: <?php echo json_encode(array_column($stats_mois, 'mois')); ?>,
        datasets: [
            {
                label: 'Nouveaux utilisateurs',
                data: <?php echo json_encode(array_column($stats_mois, 'utilisateurs')); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Nouveaux projets',
                data: <?php echo json_encode(array_column($stats_mois, 'projets')); ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Nouvelles propositions',
                data: <?php echo json_encode(array_column($stats_mois, 'propositions')); ?>,
                borderColor: '#0dcaf0',
                backgroundColor: 'rgba(13, 202, 240, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    };
    

    new Chart(document.getElementById('evolutionChart'), {
        type: 'line',
        data: evolutionData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
   
    const categoriesData = {
        labels: <?php echo json_encode(array_column($top_categories, 'categorie')); ?>,
        datasets: [{
            label: 'Nombre de projets',
            data: <?php echo json_encode(array_column($top_categories, 'nb_projets')); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)'
            ],
            borderWidth: 1
        }]
    };
    
   
    new Chart(document.getElementById('categoriesChart'), {
        type: 'doughnut',
        data: categoriesData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
    
    
    const statutsData = {
        labels: <?php echo json_encode(array_column($projets_par_statut, 'statut')); ?>,
        datasets: [{
            label: 'Nombre de projets',
            data: <?php echo json_encode(array_column($projets_par_statut, 'nb_projets')); ?>,
            backgroundColor: [
                'rgba(25, 135, 84, 0.7)',  
                'rgba(13, 202, 240, 0.7)', 
                'rgba(13, 110, 253, 0.7)', 
                'rgba(220, 53, 69, 0.7)'  
            ],
            borderColor: [
                'rgba(25, 135, 84, 1)',
                'rgba(13, 202, 240, 1)',
                'rgba(13, 110, 253, 1)',
                'rgba(220, 53, 69, 1)'
            ],
            borderWidth: 1
        }]
    };
    
  
    new Chart(document.getElementById('statutsChart'), {
        type: 'pie',
        data: statutsData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
    
  
    const propositionsData = {
        labels: ['Acceptées', 'Refusées', 'En attente'],
        datasets: [{
            label: 'Nombre de propositions',
            data: [
                <?php echo $stats_propositions['propositions_acceptees']; ?>,
                <?php echo $stats_propositions['propositions_refusees']; ?>,
                <?php echo $stats_propositions['propositions_en_attente']; ?>
            ],
            backgroundColor: [
                'rgba(25, 135, 84, 0.7)',  
                'rgba(220, 53, 69, 0.7)',  
                'rgba(255, 193, 7, 0.7)'   
            ],
            borderColor: [
                'rgba(25, 135, 84, 1)',
                'rgba(220, 53, 69, 1)',
                'rgba(255, 193, 7, 1)'
            ],
            borderWidth: 1
        }]
    };
    
    new Chart(document.getElementById('propositionsChart'), {
        type: 'bar',
        data: propositionsData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    const usersData = {
        labels: ['Clients', 'Freelances'],
        datasets: [{
            label: 'Nombre d\'utilisateurs',
            data: [<?php echo $nb_clients; ?>, <?php echo $nb_freelancers; ?>],
            backgroundColor: [
                'rgba(13, 110, 253, 0.7)',  
                'rgba(25, 135, 84, 0.7)'    
            ],
            borderColor: [
                'rgba(13, 110, 253, 1)',
                'rgba(25, 135, 84, 1)'
            ],
            borderWidth: 1
        }]
    };
    
   
    new Chart(document.getElementById('usersChart'), {
        type: 'bar',
        data: usersData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
