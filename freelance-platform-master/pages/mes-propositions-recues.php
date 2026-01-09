<?php
require_once '../includes/header.php';

if (!isLoggedIn() || !isClient()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant que client pour accéder à cette page.');
    redirect('../index.php');
}

$database = new Database();
$conn = $database->getConnection();

$client_id = getClientId($conn, getUserId());
if (!$client_id) {
    setFlashMessage('danger', 'Impossible de récupérer votre profil client. Veuillez contacter l\'administrateur.');
    redirect('../index.php');
}

$statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$id_projet = isset($_GET['projet']) ? intval($_GET['projet']) : 0;

$stmt = $conn->prepare("SELECT id_projet, titre FROM PROJET WHERE id_client = ? ORDER BY date_crea DESC");
$stmt->execute([$client_id]);
$projets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT p.*, 
               proj.titre as projet_titre, proj.description as projet_description, proj.budget as projet_budget,
               f.id as freelancer_id, f.competences, f.tarif_horaire,
               u.id_utilisateur as freelancer_user_id, u.nom, u.prenom, u.email, u.photo_profile
        FROM PROPOSITION p 
        JOIN PROJET proj ON p.id_projet = proj.id_projet 
        JOIN FREELANCER f ON p.id_freelancer = f.id 
        JOIN UTILISATEUR u ON f.id_utilisateur = u.id_utilisateur 
        WHERE proj.id_client = ?";

$params = [$client_id];

if (!empty($statut)) {
    $sql .= " AND p.statut = ?";
    $params[] = $statut;
}

