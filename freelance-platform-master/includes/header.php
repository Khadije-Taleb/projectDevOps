<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<?php

$base_path = '';
if (strpos($_SERVER['SCRIPT_NAME'], '/pages/admin/') !== false) {
    $base_path = '../../';
} elseif (strpos($_SERVER['SCRIPT_NAME'], '/pages/') !== false) {
    $base_path = '../';
}



require_once $base_path . 'includes/functions.php';
require_once $base_path . 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

checkUserStatus();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SupFreelance - Plateforme de freelance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
  

</head>
<body <?php if (isLoggedIn()): ?>data-role="<?php echo isAdmin() ? 'admin' : (isClient() ? 'client' : 'freelancer'); ?>"<?php endif; ?>>

    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="<?php echo isLoggedIn() ? $base_path . 'pages/accueil.php' : $base_path . 'index.php'; ?>">
                    
                    SupFreelance
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <?php if (!isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>index.php">Accueil</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>pages/admin/dashboard.php">Tableau de bord</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>pages/admin/utilisateurs.php">Utilisateurs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>pages/admin/projets.php">Projets</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>pages/admin/conversations.php">Conversations</a>
                        </li>
                        <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>pages/projets.php">Projets</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isLoggedIn() && isFreelancer()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>pages/propositions.php">Mes Propositions</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isLoggedIn() && isClient()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="projectsDropdown" role="button" data-bs-toggle="dropdown">
                                Mes Projets
                                <?php
                                $client_id = getClientId($conn, getUserId());
                                if ($client_id) {
                                    $stmt = $conn->prepare("SELECT COUNT(*) FROM PROPOSITION p JOIN PROJET proj ON p.id_projet = proj.id_projet WHERE proj.id_client = ? AND p.statut = 'en_attente'");
                                    $stmt->execute([$client_id]);
                                    $count = $stmt->fetchColumn();
                                    if ($count > 0) {
                                        echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">' . $count . '</span>';
                                    }
                                }
                                ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>pages/projets.php?mes_projets=1">Tous mes projets</a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>pages/mes-propositions-recues.php">
                                    Propositions reçues
                                    <?php if (isset($count) && $count > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $count; ?></span>
                                    <?php endif; ?>
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>pages/creer-projet.php">Publier un projet</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <ul class="navbar-nav">
                        <?php if (isLoggedIn()): ?>
                        
                        <?php if (!isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="<?php echo $base_path; ?>pages/messages.php">
                                <i class="fas fa-envelope"></i> Messages
                                <?php
                                $unread_count = 0;
                                if (isClient()) {
                                    $client_id = getClientId($conn, getUserId());
                                    if ($client_id) {
                                        $stmt = $conn->prepare("
                                            SELECT COUNT(*) FROM MESSAGE m 
                                            JOIN CONVERSATION c ON m.id_conversation = c.id_conversation 
                                            WHERE c.id_client = ? AND m.id_sender != ? AND m.lu = 0
                                        ");
                                        $stmt->execute([$client_id, getUserId()]);
                                        $unread_count = $stmt->fetchColumn();
                                    }
                                } elseif (isFreelancer()) {
                                    $freelancer_id = getFreelancerId($conn, getUserId());
                                    if ($freelancer_id) {
                                        $stmt = $conn->prepare("
                                            SELECT COUNT(*) FROM MESSAGE m 
                                            JOIN CONVERSATION c ON m.id_conversation = c.id_conversation 
                                            WHERE c.id_freelancer = ? AND m.id_sender != ? AND m.lu = 0
                                        ");
                                        $stmt->execute([$freelancer_id, getUserId()]);
                                        $unread_count = $stmt->fetchColumn();
                                    }
                                }
                                if ($unread_count > 0) {
                                    echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">' . $unread_count . '</span>';
                                }
                                ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>pages/profil.php">Mon Profil</a></li>
                                <?php if (isAdmin()): ?>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>pages/admin/dashboard.php">Administration</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>pages/deconnexion.php">Déconnexion</a></li>
                            </ul>
                        </li>
                        <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>pages/connexion.php">Connexion</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light" style="color:rgba(16, 36, 102, 1) ;border: 2px solid rgba(178, 178, 179, 1) ;" href="<?php echo $base_path; ?>pages/inscription.php">Inscription</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main class="container py-4">
        <?php
        $flash = getFlashMessage();
        if ($flash): 
        ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
            <?php echo $flash['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
