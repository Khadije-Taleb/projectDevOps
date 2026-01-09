<?php
require_once '../includes/header.php';

if (!isLoggedIn() || !isFreelancer()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant que freelance pour accéder à cette page.');
    redirect('../index.php');
}

$database = new Database();
$conn = $database->getConnection();

$freelancer_id = getFreelancerId($conn, getUserId());
if (!$freelancer_id) {
    setFlashMessage('danger', 'Impossible de récupérer votre profil freelance. Veuillez contacter l\'administrateur.');
    redirect('../index.php');
}

$statut = isset($_GET['statut']) ? $_GET['statut'] : '';

$sql = "SELECT p.*, 
               proj.titre as projet_titre, proj.description as projet_description, proj.budget as projet_budget, proj.statut as projet_statut,
               c.id as client_id, u.id_utilisateur as client_user_id, u.nom, u.prenom, u.photo_profile
        FROM PROPOSITION p 
        JOIN PROJET proj ON p.id_projet = proj.id_projet 
        JOIN CLIENT c ON proj.id_client = c.id 
        JOIN UTILISATEUR u ON c.id_utilisateur = u.id_utilisateur 
        WHERE p.id_freelancer = ?";

$params = [$freelancer_id];

if (!empty($statut)) {
    $sql .= " AND p.statut = ?";
    $params[] = $statut;
}

$sql .= " ORDER BY p.date_proposition DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$propositions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Filtres</h5>
                    <form method="get">
                        <div class="mb-3">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="">Tous les statuts</option>
                                <option value="en_attente" <?php echo $statut === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="acceptée" <?php echo $statut === 'acceptée' ? 'selected' : ''; ?>>Acceptée</option>
                                <option value="refusée" <?php echo $statut === 'refusée' ? 'selected' : ''; ?>>Refusée</option>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Appliquer</button>
                            <a href="propositions.php" class="btn btn-outline-secondary">Réinitialiser</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="card-title">Statistiques</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Total des propositions
                            <span class="badge bg-primary rounded-pill">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION WHERE id_freelancer = ?");
                                $stmt->execute([$freelancer_id]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            En attente
                            <span class="badge bg-warning rounded-pill">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION WHERE id_freelancer = ? AND statut = 'en_attente'");
                                $stmt->execute([$freelancer_id]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Acceptées
                            <span class="badge bg-success rounded-pill">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION WHERE id_freelancer = ? AND statut = 'acceptée'");
                                $stmt->execute([$freelancer_id]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Refusées
                            <span class="badge bg-danger rounded-pill">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION WHERE id_freelancer = ? AND statut = 'refusée'");
                                $stmt->execute([$freelancer_id]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <h1 class="mb-4">Mes propositions</h1>
            
            <?php if (empty($propositions)): ?>
            <div class="alert alert-info">
                <p class="mb-0">Vous n'avez pas encore fait de proposition.</p>
            </div>
            <div class="text-center mt-4">
                <a href="projets.php" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i> Parcourir les projets
                </a>
            </div>
            <?php else: ?>
            <div class="row row-cols-1 g-4">
                <?php foreach ($propositions as $proposition): ?>
                <div class="col">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <a href="projet-details.php?id=<?php echo $proposition['id_projet']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($proposition['projet_titre']); ?>
                                    </a>
                                </h5>
                                <small class="text-muted">
                                    Proposition envoyée le <?php echo date('d/m/Y à H:i', strtotime($proposition['date_proposition'])); ?>
                                </small>
                            </div>
                            <div>
                                <span class="badge bg-<?php 
                                    echo $proposition['statut'] === 'en_attente' ? 'warning' : 
                                        ($proposition['statut'] === 'acceptée' ? 'success' : 'danger'); 
                                ?> fs-6">
                                    <?php echo ucfirst($proposition['statut']); ?>
                                </span>
                                <span class="badge bg-<?php 
                                    echo $proposition['projet_statut'] === 'ouvert' ? 'success' : 
                                        ($proposition['projet_statut'] === 'en_cours' ? 'info' : 
                                            ($proposition['projet_statut'] === 'terminé' ? 'primary' : 'danger')); 
                                ?> ms-2">
                                    Projet: <?php echo ucfirst($proposition['projet_statut']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center mb-3 mb-md-0">
                                    <img src="../assets/img/<?php echo !empty($proposition['photo_profile']) ? htmlspecialchars($proposition['photo_profile']) : 'default.jpg'; ?>" 
                                         alt="Photo de profil" class="rounded-circle mb-2" width="80" height="80">
                                    <h6><?php echo htmlspecialchars($proposition['prenom'] . ' ' . $proposition['nom']); ?></h6>
                                    <p class="small text-muted mb-0">Client</p>
                                </div>
                                <div class="col-md-9">
                                    <div class="d-flex justify-content-between mb-3">
                                        <div>
                                            <h6 class="mb-0">Prix proposé</h6>
                                            <p class="fs-5 fw-bold text-primary mb-0"><?php echo number_format($proposition['prix_souhaité'], 2); ?> UM-N</p>
                                            <small class="text-muted">Budget du projet: <?php echo number_format($proposition['projet_budget'], 2); ?> UM-N</small>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Délai proposé</h6>
                                            <p class="fs-5 fw-bold text-primary mb-0"><?php echo $proposition['delai']; ?> jours</p>
                                        </div>
                                    </div>
                                    
                                    <h6>Mon message:</h6>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($proposition['message'])); ?></p>
                                    
                                    <div class="d-flex justify-content-end mt-3">
                                        <a href="projet-details.php?id=<?php echo $proposition['id_projet']; ?>" class="btn btn-outline-primary me-2">
                                            <i class="fas fa-eye me-1"></i> Voir le projet
                                        </a>
                                        <?php if ($proposition['statut'] === 'acceptée'): ?>
                                        <a href="messages.php?action=chat&client_id=<?php echo $proposition['client_user_id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-envelope me-1"></i> Contacter le client
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
}
</style>

<?php require_once '../includes/footer.php'; ?>
