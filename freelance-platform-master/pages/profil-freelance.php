<?php
require_once '../includes/header.php';

if (!isset($_GET['id'])) {
    setFlashMessage('danger', 'ID d\'utilisateur non spécifié.');
    redirect('../index.php');
}

$id_utilisateur = intval($_GET['id']);
$database = new Database();
$conn = $database->getConnection();


$stmt = $conn->prepare("SELECT u.* FROM UTILISATEUR u WHERE u.id_utilisateur = ? AND u.id_role = 3"); 
$stmt->execute([$id_utilisateur]);
$utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur) {
    setFlashMessage('danger', 'Freelance non trouvé.');
    redirect('../index.php');
}


$stmt = $conn->prepare("SELECT * FROM FREELANCER WHERE id_utilisateur = ?");
$stmt->execute([$id_utilisateur]);
$profile_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile_info) {
    setFlashMessage('danger', 'Profil freelance non trouvé.');
    redirect('../index.php');
}


$stmt = $conn->prepare("
    SELECT e.*, c.id as client_id, u.nom as client_nom, u.prenom as client_prenom, u.photo_profile as client_photo
    FROM EVALUATION e
    JOIN CLIENT c ON e.id_client = c.id
    JOIN UTILISATEUR u ON c.id_utilisateur = u.id_utilisateur
    WHERE e.id_freelancer = ?
    ORDER BY e.date_evaluation DESC
");
$stmt->execute([$profile_info['id']]);
$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $conn->prepare("SELECT AVG(note) FROM EVALUATION WHERE id_freelancer = ?");
$stmt->execute([$profile_info['id']]);
$note_moyenne = $stmt->fetchColumn();


$stmt = $conn->prepare("
    SELECT p.*, c.id as client_id, u.nom as client_nom, u.prenom as client_prenom
    FROM PROJET p
    JOIN EMBAUCHE e ON p.id_projet = e.id_projet
    JOIN CLIENT c ON p.id_client = c.id
    JOIN UTILISATEUR u ON c.id_utilisateur = u.id_utilisateur
    WHERE e.id_freelancer = ? AND p.statut = 'terminé'
    ORDER BY e.date_fin DESC
");
$stmt->execute([$profile_info['id']]);
$projets_termines = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $conn->prepare("
    SELECT COUNT(*) FROM EMBAUCHE 
    WHERE id_freelancer = ? AND date_fin IS NOT NULL
");
$stmt->execute([$profile_info['id']]);
$nb_projets_termines = $stmt->fetchColumn();


$competences = [];
if (!empty($profile_info['competences'])) {
    $competences = explode(',', $profile_info['competences']);
    $competences = array_map('trim', $competences);
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <img src="../assets/img/<?php echo !empty($utilisateur['photo_profile']) ? htmlspecialchars($utilisateur['photo_profile']) : 'default.jpg'; ?>" 
                         alt="Photo de profil" class="rounded-circle mb-3" width="150" height="150">
                    <h2 class="h4 mb-1"><?php echo htmlspecialchars($utilisateur['prenom'] . ' ' . $utilisateur['nom']); ?></h2>
                    <p class="text-muted mb-3">Freelance</p>
                    
                    <?php if ($note_moyenne): ?>
                    <div class="mb-3">
                        <?php
                        $note = round($note_moyenne);
                        for ($i = 1; $i <= 5; $i++) {
                            echo '<i class="' . ($i <= $note ? 'fas' : 'far') . ' fa-star text-warning"></i>';
                        }
                        ?>
                        <span class="ms-1"><?php echo number_format($note_moyenne, 1); ?>/5</span>
                        <span class="text-muted">(<?php echo count($evaluations); ?> avis)</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn() && isClient()): ?>
                    <div class="d-grid gap-2">
                        <a href="messages.php?action=chat&freelancer_id=<?php echo $id_utilisateur; ?>" class="btn btn-primary">
                            <i class="fas fa-envelope me-2"></i> Contacter
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-calendar me-2"></i> Inscrit le <?php echo formatDate($utilisateur['date_inscription'], 'd/m/Y'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h3 class="h5 mb-0">Informations</h3>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php if (!empty($profile_info['tarif_horaire'])): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-money-bill-wave me-2"></i> Tarif horaire</span>
                            <span class="fw-bold"><?php echo number_format($profile_info['tarif_horaire'], 2); ?> UM-N/h</span>
                        </li>
                        <?php endif; ?>
                        
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-briefcase me-2"></i> Projets terminés</span>
                            <span class="fw-bold"><?php echo $nb_projets_termines; ?></span>
                        </li>
                        
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-clock me-2"></i> Disponibilité</span>
                            <span class="badge bg-<?php 
                                echo $profile_info['disponibilite'] === 'disponible' ? 'success' : 
                                    ($profile_info['disponibilite'] === 'occupe' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($profile_info['disponibilite']); ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <?php if (!empty($competences)): ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h3 class="h5 mb-0">Compétences</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($competences as $competence): ?>
                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($competence); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-8">
            <?php if (!empty($profile_info['experience'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h3 class="h5 mb-0">À propos</h3>
                </div>
                <div class="card-body">
                    <div class="mb-0">
                        <?php echo nl2br(htmlspecialchars($profile_info['experience'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($projets_termines)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h3 class="h5 mb-0">Projets réalisés</h3>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($projets_termines as $projet): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="h6 mb-1"><?php echo htmlspecialchars($projet['titre']); ?></h4>
                                <small class="text-muted"><?php echo formatDate($projet['date_crea'], 'd/m/Y'); ?></small>
                            </div>
                            <p class="mb-1 text-muted small">Client: <?php echo htmlspecialchars($projet['client_prenom'] . ' ' . $projet['client_nom']); ?></p>
                            <p class="mb-0 small"><?php echo truncateText($projet['description'], 150); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($evaluations)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="h5 mb-0">Évaluations (<?php echo count($evaluations); ?>)</h3>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($evaluations as $evaluation): ?>
                        <div class="list-group-item">
                            <div class="d-flex">
                                <div class="me-3">
                                    <img src="../assets/img/<?php echo !empty($evaluation['client_photo']) ? htmlspecialchars($evaluation['client_photo']) : 'default.jpg'; ?>" 
                                         alt="Photo de profil" class="rounded-circle" width="50" height="50">
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="h6 mb-0"><?php echo htmlspecialchars($evaluation['client_prenom'] . ' ' . $evaluation['client_nom']); ?></h4>
                                        <small class="text-muted"><?php echo formatDate($evaluation['date_evaluation'], 'd/m/Y'); ?></small>
                                    </div>
                                    <div class="mb-2">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo '<i class="' . ($i <= $evaluation['note'] ? 'fas' : 'far') . ' fa-star text-warning"></i>';
                                        }
                                        ?>
                                    </div>
                                    <?php if (!empty($evaluation['commentaire'])): ?>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($evaluation['commentaire'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
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
