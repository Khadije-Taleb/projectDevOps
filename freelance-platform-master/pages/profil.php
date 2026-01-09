<?php
require_once '../includes/header.php';

if (!isLoggedIn()) {
    setFlashMessage('danger', 'Vous devez être connecté pour accéder à votre profil.');
    redirect('../index.php');
}

$database = new Database();
$conn = $database->getConnection();

$user_info = [];
$profile_info = [];


$stmt = $conn->prepare("SELECT * FROM UTILISATEUR WHERE id_utilisateur = ?");
$stmt->execute([getUserId()]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);


if (isClient()) {
    $stmt = $conn->prepare("SELECT * FROM CLIENT WHERE id_utilisateur = ?");
    $stmt->execute([getUserId()]);
    $profile_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM PROJET WHERE id_client = ?");
    $stmt->execute([$profile_info['id']]);
    $nb_projets = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM PROJET WHERE id_client = ? AND statut = 'terminé'");
    $stmt->execute([$profile_info['id']]);
    $nb_projets_termines = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT AVG(note) FROM EVALUATION WHERE id_client = ?");
    $stmt->execute([$profile_info['id']]);
    $note_moyenne = $stmt->fetchColumn();
} elseif (isFreelancer()) {
    $stmt = $conn->prepare("SELECT * FROM FREELANCER WHERE id_utilisateur = ?");
    $stmt->execute([getUserId()]);
    $profile_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    
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
    <div class="row">
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <img src="../assets/img/<?php echo !empty($user_info['photo_profile']) ? htmlspecialchars($user_info['photo_profile']) : 'default.jpg'; ?>" 
                             alt="Photo de profil" class="rounded-circle" width="150" height="150">
                    </div>
                    <h3 class="h4 mb-1"><?php echo htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']); ?></h3>
                    <p class="text-muted mb-3"><?php echo $_SESSION['role']; ?></p>
                    
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
                    
                    <div class="d-grid">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fas fa-edit me-2"></i> Modifier mon profil
                        </button>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($user_info['email']); ?></span>
                        <span><i class="fas fa-calendar me-2"></i> Inscrit le <?php echo formatDate($user_info['date_inscription'], 'd/m/Y'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Statistiques</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php if (isClient()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Projets publiés
                            <span class="badge bg-primary rounded-pill"><?php echo $nb_projets ?? 0; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Projets terminés
                            <span class="badge bg-success rounded-pill"><?php echo $nb_projets_termines ?? 0; ?></span>
                        </li>
                        <?php elseif (isFreelancer()): ?>
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
                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($user_info['prenom']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Nom</p>
                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($user_info['nom']); ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Email</p>
                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($user_info['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Dernière connexion</p>
                            <p class="mb-0 fw-bold"><?php echo formatDate($user_info['derniere_connexion'], 'd/m/Y à H:i'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isClient()): ?>
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
            <?php elseif (isFreelancer()): ?>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour modifier le profil -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>
                    Modifier mon profil
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editProfileForm" action="../api/utilisateurs.php?action=modifier_profil" method="post" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                                Informations générales
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="specific-tab" data-bs-toggle="tab" data-bs-target="#specific" type="button" role="tab" aria-controls="specific" aria-selected="false">
                                <?php echo isClient() ? 'Profil client' : 'Profil freelance'; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                                Sécurité
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="profileTabsContent">
                        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                            <div class="mb-4 text-center">
                                <img src="../assets/img/<?php echo !empty($user_info['photo_profile']) ? htmlspecialchars($user_info['photo_profile']) : 'default.jpg'; ?>" 
                                     alt="Photo de profil" class="rounded-circle mb-3" width="100" height="100" id="preview-photo">
                                <div>
                                    <label for="photo_profile" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-camera me-1"></i> Changer la photo
                                    </label>
                                    <input type="file" id="photo_profile" name="photo_profile" accept="image/*" style="display: none;">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user_info['prenom']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($user_info['nom']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_info['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="specific" role="tabpanel" aria-labelledby="specific-tab">
                            <?php if (isClient()): ?>
                            <div class="mb-3">
                                <label for="entreprise" class="form-label">Entreprise</label>
                                <input type="text" class="form-control" id="entreprise" name="entreprise" value="<?php echo htmlspecialchars($profile_info['entreprise'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="site_web" class="form-label">Site web</label>
                                <input type="url" class="form-control" id="site_web" name="site_web" value="<?php echo htmlspecialchars($profile_info['site_web'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($profile_info['description'] ?? ''); ?></textarea>
                                <div class="form-text">Décrivez votre entreprise ou vos activités.</div>
                            </div>
                            <?php elseif (isFreelancer()): ?>
                            <div class="mb-3">
                                <label for="competences" class="form-label">Compétences</label>
                                <input type="text" class="form-control" id="competences" name="competences" value="<?php echo htmlspecialchars($profile_info['competences'] ?? ''); ?>">
                                <div class="form-text">Séparez les compétences par des virgules (ex: PHP, JavaScript, Design).</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tarif_horaire" class="form-label">Tarif horaire (UM-N)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="tarif_horaire" name="tarif_horaire" min="0" step="0.01" value="<?php echo $profile_info['tarif_horaire'] ?? ''; ?>">
                                    <span class="input-group-text">UM-N/heure</span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="disponibilite" class="form-label">Disponibilité</label>
                                <select class="form-select" id="disponibilite" name="disponibilite">
                                    <option value="disponible" <?php echo isset($profile_info['disponibilite']) && $profile_info['disponibilite'] === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                                    <option value="occupe" <?php echo isset($profile_info['disponibilite']) && $profile_info['disponibilite'] === 'occupe' ? 'selected' : ''; ?>>Occupé</option>
                                    <option value="indisponible" <?php echo isset($profile_info['disponibilite']) && $profile_info['disponibilite'] === 'indisponible' ? 'selected' : ''; ?>>Indisponible</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="experience" class="form-label">Expérience</label>
                                                                <textarea class="form-control" id="experience" name="experience" rows="4"><?php echo htmlspecialchars($profile_info['experience'] ?? ''); ?></textarea>
                                <div class="form-text">Décrivez votre expérience professionnelle et vos réalisations.</div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                            <div class="mb-3">
                                <label for="mot_de_passe_actuel" class="form-label">Mot de passe actuel</label>
                                <input type="password" class="form-control" id="mot_de_passe_actuel" name="mot_de_passe_actuel">
                                <div class="form-text">Laissez vide si vous ne souhaitez pas changer de mot de passe.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="nouveau_mot_de_passe" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe">
                                <div class="form-text">Minimum 8 caractères.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirmer_mot_de_passe" class="form-label">Confirmer le nouveau mot de passe</label>
                                <input type="password" class="form-control" id="confirmer_mot_de_passe" name="confirmer_mot_de_passe">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const photoInput = document.getElementById('photo_profile');
    const photoPreview = document.getElementById('preview-photo');
    
    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreview.src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    
    const editProfileForm = document.getElementById('editProfileForm');
    if (editProfileForm) {
        editProfileForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const nouveauMotDePasse = document.getElementById('nouveau_mot_de_passe').value;
            const confirmerMotDePasse = document.getElementById('confirmer_mot_de_passe').value;
            
            if (nouveauMotDePasse && nouveauMotDePasse !== confirmerMotDePasse) {
                alert('Les mots de passe ne correspondent pas.');
                return;
            }
            
            const formData = new FormData(this);
            
            fetch('../api/utilisateurs.php?action=modifier_profil', {
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
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
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
                    document.querySelector('.modal-body').insertBefore(alertDiv, document.querySelector('.modal-body').firstChild);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    Une erreur est survenue lors de la mise à jour du profil. Veuillez réessayer.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('.modal-body').insertBefore(alertDiv, document.querySelector('.modal-body').firstChild);
            });
        });
    }
    
});
</script>

<?php require_once '../includes/footer.php'; ?>
