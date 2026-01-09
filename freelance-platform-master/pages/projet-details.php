<?php
require_once '../includes/header.php';

if (!isset($_GET['id'])) {
    setFlashMessage('danger', 'ID de projet non spécifié.');
    redirect('projets.php');
}

$id_projet = intval($_GET['id']);
$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("
    SELECT p.*, c.id as client_id, u.id_utilisateur as client_user_id, u.nom, u.prenom, u.photo_profile, u.email,
           (SELECT COUNT(*) FROM PROPOSITION WHERE id_projet = p.id_projet) as nb_propositions
    FROM PROJET p 
    JOIN CLIENT c ON p.id_client = c.id 
    JOIN UTILISATEUR u ON c.id_utilisateur = u.id_utilisateur
    WHERE p.id_projet = ?
");
$stmt->execute([$id_projet]);

if ($stmt->rowCount() === 0) {
    setFlashMessage('danger', 'Projet non trouvé.');
    redirect('projets.php');
}

$projet = $stmt->fetch(PDO::FETCH_ASSOC);

$is_owner = isClient() && $projet['client_user_id'] == getUserId();
$can_propose = isFreelancer() && $projet['statut'] === 'ouvert';

$propositions = [];
$my_proposition = null;
$freelancer_id = null;

if (isFreelancer()) {
    $freelancer_id = getFreelancerId($conn, getUserId());
    
    if ($freelancer_id) {
        $stmt = $conn->prepare("SELECT * FROM PROPOSITION WHERE id_projet = ? AND id_freelancer = ?");
        $stmt->execute([$id_projet, $freelancer_id]);
        $my_proposition = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($my_proposition) {
            $can_propose = false;
        }
    }
}

if ($is_owner) {
    $stmt = $conn->prepare("
        SELECT p.*, f.id as freelancer_id, f.competences, f.tarif_horaire,
               u.id_utilisateur as freelancer_user_id, u.nom, u.prenom, u.photo_profile, u.email,
               (SELECT AVG(note) FROM EVALUATION WHERE id_freelancer = f.id) as note_moyenne,
               (SELECT COUNT(*) FROM EVALUATION WHERE id_freelancer = f.id) as nb_evaluations
        FROM PROPOSITION p
        JOIN FREELANCER f ON p.id_freelancer = f.id
        JOIN UTILISATEUR u ON f.id_utilisateur = u.id_utilisateur
        WHERE p.id_projet = ?
        ORDER BY p.date_proposition DESC
    ");
    $stmt->execute([$id_projet]);
    $propositions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    


$embauche = null;
if ($projet['statut'] === 'en_cours' || $projet['statut'] === 'terminé') {
    $stmt = $conn->prepare("
        SELECT e.*, f.id as freelancer_id, u.id_utilisateur as freelancer_user_id, u.nom, u.prenom, u.photo_profile
        FROM EMBAUCHE e
        JOIN FREELANCER f ON e.id_freelancer = f.id
        JOIN UTILISATEUR u ON f.id_utilisateur = u.id_utilisateur
        WHERE e.id_projet = ?
    ");
    $stmt->execute([$id_projet]);
    $embauche = $stmt->fetch(PDO::FETCH_ASSOC);
}

$show_proposition_form = $can_propose && isset($_GET['action']) && $_GET['action'] === 'proposer';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="projets.php">Projets</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($projet['titre']); ?></li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h1 class="h3 mb-0"><?php echo htmlspecialchars($projet['titre']); ?></h1>
                    <span class="badge bg-<?php 
                        echo $projet['statut'] === 'ouvert' ? 'success' : 
                            ($projet['statut'] === 'en_cours' ? 'info' : 
                                ($projet['statut'] === 'terminé' ? 'primary' : 'danger')); 
                    ?> fs-6">
                        <?php echo ucfirst($projet['statut']); ?>
                    </span>
                </div>
                
                <p class="text-muted mb-4">
                    <i class="fas fa-calendar me-1"></i> Publié le <?php echo formatDate($projet['date_crea']); ?>
                    <?php if (!empty($projet['date_limite'])): ?>
                    <span class="mx-2">•</span>
                    <i class="fas fa-clock me-1"></i> Échéance: <?php echo formatDate($projet['date_limite'], 'd/m/Y'); ?>
                    <?php endif; ?>
                    <span class="mx-2">•</span>
                    <i class="fas fa-tag me-1"></i> <?php echo !empty($projet['categorie']) ? htmlspecialchars($projet['categorie']) : 'Non catégorisé'; ?>
                </p>
                
                <h5>Description</h5>
                <div class="mb-4">
                    <?php echo nl2br(htmlspecialchars($projet['description'])); ?>
                </div>
                
                <?php if ($is_owner && $projet['statut'] === 'ouvert'): ?>
                <div class="d-flex gap-2 mt-4">
                    <a href="modifier-projet.php?id=<?php echo $id_projet; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProjectModal">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($show_proposition_form): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h2 class="h5 mb-0">Faire une proposition</h2>
            </div>
            <div class="card-body">
                <form id="propositionForm" action="../api/propositions.php?action=creer" method="post">
                    <input type="hidden" name="id_projet" value="<?php echo $id_projet; ?>">
                    
                    <div class="mb-3">
                        <label for="prix_souhaité" class="form-label">Prix souhaité (UM-N) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="prix_souhaité" name="prix_souhaité" min="1" step="0.01" required>
                            <span class="input-group-text">UM-N</span>
                        </div>
                        <div class="form-text">Budget du client: <?php echo number_format($projet['budget'], 2); ?> UM-N</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="delai" class="form-label">Délai de livraison (jours) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="delai" name="delai" min="1" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="message" class="form-label">Message au client <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                        <div class="form-text">
                            Expliquez pourquoi vous êtes qualifié pour ce projet, votre approche, et tout autre détail pertinent.
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="projet-details.php?id=<?php echo $id_projet; ?>" class="btn btn-outline-secondary">Annuler</a>
                        <button type="submit" class="btn btn-primary">Envoyer ma proposition</button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($my_proposition): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Ma proposition</h2>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="mb-0"><strong>Prix proposé:</strong> <?php echo number_format($my_proposition['prix_souhaité'], 2); ?> UM-N</p>
                        <p class="mb-0"><strong>Délai:</strong> <?php echo $my_proposition['delai']; ?> jours</p>
                    </div>
                    <span class="badge bg-<?php 
                        echo $my_proposition['statut'] === 'en_attente' ? 'warning' : 
                            ($my_proposition['statut'] === 'acceptée' ? 'success' : 'danger'); 
                    ?> fs-6">
                        <?php echo ucfirst($my_proposition['statut']); ?>
                    </span>
                </div>
                
                <div class="mb-3">
                    <h6>Mon message:</h6>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($my_proposition['message'])); ?></p>
                </div>
                
                <p class="text-muted small">Proposition envoyée le <?php echo formatDate($my_proposition['date_proposition']); ?></p>
            </div>
        </div>
        <?php elseif ($can_propose): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-5">
                <h2 class="h5 mb-3">Intéressé par ce projet ?</h2>
                <p class="mb-4">Faites une proposition au client pour montrer votre intérêt et vos compétences.</p>
                <a href="?id=<?php echo $id_projet; ?>&action=proposer" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i> Faire une proposition
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($is_owner && !empty($propositions)): ?>
        <div class="card border-0 shadow-sm mb-4" id="propositions">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Propositions reçues (<?php echo count($propositions); ?>)</h2>
            </div>
            <div class="card-body p-0">
                <?php foreach ($propositions as $proposition): ?>
                <div class="proposition p-4 border-bottom" id="proposition-<?php echo $proposition['id_proposition']; ?>">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <img src="../assets/img/<?php echo !empty($proposition['photo_profile']) ? htmlspecialchars($proposition['photo_profile']) : 'default.jpg'; ?>" 
                                 alt="Photo de profil" class="rounded-circle mb-2" width="80" height="80">
                            <h6><?php echo htmlspecialchars($proposition['prenom'] . ' ' . $proposition['nom']); ?></h6>
                            <?php if (!empty($proposition['competences'])): ?>
                            <p class="small text-muted mb-0"><?php echo htmlspecialchars($proposition['competences']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($proposition['note_moyenne'])): ?>
                            <div class="mb-1">
                                <?php
                                $note = round($proposition['note_moyenne']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo '<i class="' . ($i <= $note ? 'fas' : 'far') . ' fa-star text-warning"></i>';
                                }
                                ?>
                                <span class="ms-1">(<?php echo $proposition['nb_evaluations']; ?>)</span>
                            </div>
                            <?php endif; ?>
                            
                            <a href="profil-freelance.php?id=<?php echo $proposition['freelancer_user_id']; ?>" class="btn btn-sm btn-outline-secondary mt-2">Voir profil</a>
                        </div>
                        <div class="col-md-9">
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <h6 class="mb-0">Prix proposé</h6>
                                    <p class="fs-5 fw-bold text-primary mb-0"><?php echo number_format($proposition['prix_souhaité'], 2); ?> UM-N</p>
                                    <small class="text-muted">Budget: <?php echo number_format($projet['budget'], 2); ?> UM-N</small>
                                </div>
                                <div>
                                    <h6 class="mb-0">Délai proposé</h6>
                                    <p class="fs-5 fw-bold text-primary mb-0">
    <?php echo isset($proposition['delai']) ? htmlspecialchars($proposition['delai']) . ' jours' : 'Non précisé'; ?>
</p>
                                </div>
                                <div>
                                    <span class="badge bg-<?php 
                                        echo $proposition['statut'] === 'en_attente' ? 'warning' : 
                                            ($proposition['statut'] === 'acceptée' ? 'success' : 'danger'); 
                                    ?> fs-6">
                                        <?php echo ucfirst($proposition['statut']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <h6>Message du freelance:</h6>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($proposition['message'])); ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Proposition reçue le <?php echo formatDate($proposition['date_proposition']); ?></small>
                                
                                <?php if ($projet['statut'] === 'ouvert' && $proposition['statut'] === 'en_attente'): ?>
                               
                                <?php elseif ($proposition['statut'] === 'acceptée'): ?>
                                <a href="messages.php?action=chat&freelancer_id=<?php echo $proposition['freelancer_user_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-envelope me-1"></i> Contacter le freelance
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($embauche && ($is_owner || (isFreelancer() && $freelancer_id == $embauche['freelancer_id']))): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Détails de l'embauche</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Freelance:</strong> <?php echo htmlspecialchars($embauche['prenom'] . ' ' . $embauche['nom']); ?></p>
                        <p><strong>Date d'embauche:</strong> <?php echo formatDate($embauche['date_embauche'], 'd/m/Y'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($embauche['date_fin'])): ?>
                        <p><strong>Date de fin:</strong> <?php echo formatDate($embauche['date_fin'], 'd/m/Y'); ?></p>
                        <p><strong>Montant final:</strong> <?php echo number_format($embauche['montant_final'], 2); ?> UM-N</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($is_owner && $projet['statut'] === 'en_cours'): ?>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#terminerProjetModal">
                        <i class="fas fa-check-circle me-1"></i> Marquer comme terminé
                    </button>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="messages.php?action=chat&<?php echo $is_owner ? 'freelancer_id=' . $embauche['freelancer_user_id'] : 'client_id=' . $projet['client_user_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-envelope me-1"></i> Messages
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Détails du projet</h2>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Budget</span>
                        <span class="fw-bold"><?php echo number_format($projet['budget'], 2); ?> UM-N</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Statut</span>
                        <span class="badge bg-<?php 
                            echo $projet['statut'] === 'ouvert' ? 'success' : 
                                ($projet['statut'] === 'en_cours' ? 'info' : 
                                    ($projet['statut'] === 'terminé' ? 'primary' : 'danger')); 
                        ?>">
                            <?php echo ucfirst($projet['statut']); ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Date de publication</span>
                        <span><?php echo formatDate($projet['date_crea'], 'd/m/Y'); ?></span>
                    </li>
                    <?php if (!empty($projet['date_limite'])): ?>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Date limite</span>
                        <span><?php echo formatDate($projet['date_limite'], 'd/m/Y'); ?></span>
                    </li>
                    <?php endif; ?>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Propositions</span>
                        <span><?php echo $projet['nb_propositions']; ?></span>
                    </li>
                </ul>
            </div>
        </div>
        
                        
    </div>
</div>


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


<?php if ($is_owner && $projet['statut'] === 'en_cours' && $embauche): ?>
<div class="modal fade" id="terminerProjetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>
                    Terminer le projet
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="terminerProjetForm" action="../api/projets.php?action=terminer_projet" method="post">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_projet" value="<?php echo $id_projet; ?>">
                    <input type="hidden" name="id_embauche" value="<?php echo $embauche['id_embauche']; ?>">
                    
                    <div class="text-center mb-4">
                        <div class="display-1 text-primary mb-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4>Terminer le projet avec <?php echo htmlspecialchars($embauche['prenom'] . ' ' . $embauche['nom']); ?></h4>
                    </div>
                    
                    <div class="mb-3">
                        <label for="montant_final" class="form-label">Montant final (UM-N) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="montant_final" name="montant_final" min="1" step="0.01" value="<?php echo $projet['budget']; ?>" required>
                            <span class="input-group-text">UM-N</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note" class="form-label">Évaluation du freelance <span class="text-danger">*</span></label>
                        <div class="rating">
                            <input type="radio" id="star5" name="note" value="5" required><label for="star5"></label>
                            <input type="radio" id="star4" name="note" value="4" required><label for="star4"></label>
                            <input type="radio" id="star3" name="note" value="3" required><label for="star3"></label>
                            <input type="radio" id="star2" name="note" value="2" required><label for="star2"></label>
                            <input type="radio" id="star1" name="note" value="1" required><label for="star1"></label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <p class="mb-0">En terminant ce projet, vous confirmez que le travail a été livré et que vous êtes satisfait du résultat.</p>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 justify-content-center">
                    <button type="button" class="btn btn-lg btn-outline-secondary px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-lg btn-primary px-4">
                        <i class="fas fa-check me-2"></i>Terminer le projet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<?php if ($is_owner && $projet['statut'] === 'ouvert'): ?>
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
                    <input type="hidden" name="id_projet" value="<?php echo $id_projet; ?>">
                    
                    <div class="text-center mb-4">
                        <div class="display-1 text-danger mb-3">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <h4>Êtes-vous sûr de vouloir supprimer ce projet ?</h4>
                        <p class="lead fw-bold text-danger"><?php echo htmlspecialchars($projet['titre']); ?></p>
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
<?php endif; ?>

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
    <?php if ($show_proposition_form): ?>
    const propositionForm = document.getElementById('propositionForm');
    if (propositionForm) {
        propositionForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            if (!propositionForm.checkValidity()) {
                propositionForm.reportValidity();
                return;
            }
            
            const formData = new FormData(propositionForm);
            
            fetch('../api/propositions.php?action=creer', {
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
                    propositionForm.parentNode.insertBefore(alertDiv, propositionForm);
                    
                    setTimeout(() => {
                        window.location.href = data.redirect || 'projet-details.php?id=<?php echo $id_projet; ?>';
                    }, 1500);
                } else {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    propositionForm.parentNode.insertBefore(alertDiv, propositionForm);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    Une erreur est survenue lors de l'envoi de la proposition. Veuillez réessayer.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                propositionForm.parentNode.insertBefore(alertDiv, propositionForm);
            });
        });
    }
    <?php endif; ?>
    
    const accepterPropositionForm = document.getElementById('accepterPropositionForm');
    if (accepterPropositionForm) {
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
    }
    
    const refuserPropositionForm = document.getElementById('refuserPropositionForm');
    if (refuserPropositionForm) {
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
    }
    
    const terminerProjetForm = document.getElementById('terminerProjetForm');
    if (terminerProjetForm) {
        terminerProjetForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            if (!terminerProjetForm.checkValidity()) {
                terminerProjetForm.reportValidity();
                return;
            }
            
            const formData = new FormData(terminerProjetForm);
            
            fetch('../api/projets.php?action=terminer_projet', {
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
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('terminerProjetModal'));
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
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('terminerProjetModal'));
                    modal.hide();
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    Une erreur est survenue lors de la finalisation du projet. Veuillez réessayer.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('terminerProjetModal'));
                modal.hide();
            });
        });
    }
    
    const deleteProjectForm = document.getElementById('deleteProjectForm');
    if (deleteProjectForm) {
        deleteProjectForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const formData = new FormData(deleteProjectForm);
            
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
    }
});
</script>

<style>
.proposition:last-child {
    border-bottom: none !important;
}

.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}

.rating input {
    display: none;
}

.rating label {
    cursor: pointer;
    width: 30px;
    height: 30px;
    margin: 0;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='30' height='30' viewBox='0 0 24 24'%3e%3cpath d='M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z' fill='%23e4e5e9'/%3e%3c/svg%3e");
}

.rating label:hover,
.rating label:hover ~ label,
.rating input:checked ~ label {
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='30' height='30' viewBox='0 0 24 24'%3e%3cpath d='M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z' fill='%23ffc107'/%3e%3c/svg%3e");
}
</style>

<?php require_once '../includes/footer.php'; ?>
