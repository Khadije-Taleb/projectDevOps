<?php
require_once '../includes/header.php';

if (!isLoggedIn()) {
    redirect('../index.php');
}

$database = new Database();
$conn = $database->getConnection();

$projets_recents = [];
$propositions_recentes = [];

if (isClient()) {
    $client_id = getClientId($conn, getUserId());
    
    if ($client_id) {
        $stmt = $conn->prepare("
            SELECT p.*, 
                   (SELECT COUNT(*) FROM PROPOSITION WHERE id_projet = p.id_projet) as nb_propositions,
                   (SELECT COUNT(*) FROM PROPOSITION WHERE id_projet = p.id_projet AND statut = 'en_attente') as nb_propositions_en_attente
            FROM PROJET p 
            WHERE p.id_client = ? 
            ORDER BY p.date_crea DESC 
            LIMIT 5
        ");
        $stmt->execute([$client_id]);
        $projets_recents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("
            SELECT prop.*, p.titre as projet_titre, f.id as freelancer_id, u.nom, u.prenom, u.photo_profile
            FROM PROPOSITION prop
            JOIN PROJET p ON prop.id_projet = p.id_projet
            JOIN FREELANCER f ON prop.id_freelancer = f.id
            JOIN UTILISATEUR u ON f.id_utilisateur = u.id_utilisateur
            WHERE p.id_client = ? AND prop.statut = 'en_attente'
            ORDER BY prop.date_proposition DESC
            LIMIT 5
        ");
        $stmt->execute([$client_id]);
        $propositions_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif (isFreelancer()) {
    $freelancer_id = getFreelancerId($conn, getUserId());
    
    if ($freelancer_id) {
        $stmt = $conn->prepare("
            SELECT p.*, c.id as client_id, u.nom, u.prenom
            FROM PROJET p
            JOIN CLIENT c ON p.id_client = c.id
            JOIN UTILISATEUR u ON c.id_utilisateur = u.id_utilisateur
            WHERE p.statut = 'ouvert'
            ORDER BY p.date_crea DESC
            LIMIT 10
        ");
        $stmt->execute();
        $projets_recents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("
            SELECT prop.*, p.titre as projet_titre, p.id_client, c.id_utilisateur as client_user_id, u.nom, u.prenom
            FROM PROPOSITION prop
            JOIN PROJET p ON prop.id_projet = p.id_projet
            JOIN CLIENT c ON p.id_client = c.id
            JOIN UTILISATEUR u ON c.id_utilisateur = u.id_utilisateur
            WHERE prop.id_freelancer = ?
            ORDER BY prop.date_proposition DESC
            LIMIT 5
        ");
        $stmt->execute([$freelancer_id]);
        $propositions_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h1 class="h4 mb-3">Bienvenue, <?php echo $_SESSION['prenom']; ?> !</h1>
                <p class="text-muted">
                    <?php if (isClient()): ?>
                    Publiez des projets et trouvez les meilleurs freelances pour les réaliser.
                    <?php elseif (isFreelancer()): ?>
                    Trouvez des projets qui correspondent à vos compétences et proposez vos services.
                    <?php endif; ?>
                </p>
                
                <?php if (isClient()): ?>
                <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                    <a href="creer-projet.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i> Publier un projet
                    </a>
                    <a href="projets.php?mes_projets=1" class="btn btn-outline-primary">
                        <i class="fas fa-list me-2"></i> Voir mes projets
                    </a>
                </div>
                <?php elseif (isFreelancer()): ?>
                <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                    <a href="projets.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i> Parcourir les projets
                    </a>
                    <a href="propositions.php" class="btn btn-outline-primary">
                        <i class="fas fa-clipboard-list me-2"></i> Mes propositions
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isClient() && !empty($propositions_recentes)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Propositions récentes</h2>
                <a href="mes-propositions-recues.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($propositions_recentes as $proposition): ?>
                    <a href="projet-details.php?id=<?php echo $proposition['id_projet']; ?>#proposition-<?php echo $proposition['id_proposition']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($proposition['projet_titre']); ?></h6>
                                <p class="mb-1 text-muted small">
                                    <img src="../assets/img/<?php echo !empty($proposition['photo_profile']) ? htmlspecialchars($proposition['photo_profile']) : 'default.jpg'; ?>" alt="Photo de profil" class="rounded-circle me-1" width="20" height="20">
                                    <?php echo htmlspecialchars($proposition['prenom'] . ' ' . $proposition['nom']); ?> a proposé <?php echo number_format($proposition['prix_souhaité'], 2); ?> UM-N
                                </p>
                            </div>
                            <span class="badge bg-warning">Nouvelle</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($projets_recents)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">
                    <?php if (isClient()): ?>
                    Mes projets récents
                    <?php else: ?>
                    Projets récents
                    <?php endif; ?>
                </h2>
                <a href="projets.php<?php echo isClient() ? '?mes_projets=1' : ''; ?>" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($projets_recents as $projet): ?>
                    <a href="projet-details.php?id=<?php echo $projet['id_projet']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($projet['titre']); ?></h6>
                                <p class="mb-1 text-muted small">
                                    <?php if (isFreelancer()): ?>
                                    <span>Client: <?php echo htmlspecialchars($projet['prenom'] . ' ' . $projet['nom']); ?></span>
                                    <span class="mx-2">•</span>
                                    <?php endif; ?>
                                    <span>Budget: <?php echo number_format($projet['budget'], 2); ?> UM-N</span>
                                    <span class="mx-2">•</span>
                                    <span>Publié le <?php echo formatDate($projet['date_crea'], 'd/m/Y'); ?></span>
                                </p>
                            </div>
                            <div>
                                <span class="badge bg-<?php 
                                    echo $projet['statut'] === 'ouvert' ? 'success' : 
                                        ($projet['statut'] === 'en_cours' ? 'info' : 
                                            ($projet['statut'] === 'terminé' ? 'primary' : 'danger')); 
                                ?>">
                                    <?php echo ucfirst($projet['statut']); ?>
                                </span>
                                <?php if (isClient() && isset($projet['nb_propositions_en_attente']) && $projet['nb_propositions_en_attente'] > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $projet['nb_propositions_en_attente']; ?> nouvelle(s)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Mon profil</h2>
            </div>
            <div class="card-body text-center">
                <img src="../assets/img/<?php echo !empty($_SESSION['photo_profile']) ? htmlspecialchars($_SESSION['photo_profile']) : 'default.jpg'; ?>" alt="Photo de profil" class="rounded-circle mb-3" width="100" height="100">
                <h3 class="h5 mb-1"><?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?></h3>
                <p class="text-muted mb-3"><?php echo isClient() ? 'Client' : 'Freelance'; ?></p>
                <a href="profil.php" class="btn btn-outline-primary btn-sm">Modifier mon profil</a>
            </div>
        </div>
        
        <?php if (isFreelancer() && !empty($propositions_recentes)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Mes propositions récentes</h2>
                <a href="propositions.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($propositions_recentes as $proposition): ?>
                    <a href="projet-details.php?id=<?php echo $proposition['id_projet']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($proposition['projet_titre']); ?></h6>
                                <p class="mb-1 text-muted small">
                                    Prix proposé: <?php echo number_format($proposition['prix_souhaité'], 2); ?> UM-N
                                </p>
                            </div>
                            <span class="badge bg-<?php 
                                echo $proposition['statut'] === 'en_attente' ? 'warning' : 
                                    ($proposition['statut'] === 'acceptée' ? 'success' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($proposition['statut']); ?>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Statistiques</h2>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php if (isClient()): ?>
                    <?php
                    $client_id = getClientId($conn, getUserId());
                    if ($client_id) {
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM PROJET WHERE id_client = ?");
                        $stmt->execute([$client_id]);
                        $total_projets = $stmt->fetchColumn();
                        
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM PROJET WHERE id_client = ? AND statut = 'terminé'");
                        $stmt->execute([$client_id]);
                        $projets_termines = $stmt->fetchColumn();
                        
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION p JOIN PROJET proj ON p.id_projet = proj.id_projet WHERE proj.id_client = ?");
                        $stmt->execute([$client_id]);
                        $total_propositions = $stmt->fetchColumn();
                    }
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Projets publiés
                        <span class="badge bg-primary rounded-pill"><?php echo $total_projets ?? 0; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Projets terminés
                        <span class="badge bg-success rounded-pill"><?php echo $projets_termines ?? 0; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Propositions reçues
                        <span class="badge bg-info rounded-pill"><?php echo $total_propositions ?? 0; ?></span>
                    </li>
                    <?php elseif (isFreelancer()): ?>
                    <?php
                    $freelancer_id = getFreelancerId($conn, getUserId());
                    if ($freelancer_id) {
                                               $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION WHERE id_freelancer = ?");
                        $stmt->execute([$freelancer_id]);
                        $total_propositions = $stmt->fetchColumn();
                        
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION WHERE id_freelancer = ? AND statut = 'acceptée'");
                        $stmt->execute([$freelancer_id]);
                        $propositions_acceptees = $stmt->fetchColumn();
                        
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM EMBAUCHE WHERE id_freelancer = ? AND date_fin IS NOT NULL");
                        $stmt->execute([$freelancer_id]);
                        $projets_termines = $stmt->fetchColumn();
                    }
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Propositions envoyées
                        <span class="badge bg-primary rounded-pill"><?php echo $total_propositions ?? 0; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Propositions acceptées
                        <span class="badge bg-success rounded-pill"><?php echo $propositions_acceptees ?? 0; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Projets terminés
                        <span class="badge bg-info rounded-pill"><?php echo $projets_termines ?? 0; ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
