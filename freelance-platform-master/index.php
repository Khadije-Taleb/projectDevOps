<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('pages/admin/dashboard.php');
    } else {
        redirect('pages/accueil.php');
    }
}

require_once 'includes/header.php';
?>

<div class="hero bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="display-4 fw-bold mb-4">Trouvez les meilleurs freelances pour vos projets</h1>
                <p class="lead mb-4">SupFreelance connecte les entreprises avec des freelances talentueux pour réaliser vos projets.</p>
                <div class="d-grid gap-3 d-md-flex justify-content-md-start">
                    <a href="pages/inscription.php?type=client" class="btn btn-light btn-lg px-4">Publier un projet</a>
                    <a href="pages/inscription.php?type=freelancer" class="btn btn-outline-light btn-lg px-4">Devenir freelance</a>
                </div>
            </div>
         
        </div>
    </div>
</div>

<div class="container py-5">
    <h2 class="text-center mb-5">Comment ça marche</h2>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-primary bg-gradient text-white mb-3">
                        <i class="fas fa-file-alt fa-2x"></i>
                    </div>
                    <h3 class="fs-4">Publiez votre projet</h3>
                    <p class="mb-0">Décrivez votre projet, fixez votre budget et recevez des propositions de freelances qualifiés.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-primary bg-gradient text-white mb-3">
                        <i class="fas fa-user-check fa-2x"></i>
                    </div>
                    <h3 class="fs-4">Choisissez le bon freelance</h3>
                    <p class="mb-0">Comparez les profils, les évaluations et les propositions pour sélectionner le meilleur freelance.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-primary bg-gradient text-white mb-3">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <h3 class="fs-4">Obtenez des résultats</h3>
                    <p class="mb-0">Collaborez efficacement et recevez un travail de qualité qui répond à vos attentes.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-light py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2 class="mb-4">Pourquoi choisir SupFreelance ?</h2>
                <ul class="list-unstyled">
                    <li class="mb-3"><i class="fas fa-check-circle text-primary me-2"></i> Des freelances qualifiés et vérifiés</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-primary me-2"></i> Paiement sécurisé et garantie de satisfaction</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-primary me-2"></i> Support client réactif</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-primary me-2"></i> Plateforme simple et intuitive</li>
                </ul>
                <a href="pages/inscription.php" class="btn btn-primary">Inscrivez-vous gratuitement</a>
            </div>
           
        </div>
    </div>
</div>

<div class="container py-5">
    <h2 class="text-center mb-5">Catégories populaires</h2>
    <div class="row g-4">
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <i class="fas fa-laptop-code fa-3x text-primary mb-3"></i>
                    <h5>Développement Web</h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <i class="fas fa-paint-brush fa-3x text-primary mb-3"></i>
                                        <h5>Design Graphique</h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <i class="fas fa-bullhorn fa-3x text-primary mb-3"></i>
                    <h5>Marketing Digital</h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <i class="fas fa-language fa-3x text-primary mb-3"></i>
                    <h5>Traduction</h5>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-primary text-white py-5">
    <div class="container text-center">
        <h2 class="mb-4">Prêt à commencer ?</h2>
        <p class="lead mb-4">Rejoignez notre communauté de freelances et d'entreprises dès aujourd'hui.</p>
        <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
            <a href="pages/inscription.php?type=client" class="btn btn-light btn-lg px-4">Je suis un client</a>
            <a href="pages/inscription.php?type=freelancer" class="btn btn-outline-light btn-lg px-4">Je suis un freelance</a>
        </div>
    </div>
</div>

<style>
.feature-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 70px;
    height: 70px;
    border-radius: 50%;
}
</style>

<?php require_once 'includes/footer.php'; ?>
