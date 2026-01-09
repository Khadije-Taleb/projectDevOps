<?php
require_once '../includes/header.php';

$type = isset($_GET['type']) && in_array($_GET['type'], ['client', 'freelancer']) ? $_GET['type'] : '';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0">Inscription</h1>
                </div>
                <div class="card-body">
                    <?php if (empty($type)): ?>
                    <div class="text-center mb-4">
                        <h2 class="h5 mb-4">Je souhaite m'inscrire en tant que :</h2>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <a href="?type=client" class="card h-100 border-0 shadow-sm text-decoration-none text-reset">
                                    <div class="card-body text-center p-4">
                                        <div class="mb-3">
                                            <i class="fas fa-briefcase fa-4x text-primary"></i>
                                        </div>
                                        <h3 class="h5">Client</h3>
                                        <p class="text-muted mb-0">Je souhaite publier des projets et trouver des freelances.</p>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="?type=freelancer" class="card h-100 border-0 shadow-sm text-decoration-none text-reset">
                                    <div class="card-body text-center p-4">
                                        <div class="mb-3">
                                            <i class="fas fa-laptop-code fa-4x text-primary"></i>
                                        </div>
                                        <h3 class="h5">Freelance</h3>
                                        <p class="text-muted mb-0">Je souhaite proposer mes services et trouver des projets.</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <form id="inscriptionForm" class="needs-validation" novalidate>
                        <input type="hidden" name="type" value="<?php echo $type; ?>">
                        
                        <div class="mb-3">
                            <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                            <div class="invalid-feedback">Veuillez entrer votre prénom.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                            <div class="invalid-feedback">Veuillez entrer votre nom.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">Veuillez entrer une adresse email valide.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mot_de_passe" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required minlength="8">
                            <div class="invalid-feedback">Le mot de passe doit contenir au moins 8 caractères.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirmer_mot_de_passe" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirmer_mot_de_passe" name="confirmer_mot_de_passe" required>
                            <div class="invalid-feedback">Les mots de passe ne correspondent pas.</div>
                        </div>
                        
                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input" id="conditions" name="conditions" required>
                            <label class="form-check-label" for="conditions">J'accepte les <a href="#">conditions d'utilisation</a> et la <a href="#">politique de confidentialité</a>.</label>
                            <div class="invalid-feedback">Vous devez accepter les conditions d'utilisation.</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">S'inscrire en tant que <?php echo $type === 'client' ? 'client' : 'freelance'; ?></button>
                        </div>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <p>Vous avez déjà un compte ? <a href="connexion.php">Connectez-vous</a></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('inscriptionForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            
            if (!form.checkValidity()) {
                event.stopPropagation();
                form.classList.add('was-validated');
                return;
            }
            
            const password = document.getElementById('mot_de_passe').value;
            const confirmPassword = document.getElementById('confirmer_mot_de_passe').value;
            
            if (password !== confirmPassword) {
                document.getElementById('confirmer_mot_de_passe').setCustomValidity('Les mots de passe ne correspondent pas.');
                form.classList.add('was-validated');
                return;
            }
            
            const formData = new FormData(form);
            
            fetch('../api/utilisateurs.php?action=inscription', {
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
                    form.parentNode.insertBefore(alertDiv, form);
                    
                    setTimeout(() => {
                        window.location.href = data.redirect || '../index.php';
                    }, 1500);
                } else {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    form.parentNode.insertBefore(alertDiv, form);
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
                form.parentNode.insertBefore(alertDiv, form);
            });
        });
        
        document.getElementById('confirmer_mot_de_passe').addEventListener('input', function() {
            const password = document.getElementById('mot_de_passe').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Les mots de passe ne correspondent pas.');
            } else {
                this.setCustomValidity('');
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
