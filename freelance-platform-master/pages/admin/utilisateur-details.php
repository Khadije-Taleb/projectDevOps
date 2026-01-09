<?php
require_once '../../includes/header.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant qu\'administrateur pour accéder à cette page.');
    redirect('../../index.php');
}

if (!isset($_GET['id'])) {
    setFlashMessage('danger', 'ID d\'utilisateur non spécifié.');
    redirect('utilisateurs.php');
}

$id_utilisateur = intval($_GET['id']);
$database = new Database();
$conn = $database->getConnection();


$stmt = $conn->prepare("SELECT u.*, r.nom as role_nom FROM UTILISATEUR u JOIN ROLE r ON u.id_role = r.id_role WHERE u.id_utilisateur = ?");
$stmt->execute([$id_utilisateur]);
$utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur) {
    setFlashMessage('danger', 'Utilisateur non trouvé.');
    redirect('utilisateurs.php');
}


$profile_info = [];
if ($utilisateur['role_nom'] === 'Client') {
    $stmt = $conn->prepare("SELECT * FROM CLIENT WHERE id_utilisateur = ?");
    $stmt->execute([$id_utilisateur]);
    $profile_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    
    $stmt = $conn->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM PROPOSITION WHERE id_projet = p.id_projet) as nb_propositions
        FROM PROJET p 
        WHERE p.id_client = ? 
        ORDER BY p.date_crea DESC
    ");
    $stmt->execute([$profile_info['id']]);
    $projets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM PROJET WHERE id_client = ?");
    $stmt->execute([$profile_info['id']]);
    $nb_projets = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM PROJET WHERE id_client = ? AND statut = 'terminé'");
    $stmt->execute([$profile_info['id']]);
    $nb_projets_termines = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT AVG(note) FROM EVALUATION WHERE id_client = ?");
    $stmt->execute([$profile_info['id']]);
    $note_moyenne = $stmt->fetchColumn();
} elseif ($utilisateur['role_nom'] === 'Freelancer') {
    $stmt = $conn->prepare("SELECT * FROM FREELANCER WHERE id_utilisateur = ?");
    $stmt->execute([$id_utilisateur]);
    $profile_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    
    $stmt = $conn->prepare("
        SELECT p.*, proj.titre as projet_titre, proj.budget as projet_budget, proj.statut as projet_statut
        FROM PROPOSITION p
        JOIN PROJET proj ON p.id_projet = proj.id_projet
        WHERE p.id_freelancer = ?
        ORDER BY p.date_proposition DESC
    ");
    $stmt->execute([$profile_info['id']]);
    $propositions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION WHERE id_freelancer = ?");
    $stmt->execute([$profile_info['id']]);
    $nb_propositions = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION WHERE id_freelancer = ? AND statut = 'acceptée'");
    $stmt->execute([$profile_info['id']]);
    $nb_propositions_acceptees = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM EMBAUCHE WHERE id_freelancer = ? AND date_fin IS NOT NULL");
    $stmt->execute([$profile_info['id']]);
    $nb_projets_termines = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT AVG(note) FROM EVALUATION WHERE id_freelancer = ?");
    $stmt->execute([$profile_info['id']]);
    $note_moyenne = $stmt->fetchColumn();
}
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Détails de l'utilisateur</h1>
        <a href="utilisateurs.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Retour à la liste
        </a>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <img src="../../assets/img/<?php echo !empty($utilisateur['photo_profile']) ? htmlspecialchars($utilisateur['photo_profile']) : 'default.jpg'; ?>" 
                             alt="Photo de profil" class="rounded-circle" width="150" height="150">
                    </div>
                    <h3 class="h4 mb-1"><?php echo htmlspecialchars($utilisateur['prenom'] . ' ' . $utilisateur['nom']); ?></h3>
                    <p class="text-muted mb-3"><?php echo $utilisateur['role_nom']; ?></p>
                    
                    <?php if (isset($note_moyenne) && $note_moyenne): ?>
                    <div class="mb-3">
                        <?php
                        $note = round($note_moyenne);
                        for ($i = 1; $i <= 5; $i++) {
                            echo '<i class="' . ($i <= $note ? 'fas' : 'far') . ' fa-star text-warning"></i>';
                        }
                        ?>
                                                <span class="ms-1"><?php echo number_format($note_moyenne, 1); ?>/5</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-<?php echo $utilisateur['statut'] === 'actif' ? 'warning' : 'success'; ?>" 
                                onclick="changerStatut(<?php echo $utilisateur['id_utilisateur']; ?>, '<?php echo $utilisateur['statut'] === 'actif' ? 'suspendu' : 'actif'; ?>')">
                            <i class="fas fa-<?php echo $utilisateur['statut'] === 'actif' ? 'ban' : 'check'; ?> me-2"></i>
                            <?php echo $utilisateur['statut'] === 'actif' ? 'Suspendre l\'utilisateur' : 'Activer l\'utilisateur'; ?>
                        </button>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($utilisateur['email']); ?></span>
                        <span><i class="fas fa-calendar me-2"></i> Inscrit le <?php echo formatDate($utilisateur['date_inscription'], 'd/m/Y'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Statistiques</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php if ($utilisateur['role_nom'] === 'Client'): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Projets publiés
                            <span class="badge bg-primary rounded-pill"><?php echo $nb_projets ?? 0; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Projets terminés
                            <span class="badge bg-success rounded-pill"><?php echo $nb_projets_termines ?? 0; ?></span>
                        </li>
                        <?php elseif ($utilisateur['role_nom'] === 'Freelancer'): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Propositions envoyées
                            <span class="badge bg-primary rounded-pill"><?php echo $nb_propositions ?? 0; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Propositions acceptées
                            <span class="badge bg-success rounded-pill"><?php echo $nb_propositions_acceptees ?? 0; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Projets terminés
                            <span class="badge bg-info rounded-pill"><?php echo $nb_projets_termines ?? 0; ?></span>
                        </li>
                        <?php endif; ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Dernière connexion
                            <span class="badge bg-secondary"><?php echo $utilisateur['derniere_connexion'] ? formatDate($utilisateur['derniere_connexion'], 'd/m/Y H:i') : 'Jamais'; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Informations personnelles</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Prénom</p>
                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($utilisateur['prenom']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Nom</p>
                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($utilisateur['nom']); ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Email</p>
                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($utilisateur['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Statut</p>
                            <p class="mb-0">
                                <span class="badge bg-<?php echo $utilisateur['statut'] === 'actif' ? 'success' : ($utilisateur['statut'] === 'inactif' ? 'warning' : 'danger'); ?>">
                                    <?php echo ucfirst($utilisateur['statut']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($utilisateur['role_nom'] === 'Client' && !empty($profile_info)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Profil client</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Entreprise</p>
                            <p class="mb-0 fw-bold"><?php echo !empty($profile_info['entreprise']) ? htmlspecialchars($profile_info['entreprise']) : 'Non spécifié'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Site web</p>
                            <p class="mb-0 fw-bold">
                                <?php if (!empty($profile_info['site_web'])): ?>
                                <a href="<?php echo htmlspecialchars($profile_info['site_web']); ?>" target="_blank"><?php echo htmlspecialchars($profile_info['site_web']); ?></a>
                                <?php else: ?>
                                Non spécifié
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <p class="mb-1 text-muted small">Description</p>
                            <p class="mb-0"><?php echo !empty($profile_info['description']) ? nl2br(htmlspecialchars($profile_info['description'])) : 'Aucune description fournie.'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Projets publiés</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($projets)): ?>
                    <div class="p-4 text-center">
                        <p class="text-muted mb-0">Cet utilisateur n'a pas encore publié de projet.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Budget</th>
                                    <th>Date de création</th>
                                    <th>Statut</th>
                                    <th>Propositions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projets as $projet): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($projet['titre']); ?></td>
                                    <td><?php echo number_format($projet['budget'], 2); ?> UM-N</td>
                                    <td><?php echo formatDate($projet['date_crea'], 'd/m/Y'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $projet['statut'] === 'ouvert' ? 'success' : 
                                                ($projet['statut'] === 'en_cours' ? 'info' : 
                                                    ($projet['statut'] === 'terminé' ? 'primary' : 'danger')); 
                                        ?>">
                                            <?php echo ucfirst($projet['statut']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $projet['nb_propositions']; ?></td>
                                    <td>
                                        <a href="../../pages/projet-details.php?id=<?php echo $projet['id_projet']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($utilisateur['role_nom'] === 'Freelancer' && !empty($profile_info)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Profil freelance</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Compétences</p>
                            <p class="mb-0 fw-bold"><?php echo !empty($profile_info['competences']) ? htmlspecialchars($profile_info['competences']) : 'Non spécifié'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Tarif horaire</p>
                            <p class="mb-0 fw-bold"><?php echo !empty($profile_info['tarif_horaire']) ? number_format($profile_info['tarif_horaire'], 2) . ' UM-N/heure' : 'Non spécifié'; ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Disponibilité</p>
                            <p class="mb-0">
                                <?php if (!empty($profile_info['disponibilite'])): ?>
                                <span class="badge bg-<?php 
                                    echo $profile_info['disponibilite'] === 'disponible' ? 'success' : 
                                        ($profile_info['disponibilite'] === 'occupe' ? 'warning' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($profile_info['disponibilite']); ?>
                                </span>
                                <?php else: ?>
                                Non spécifié
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <p class="mb-1 text-muted small">Expérience</p>
                            <p class="mb-0"><?php echo !empty($profile_info['experience']) ? nl2br(htmlspecialchars($profile_info['experience'])) : 'Aucune expérience fournie.'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Propositions envoyées</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($propositions)): ?>
                    <div class="p-4 text-center">
                        <p class="text-muted mb-0">Cet utilisateur n'a pas encore envoyé de proposition.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Projet</th>
                                    <th>Prix proposé</th>
                                    <th>Date de proposition</th>
                                    <th>Statut proposition</th>
                                    <th>Statut projet</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($propositions as $proposition): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($proposition['projet_titre']); ?></td>
                                    <td><?php echo number_format($proposition['prix_souhaité'], 2); ?> UM-N</td>
                                    <td><?php echo formatDate($proposition['date_proposition'], 'd/m/Y'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $proposition['statut'] === 'en_attente' ? 'warning' : 
                                                ($proposition['statut'] === 'acceptée' ? 'success' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($proposition['statut']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $proposition['projet_statut'] === 'ouvert' ? 'success' : 
                                                ($proposition['projet_statut'] === 'en_cours' ? 'info' : 
                                                    ($proposition['projet_statut'] === 'terminé' ? 'primary' : 'danger')); 
                                        ?>">
                                            <?php echo ucfirst($proposition['projet_statut']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../../pages/projet-details.php?id=<?php echo $proposition['id_projet']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function changerStatut(id_utilisateur, nouveau_statut) {
    if (confirm('Êtes-vous sûr de vouloir ' + (nouveau_statut === 'actif' ? 'activer' : 'suspendre') + ' cet utilisateur ?')) {
        fetch('../../api/admin.php?action=changer_statut_utilisateur', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id_utilisateur=${id_utilisateur}&statut=${nouveau_statut}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('danger', 'Une erreur est survenue. Veuillez réessayer.');
        });
    }
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.querySelector('main.container').insertBefore(alertDiv, document.querySelector('main.container').firstChild);
}
</script>

<?php require_once '../../includes/footer.php'; ?>
