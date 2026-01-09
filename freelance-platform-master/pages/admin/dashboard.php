<?php
require_once '../../includes/header.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant qu\'administrateur pour accéder à cette page.');
    redirect('../../index.php');
}

$database = new Database();
$conn = $database->getConnection();


$stmt = $conn->query("SELECT COUNT(*) FROM UTILISATEUR WHERE id_role = 2"); 
$nb_clients = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM UTILISATEUR WHERE id_role = 3"); 
$nb_freelancers = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM PROJET");
$nb_projets = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM PROJET WHERE statut = 'ouvert'");
$nb_projets_ouverts = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM PROJET WHERE statut = 'en_cours'");
$nb_projets_en_cours = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM PROJET WHERE statut = 'terminé'");
$nb_projets_termines = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM PROPOSITION");
$nb_propositions = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM CONVERSATION");
$nb_conversations = $stmt->fetchColumn();


$stmt = $conn->query("
    SELECT u.*, r.nom as role_nom 
    FROM UTILISATEUR u 
    JOIN ROLE r ON u.id_role = r.id_role 
    ORDER BY u.date_inscription DESC 
    LIMIT 5
");
$derniers_utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $conn->query("
    SELECT p.*, c.id as client_id, u.nom, u.prenom
    FROM PROJET p
    JOIN CLIENT c ON p.id_client = c.id
    JOIN UTILISATEUR u ON c.id_utilisateur = u.id_utilisateur
    ORDER BY p.date_crea DESC
    LIMIT 5
");
$derniers_projets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <h1 class="mb-4">Tableau de bord administrateur</h1>
    
    <div class="row mb-4">
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-primary mb-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5 class="card-title">Utilisateurs</h5>
                    <p class="card-text display-6"><?php echo $nb_clients + $nb_freelancers; ?></p>
                    <div class="d-flex justify-content-around">
                        <div>
                            <small class="text-muted">Clients</small>
                            <p class="mb-0 fw-bold"><?php echo $nb_clients; ?></p>
                        </div>
                        <div>
                            <small class="text-muted">Freelances</small>
                            <p class="mb-0 fw-bold"><?php echo $nb_freelancers; ?></p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="utilisateurs.php" class="btn btn-outline-primary btn-sm w-100">Gérer les utilisateurs</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-success mb-3">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h5 class="card-title">Projets</h5>
                    <p class="card-text display-6"><?php echo $nb_projets; ?></p>
                    <div class="d-flex justify-content-around">
                        <div>
                            <small class="text-muted">Ouverts</small>
                            <p class="mb-0 fw-bold"><?php echo $nb_projets_ouverts; ?></p>
                        </div>
                        <div>
                            <small class="text-muted">En cours</small>
                            <p class="mb-0 fw-bold"><?php echo $nb_projets_en_cours; ?></p>
                        </div>
                        <div>
                            <small class="text-muted">Terminés</small>
                            <p class="mb-0 fw-bold"><?php echo $nb_projets_termines; ?></p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="projets.php" class="btn btn-outline-success btn-sm w-100">Gérer les projets</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-info mb-3">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h5 class="card-title">Propositions</h5>
                    <p class="card-text display-6"><?php echo $nb_propositions; ?></p>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="statistiques.php" class="btn btn-outline-info btn-sm w-100">Voir les statistiques</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-warning mb-3">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h5 class="card-title">Conversations</h5>
                    <p class="card-text display-6"><?php echo $nb_conversations; ?></p>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="conversations.php" class="btn btn-outline-warning btn-sm w-100">Voir les conversations</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Derniers utilisateurs inscrits</h5>
                    <a href="utilisateurs.php" class="btn btn-sm btn-outline-primary">Voir tous</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Date d'inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($derniers_utilisateurs as $utilisateur): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($utilisateur['prenom'] . ' ' . $utilisateur['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($utilisateur['email']); ?></td>
                                    <td><span class="badge bg-<?php echo $utilisateur['role_nom'] === 'Admin' ? 'danger' : ($utilisateur['role_nom'] === 'Client' ? 'primary' : 'success'); ?>"><?php echo $utilisateur['role_nom']; ?></span></td>
                                    <td><?php echo formatDate($utilisateur['date_inscription'], 'd/m/Y'); ?></td>
                                    <td>
                                        <a href="utilisateur-details.php?id=<?php echo $utilisateur['id_utilisateur']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($derniers_utilisateurs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Aucun utilisateur trouvé.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Derniers projets</h5>
                    <a href="projets.php" class="btn btn-sm btn-outline-success">Voir tous</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Client</th>
                                    <th>Budget</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($derniers_projets as $projet): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($projet['titre']); ?></td>
                                    <td><?php echo htmlspecialchars($projet['prenom'] . ' ' . $projet['nom']); ?></td>
                                    <td><?php echo number_format($projet['budget'], 2); ?> UM-N</td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $projet['statut'] === 'ouvert' ? 'success' : 
                                                ($projet['statut'] === 'en_cours' ? 'info' : 
                                                    ($projet['statut'] === 'terminé' ? 'primary' : 'danger')); 
                                        ?>">
                                            <?php echo ucfirst($projet['statut']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../projet-details.php?id=<?php echo $projet['id_projet']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($derniers_projets)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Aucun projet trouvé.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
