<?php
require_once '../../includes/header.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant qu\'administrateur pour accéder à cette page.');
    redirect('../../index.php');
}

if (!isset($_GET['id'])) {
    setFlashMessage('danger', 'ID de conversation non spécifié.');
    redirect('conversations.php');
}

$id_conversation = intval($_GET['id']);
$database = new Database();
$conn = $database->getConnection();


$stmt = $conn->prepare("
    SELECT c.*, 
           cl.id as client_id, cl.id_utilisateur as client_user_id, 
           f.id as freelancer_id, f.id_utilisateur as freelancer_user_id,
           u_client.nom as client_nom, u_client.prenom as client_prenom, u_client.photo_profile as client_photo,
           u_freelancer.nom as freelancer_nom, u_freelancer.prenom as freelancer_prenom, u_freelancer.photo_profile as freelancer_photo
    FROM CONVERSATION c 
    JOIN CLIENT cl ON c.id_client = cl.id 
    JOIN FREELANCER f ON c.id_freelancer = f.id
    JOIN UTILISATEUR u_client ON cl.id_utilisateur = u_client.id_utilisateur
    JOIN UTILISATEUR u_freelancer ON f.id_utilisateur = u_freelancer.id_utilisateur
    WHERE c.id_conversation = ?
");
$stmt->execute([$id_conversation]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    setFlashMessage('danger', 'Conversation non trouvée.');
    redirect('conversations.php');
}


$stmt = $conn->prepare("
    SELECT m.*, u.nom, u.prenom, u.photo_profile
    FROM MESSAGE m
    JOIN UTILISATEUR u ON m.id_sender = u.id_utilisateur
    WHERE m.id_conversation = ?
    ORDER BY m.date_envoi ASC
");
$stmt->execute([$id_conversation]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $conn->prepare("
    SELECT p.*, prop.id_proposition, prop.statut as proposition_statut
    FROM PROJET p
    JOIN PROPOSITION prop ON p.id_projet = prop.id_projet
    WHERE p.id_client = ? AND prop.id_freelancer = ?
    ORDER BY prop.date_proposition DESC
");
$stmt->execute([$conversation['client_id'], $conversation['freelancer_id']]);
$projets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Détails de la conversation</h1>
        <a href="conversations.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Retour à la liste
        </a>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Informations</h5>
                </div>
                <div class="card-body">
                    <p><strong>ID de conversation:</strong> <?php echo $conversation['id_conversation']; ?></p>
                    <p><strong>Date de création:</strong> <?php echo formatDate($conversation['date_creation'], 'd/m/Y H:i'); ?></p>
                    <p><strong>Dernière activité:</strong> <?php echo $conversation['date_derniere_activite'] ? formatDate($conversation['date_derniere_activite'], 'd/m/Y H:i') : 'Jamais'; ?></p>
                    <p><strong>Nombre de messages:</strong> <?php echo count($messages); ?></p>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Client</h5>
                </div>
                <div class="card-body text-center">
                    <img src="../../assets/img/<?php echo !empty($conversation['client_photo']) ? htmlspecialchars($conversation['client_photo']) : 'default.jpg'; ?>" 
                         alt="Photo de profil" class="rounded-circle mb-3" width="80" height="80">
                    <h5 class="mb-1"><?php echo htmlspecialchars($conversation['client_prenom'] . ' ' . $conversation['client_nom']); ?></h5>
                    <p class="text-muted mb-3">Client</p>
                    <a href="utilisateur-details.php?id=<?php echo $conversation['client_user_id']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-user me-1"></i> Voir le profil
                    </a>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Freelance</h5>
                </div>
                <div class="card-body text-center">
                    <img src="../../assets/img/<?php echo !empty($conversation['freelancer_photo']) ? htmlspecialchars($conversation['freelancer_photo']) : 'default.jpg'; ?>" 
                         alt="Photo de profil" class="rounded-circle mb-3" width="80" height="80">
                    <h5 class="mb-1"><?php echo htmlspecialchars($conversation['freelancer_prenom'] . ' ' . $conversation['freelancer_nom']); ?></h5>
                    <p class="text-muted mb-3">Freelance</p>
                    <a href="utilisateur-details.php?id=<?php echo $conversation['freelancer_user_id']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-user me-1"></i> Voir le profil
                    </a>
                </div>
            </div>
            
            <?php if (!empty($projets)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Projets associés</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($projets as $projet): ?>
                        <a href="../../pages/projet-details.php?id=<?php echo $projet['id_projet']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($projet['titre']); ?></h6>
                                <small>
                                    <span class="badge bg-<?php 
                                        echo $projet['statut'] === 'ouvert' ? 'success' : 
                                            ($projet['statut'] === 'en_cours' ? 'info' : 
                                                ($projet['statut'] === 'terminé' ? 'primary' : 'danger')); 
                                    ?>">
                                        <?php echo ucfirst($projet['statut']); ?>
                                    </span>
                                </small>
                            </div>
                            <p class="mb-1 small text-muted">Budget: <?php echo number_format($projet['budget'], 2); ?> UM-N</p>
                            <small>
                                Proposition: 
                                <span class="badge bg-<?php 
                                    echo $projet['proposition_statut'] === 'en_attente' ? 'warning' : 
                                        ($projet['proposition_statut'] === 'acceptée' ? 'success' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($projet['proposition_statut']); ?>
                                </span>
                            </small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Messages</h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-3" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <p class="text-muted mb-0">Aucun message dans cette conversation.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                        <div class="d-flex mb-3 <?php echo $message['id_sender'] == $conversation['client_user_id'] ? '' : 'flex-row-reverse'; ?>">
                            <div class="me-2 <?php echo $message['id_sender'] == $conversation['client_user_id'] ? '' : 'ms-2 me-0 order-1'; ?>">
                                <img src="../../assets/img/<?php echo !empty($message['photo_profile']) ? htmlspecialchars($message['photo_profile']) : 'default.jpg'; ?>" 
                                     alt="Photo de profil" class="rounded-circle" width="40" height="40">
                            </div>
                            <div class="flex-grow-1 <?php echo $message['id_sender'] == $conversation['client_user_id'] ? 'me-5' : 'ms-5'; ?>">
                                <div class="card <?php echo $message['id_sender'] == $conversation['client_user_id'] ? 'bg-light' : 'bg-primary text-white'; ?> border-0">
                                    <div class="card-header bg-transparent border-0 py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold"><?php echo htmlspecialchars($message['prenom'] . ' ' . $message['nom']); ?></span>
                                            <small><?php echo formatDate($message['date_envoi'], 'd/m/Y H:i'); ?></small>
                                        </div>
                                    </div>
                                    <div class="card-body py-2">
                                        <p class="card-text mb-0"><?php echo nl2br(htmlspecialchars($message['contenu'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($messages)): ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Actions administratives</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteConversationModal">
                                <i class="fas fa-trash-alt me-2"></i> Supprimer la conversation
                            </button>
                        </div>
                        
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour supprimer la conversation -->
<div class="modal fade" id="deleteConversationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Supprimer la conversation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteConversationForm" action="../../api/admin.php?action=supprimer_conversation" method="post">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_conversation" value="<?php echo $id_conversation; ?>">
                    
                    <div class="text-center mb-4">
                        <div class="display-1 text-danger mb-3">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <h4>Êtes-vous sûr de vouloir supprimer cette conversation ?</h4>
                    </div>
                    
                    <div class="alert alert-warning">
                        <p class="mb-0"><strong>Attention :</strong> Cette action est irréversible. Tous les messages de cette conversation seront définitivement supprimés.</p>
                    </div>
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

<!-- Modal pour archiver la conversation -->
<div class="modal fade" id="archiveConversationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-white border-0">
                <h5 class="modal-title">
                    <i class="fas fa-archive me-2"></i>
                    Archiver la conversation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="archiveConversationForm" action="../../api/admin.php?action=archiver_conversation" method="post">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_conversation" value="<?php echo $id_conversation; ?>">
                    
                    <div class="text-center mb-4">
                        <div class="display-1 text-warning mb-3">
                            <i class="fas fa-archive"></i>
                        </div>
                        <h4>Êtes-vous sûr de vouloir archiver cette conversation ?</h4>
                    </div>
                    
                    <div class="alert alert-info">
                        <p class="mb-0">La conversation sera archivée et ne sera plus accessible aux utilisateurs, mais restera disponible pour les administrateurs.</p>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 justify-content-center">
                    <button type="button" class="btn btn-lg btn-outline-secondary px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-lg btn-warning px-4">
                        <i class="fas fa-archive me-2"></i>Archiver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const deleteForm = document.getElementById('deleteConversationForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(this.action, {
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
                    
                    setTimeout(() => {
                        window.location.href = 'conversations.php';
                    }, 1500);
                } else {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteConversationModal'));
                    modal.hide();
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    Une erreur est survenue. Veuillez réessayer.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('deleteConversationModal'));
                modal.hide();
            });
        });
    }
    
    
    const archiveForm = document.getElementById('archiveConversationForm');
    if (archiveForm) {
        archiveForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(this.action, {
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
                    
                    setTimeout(() => {
                        window.location.href = 'conversations.php';
                    }, 1500);
                } else {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('archiveConversationModal'));
                    modal.hide();
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    Une erreur est survenue. Veuillez réessayer.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('archiveConversationModal'));
                modal.hide();
            });
        });
    }
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

<?php require_once '../../includes/footer.php'; ?>