if ($id_projet > 0) {
    $sql .= " AND p.id_projet = ?";
    $params[] = $id_projet;
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
                        
                        <div class="mb-3">
                            <label for="projet" class="form-label">Projet</label>
                            <select class="form-select" id="projet" name="projet">
                                <option value="">Tous les projets</option>
                                <?php foreach ($projets as $projet): ?>
                                <option value="<?php echo $projet['id_projet']; ?>" <?php echo $id_projet === (int)$projet['id_projet'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($projet['titre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Appliquer</button>
                            <a href="mes-propositions-recues.php" class="btn btn-outline-secondary">Réinitialiser</a>
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
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION p JOIN PROJET proj ON p.id_projet = proj.id_projet WHERE proj.id_client = ?");
                                $stmt->execute([$client_id]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Total des propositions
                            <span class="badge bg-primary rounded-pill">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION p JOIN PROJET proj ON p.id_projet = proj.id_projet WHERE proj.id_client = ?");
                                $stmt->execute([$client_id]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            En attente
                            <span class="badge bg-warning rounded-pill">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION p JOIN PROJET proj ON p.id_projet = proj.id_projet WHERE proj.id_client = ? AND p.statut = 'en_attente'");
                                $stmt->execute([$client_id]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Acceptées
                            <span class="badge bg-success rounded-pill">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION p JOIN PROJET proj ON p.id_projet = proj.id_projet WHERE proj.id_client = ? AND p.statut = 'acceptée'");
                                $stmt->execute([$client_id]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Refusées
                            <span class="badge bg-danger rounded-pill">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION p JOIN PROJET proj ON p.id_projet = proj.id_projet WHERE proj.id_client = ? AND p.statut = 'refusée'");
                                $stmt->execute([$client_id]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <h1 class="mb-4">Propositions reçues</h1>
            
            <?php if (empty($propositions)): ?>
            <div class="alert alert-info">
                <p class="mb-0">Aucune proposition ne correspond à vos critères.</p>
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
                                    Proposition reçue le <?php echo date('d/m/Y à H:i', strtotime($proposition['date_proposition'])); ?>
                                </small>
                            </div>
                            <span class="badge bg-<?php 
                                echo $proposition['statut'] === 'en_attente' ? 'warning' : 
                                    ($proposition['statut'] === 'acceptée' ? 'success' : 'danger'); 
                            ?> fs-6">
                                <?php echo ucfirst($proposition['statut']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center mb-3 mb-md-0">
                                    <img src="../assets/img/<?php echo !empty($proposition['photo_profile']) ? htmlspecialchars($proposition['photo_profile']) : 'default.jpg'; ?>" 
                                         alt="Photo de profil" class="rounded-circle mb-2" width="80" height="80">
                                    <h6><?php echo htmlspecialchars($proposition['prenom'] . ' ' . $proposition['nom']); ?></h6>
                                    <?php if (!empty($proposition['competences'])): ?>
                                    <p class="small text-muted mb-0"><?php echo htmlspecialchars($proposition['competences']); ?></p>
                                    <?php endif; ?>
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
                                    
                                    <h6>Message du freelance:</h6>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($proposition['message'])); ?></p>
                                    
                                    <?php if ($proposition['statut'] === 'en_attente'): ?>
                                    <div class="d-flex justify-content-end mt-3">
                                        <button type="button" class="btn btn-outline-danger me-2" onclick="refuserProposition(<?php echo $proposition['id_proposition']; ?>)">
                                            <i class="fas fa-times me-1"></i> Refuser
                                        </button>
                                        <button type="button" class="btn btn-success" onclick="accepterProposition(<?php echo $proposition['id_proposition']; ?>, <?php echo $proposition['id_projet']; ?>)">
                                            <i class="fas fa-check me-1"></i> Accepter
                                        </button>
                                    </div>
                                    <?php elseif ($proposition['statut'] === 'acceptée'): ?>
                                    <div class="d-flex justify-content-end mt-3">
                                        <a href="messages.php?action=chat&freelancer_id=<?php echo $proposition['freelancer_user_id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-envelope me-1"></i> Contacter le freelance
                                        </a>
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
</div>

<!-- Modal de confirmation pour accepter une proposition -->
<div class="modal fade" id="accepterPropositionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>
                    Accepter la proposition
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="accepterPropositionForm" action="../api/propositions.php?action=accepter" method="post">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_proposition" id="accepter_id_proposition">
                    <input type="hidden" name="id_projet" id="accepter_id_projet">
                    
                    <div class="text-center mb-4">
                        <div class="display-1 text-success mb-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4>Êtes-vous sûr de vouloir accepter cette proposition ?</h4>
                    </div>
                    
                    <div class="alert alert-warning">
                        <p class="mb-0"><strong>En acceptant cette proposition :</strong></p>
                        <ul class="mb-0">
                            <li>Toutes les autres propositions pour ce projet seront automatiquement refusées</li>
                            <li>Le projet passera en statut "en cours"</li>
                            <li>Vous pourrez communiquer avec le freelance via la messagerie</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 justify-content-center">
                    <button type="button" class="btn btn-lg btn-outline-secondary px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-lg btn-success px-4">
                        <i class="fas fa-check me-2"></i>Confirmer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de confirmation pour refuser une proposition -->
<div class="modal fade" id="refuserPropositionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title">
                    <i class="fas fa-times-circle me-2"></i>
                    Refuser la proposition
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="refuserPropositionForm" action="../api/propositions.php?action=refuser" method="post">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_proposition" id="refuser_id_proposition">
                    
                    <div class="text-center mb-4">
                        <div class="display-1 text-danger mb-3">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h4>Êtes-vous sûr de vouloir refuser cette proposition ?</h4>
                    </div>
                    
                    <div class="alert alert-warning">
                        <p class="mb-0">Cette action est irréversible. Le freelance sera notifié que sa proposition a été refusée.</p>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 justify-content-center">
                    <button type="button" class="btn btn-lg btn-outline-secondary px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-lg btn-danger px-4">
                        <i class="fas fa-check me-2"></i>Confirmer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function accepterProposition(id_proposition, id_projet) {
    document.getElementById('accepter_id_proposition').value = id_proposition;
    document.getElementById('accepter_id_projet').value = id_projet;
    
    const modal = new bootstrap.Modal(document.getElementById('accepterPropositionModal'));
    modal.show();
}

function refuserProposition(id_proposition) {
    document.getElementById('refuser_id_proposition').value = id_proposition;
    
    const modal = new bootstrap.Modal(document.getElementById('refuserPropositionModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    const accepterPropositionForm = document.getElementById('accepterPropositionForm');
    accepterPropositionForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const formData = new FormData(accepterPropositionForm);
        
        fetch('../api/propositions.php?action=accepter', {
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
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('accepterPropositionModal'));
                modal.hide();
                
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('accepterPropositionModal'));
                modal.hide();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                Une erreur est survenue lors de l'acceptation de la proposition. Veuillez réessayer.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('accepterPropositionModal'));
            modal.hide();
        });
    });

    const refuserPropositionForm = document.getElementById('refuserPropositionForm');
    refuserPropositionForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const formData = new FormData(refuserPropositionForm);
        
        fetch('../api/propositions.php?action=refuser', {
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
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('refuserPropositionModal'));
                modal.hide();
                
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('refuserPropositionModal'));
                modal.hide();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                Une erreur est survenue lors du refus de la proposition. Veuillez réessayer.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('refuserPropositionModal'));
            modal.hide();
        });
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
