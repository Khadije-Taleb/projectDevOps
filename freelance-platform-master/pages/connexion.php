<?php
require_once '../includes/header.php';

if (isLoggedIn()) {
    redirect('../index.php');
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-6 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0">Connexion</h1>
                </div>
                <div class="card-body">
                    <form id="connexionForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">Veuillez entrer votre adresse email.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="mot_de_passe" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
                            <div class="invalid-feedback">Veuillez entrer votre mot de passe.</div>
                        </div>
                        
                        
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Se connecter</button>
                        </div>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <p>Vous n'avez pas de compte ? <a href="inscription.php">Inscrivez-vous</a></p>
                        <p><a href="mot-de-passe-oublie.php">Mot de passe oublié ?</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('connexionForm');
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        
        if (!form.checkValidity()) {
            event.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(form);
        
        fetch('../api/utilisateurs.php?action=connexion', {
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
});
</script>

<?php require_once '../includes/footer.php'; ?>
