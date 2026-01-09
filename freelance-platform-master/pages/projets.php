<?php
require_once '../includes/header.php';

if (isset($_GET['action']) && $_GET['action'] === 'nouveau' && isClient()) {
    redirect('creer-projet.php' . (isset($_GET['mes_projets']) ? '?retour=mes_projets' : ''));
}

$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->query("SELECT DISTINCT categorie FROM PROJET WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$categorie = isset($_GET['categorie']) ? $_GET['categorie'] : '';
$budget_min = isset($_GET['budget_min']) && is_numeric($_GET['budget_min']) ? floatval($_GET['budget_min']) : null;
$budget_max = isset($_GET['budget_max']) && is_numeric($_GET['budget_max']) ? floatval($_GET['budget_max']) : null;
$recherche = isset($_GET['recherche']) ? $_GET['recherche'] : '';
$mes_projets = isset($_GET['mes_projets']) && isClient() ? true : false;

$sql = "SELECT p.*, c.id as client_id, u.nom, u.prenom, u.photo_profile,
        (SELECT COUNT(*) FROM PROPOSITION WHERE id_projet = p.id_projet) as nb_propositions,
        (SELECT COUNT(*) FROM PROPOSITION WHERE id_projet = p.id_projet AND statut = 'en_attente') as nb_propositions_en_attente";

if (isFreelancer() && isset($_GET['mes_propositions'])) {
    $sql .= ", (SELECT COUNT(*) FROM PROPOSITION WHERE id_projet = p.id_projet AND id_freelancer = ?) as a_propose";
}

$sql .= " FROM PROJET p 
         JOIN CLIENT c ON p.id_client = c.id 
         JOIN UTILISATEUR u ON c.id_utilisateur = u.id_utilisateur";

$params = [];
$where_clauses = [];

if ($mes_projets) {
    $client_id = getClientId($conn, getUserId());
    if ($client_id) {
        $where_clauses[] = "p.id_client = ?";
        $params[] = $client_id;
    }
} else {
    $where_clauses[] = "p.statut = 'ouvert'";
}

if (!empty($categorie)) {
    $where_clauses[] = "p.categorie = ?";
    $params[] = $categorie;
}

if (!is_null($budget_min)) {
    $where_clauses[] = "p.budget >= ?";
    $params[] = $budget_min;
}

if (!is_null($budget_max)) {
    $where_clauses[] = "p.budget <= ?";
    $params[] = $budget_max;
}

if (!empty($recherche)) {
    $where_clauses[] = "(p.titre LIKE ? OR p.description LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

if (isFreelancer() && isset($_GET['mes_propositions'])) {
    $freelancer_id = getFreelancerId($conn, getUserId());
    if ($freelancer_id) {
        array_unshift($params, $freelancer_id);
    }
}

$sql .= " ORDER BY p.date_crea DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$projets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resultats_trouves = !empty($projets);
?>

<div class="row">
    <!-- Filtres -->
    <div class="col-md-3 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Filtres</h5>
                <form method="get" id="filterForm">
                    <?php if ($mes_projets): ?>
                    <input type="hidden" name="mes_projets" value="1">
                    <?php endif; ?>
                    
                    <?php if (isFreelancer() && isset($_GET['mes_propositions'])): ?>
                    <input type="hidden" name="mes_propositions" value="1">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="recherche" class="form-label">Recherche</label>
                        <input type="text" class="form-control" id="recherche" name="recherche" value="<?php echo htmlspecialchars($recherche); ?>" placeholder="Titre, description...">
                    </div>
                    
                    <div class="mb-3">
                        <label for="categorie" class="form-label">Catégorie</label>
                        <select class="form-select" id="categorie" name="categorie">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categorie === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Budget</label>
                        <div class="row g-2">
                            <div class="col">
                                <input type="number" class="form-control" name="budget_min" placeholder="Min" value="<?php echo $budget_min ?? ''; ?>">
                            </div>
                            <div class="col">
                                <input type="number" class="form-control" name="budget_max" placeholder="Max" value="<?php echo $budget_max ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Appliquer</button>
                        <a href="<?php echo $mes_projets ? '?mes_projets=1' : (isFreelancer() && isset($_GET['mes_propositions']) ? '?mes_propositions=1' : ''); ?>" class="btn btn-outline-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Liste des projets -->
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo $mes_projets ? 'Mes projets' : 'Projets disponibles'; ?></h1>
            <?php if (isClient()): ?>
            <a href="?action=nouveau<?php echo $mes_projets ? '&mes_projets=1' : ''; ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Publier un projet
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (!$resultats_trouves): ?>
        <div class="alert alert-info">
            <p class="mb-0">
                <?php if (!empty($recherche) || !empty($categorie) || !is_null($budget_min) || !is_null($budget_max)): ?>
                    Aucun projet ne correspond à vos critères de recherche.
                <?php else: ?>
                    Aucun projet disponible pour le moment.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php foreach ($projets as $projet): ?>
            <div class="col">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title mb-0">
                                <a href="projet-details.php?id=<?php echo $projet['id_projet']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($projet['titre']); ?>
                                </a>
                            </h5>
                            <span class="badge bg-<?php 
                                echo $projet['statut'] === 'ouvert' ? 'success' : 
                                    ($projet['statut'] === 'en_cours' ? 'info' : 
                                        ($projet['statut'] === 'terminé' ? 'primary' : 'danger')); 
                            ?>">
                                <?php echo ucfirst($projet['statut']); ?>
                            </span>
                        </div>
                        
                        <p class="card-text text-muted small mb-3">
                            <i class="fas fa-tag me-1"></i> <?php echo !empty($projet['categorie']) ? htmlspecialchars($projet['categorie']) : 'Non catégorisé'; ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-calendar me-1"></i> <?php echo date('d/m/Y', strtotime($projet['date_crea'])); ?>
                            <?php if (!empty($projet['date_limite'])): ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-clock me-1"></i> Échéance: <?php echo date('d/m/Y', strtotime($projet['date_limite'])); ?>
                            <?php endif; ?>
                        </p>
                        
                        <p class="card-text"><?php echo nl2br(htmlspecialchars(truncateText($projet['description'], 150))); ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="d-flex align-items-center">
                                <img src="../assets/img/<?php echo !empty($projet['photo_profile']) ? htmlspecialchars($projet['photo_profile']) : 'default.jpg'; ?>" alt="Photo de profil" class="rounded-circle me-2" width="30" height="30">
                                <span><?php echo htmlspecialchars($projet['prenom'] . ' ' . $projet['nom']); ?></span>
                            </div>
                            <span class="fw-bold"><?php echo number_format($projet['budget'], 2); ?> UM-N</span>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <?php if ($mes_projets && $projet['nb_propositions'] > 0): ?>
                                <a href="projet-details.php?id=<?php echo $projet['id_projet']; ?>#propositions" class="text-decoration-none">
                                    <i class="fas fa-comment me-1"></i> 
                                    <span class="badge bg-<?php echo $projet['nb_propositions_en_attente'] > 0 ? 'danger' : 'primary'; ?> rounded-pill">
                                        <?php echo $projet['nb_propositions']; ?>
                                    </span> proposition(s)
                                    <?php if ($projet['nb_propositions_en_attente'] > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $projet['nb_propositions_en_attente']; ?> nouvelle(s)</span>
                                    <?php endif; ?>
                                </a>
                                <?php else: ?>
                                <i class="fas fa-comment me-1"></i> <?php echo $projet['nb_propositions']; ?> proposition(s)
                                <?php endif; ?>
                            </small>
                            <div class="d-flex align-items-center">
                                <a href="projet-details.php?id=<?php echo $projet['id_projet']; ?>" class="btn btn-sm btn-outline-primary">
                                    Détails
                                </a>
                                
                                <?php if (isFreelancer() && $projet['statut'] === 'ouvert'): ?>
                                    <?php if (isset($projet['a_propose']) && $projet['a_propose'] > 0): ?>
                                    <span class="badge bg-success ms-2">Proposition envoyée</span>
                                    <?php else: ?>
                                    <a href="projet-details.php?id=<?php echo $projet['id_projet']; ?>&action=proposer" class="btn btn-sm btn-primary ms-2">
                                        Proposer
                                    </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($mes_projets && $projet['statut'] === 'ouvert'): ?>
                                <div class="btn-group ms-2">
                                    <a href="modifier-projet.php?id=<?php echo $projet['id_projet']; ?>" class="btn btn-sm btn-outline-secondary" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Supprimer" 
                                            onclick="confirmerSuppression(<?php echo $projet['id_projet']; ?>, '<?php echo addslashes($projet['titre']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
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

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Supprimer le projet
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteProjectForm" action="../api/projets.php?action=supprimer" method="post">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_projet" id="delete_id_projet">
                    
                    <div class="text-center mb-4">
                        <div class="display-1 text-danger mb-3">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <h4>Êtes-vous sûr de vouloir supprimer ce projet ?</h4>
                        <p class="lead fw-bold text-danger" id="delete_projet_titre"></p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <p class="mb-0"><strong>Cette action entraînera la suppression de :</strong></p>
                        <ul class="mb-0">
                            <li>Toutes les propositions liées à ce projet</li>
                            <li>Tous les contrats et embauches</li>
                            <li>Toutes les évaluations associées</li>
                            <li>Tous les messages et conversations liés</li>
                        </ul>
                    </div>
                    
                    <p class="text-danger text-center fw-bold mb-0">Cette action est irréversible !</p>
                </div>
                <div class="modal-footer bg-light border-0 justify-content-center">
                    <button type="button" class="btn btn-lg btn-outline-secondary px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-lg btn-danger px-4">
                        <i class="fas fa-trash-alt me-2"></i>Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmerSuppression(id_projet, titre) {
    document.getElementById('delete_id_projet').value = id_projet;
    document.getElementById('delete_projet_titre').textContent = titre;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteProjectModal'));
    modal.show();
}

document.getElementById('deleteProjectForm').addEventListener('submit', function(event) {
    event.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../api/projets.php?action=supprimer', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show';
            alertDiv.innerHTML = `
                ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteProjectModal'));
            modal.hide();
            
            setTimeout(() => {
                window.location.href = data.redirect || 'projets.php?mes_projets=1';
            }, 1500);
        } else {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteProjectModal'));
            modal.hide();
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = `
            Une erreur est survenue lors de la suppression du projet. Veuillez réessayer.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteProjectModal'));
        modal.hide();
    });
});
</script>

<style>
.card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
}

.badge {
    font-weight: 500;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

.btn-group .btn i {
    font-size: 0.875rem;
}

.modal-content {
    border-radius: 0.5rem;
}

.modal-header {
    border-top-left-radius: 0.5rem;
    border-top-right-radius: 0.5rem;
}

.modal-footer {
    border-bottom-left-radius: 0.5rem;
    border-bottom-right-radius: 0.5rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>
