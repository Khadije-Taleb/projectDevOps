<?php
require_once '../../includes/header.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant qu\'administrateur pour accéder à cette page.');
    redirect('../../index.php');
}

$database = new Database();
$conn = $database->getConnection();


$recherche = isset($_GET['recherche']) ? $_GET['recherche'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$par_page = 10;
$offset = ($page - 1) * $par_page;


$sql = "SELECT c.*, 
               cl.id as client_id, cl.id_utilisateur as client_user_id, 
               f.id as freelancer_id, f.id_utilisateur as freelancer_user_id,
               u_client.nom as client_nom, u_client.prenom as client_prenom,
               u_freelancer.nom as freelancer_nom, u_freelancer.prenom as freelancer_prenom,
               (SELECT COUNT(*) FROM MESSAGE WHERE id_conversation = c.id_conversation) as nb_messages,
               (SELECT MAX(date_envoi) FROM MESSAGE WHERE id_conversation = c.id_conversation) as dernier_message_date
        FROM CONVERSATION c 
        JOIN CLIENT cl ON c.id_client = cl.id 
        JOIN FREELANCER f ON c.id_freelancer = f.id
        JOIN UTILISATEUR u_client ON cl.id_utilisateur = u_client.id_utilisateur
        JOIN UTILISATEUR u_freelancer ON f.id_utilisateur = u_freelancer.id_utilisateur";

$where_clauses = [];
$params = [];

if (!empty($recherche)) {
    $where_clauses[] = "(u_client.nom LIKE ? OR u_client.prenom LIKE ? OR u_freelancer.nom LIKE ? OR u_freelancer.prenom LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}


$stmt_count = $conn->prepare("SELECT COUNT(*) FROM CONVERSATION c 
                             JOIN CLIENT cl ON c.id_client = cl.id 
                             JOIN FREELANCER f ON c.id_freelancer = f.id
                             JOIN UTILISATEUR u_client ON cl.id_utilisateur = u_client.id_utilisateur
                             JOIN UTILISATEUR u_freelancer ON f.id_utilisateur = u_freelancer.id_utilisateur" . 
                            (!empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : ""));
$stmt_count->execute($params);
$total_conversations = $stmt_count->fetchColumn();

$total_pages = ceil($total_conversations / $par_page);


$sql .= " ORDER BY c.date_derniere_activite DESC LIMIT $par_page OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestion des conversations</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Retour au tableau de bord
        </a>
    </div>
    
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-10">
                    <label for="recherche" class="form-label">Recherche</label>
                    <input type="text" class="form-control" id="recherche" name="recherche" value="<?php echo htmlspecialchars($recherche); ?>" placeholder="Nom du client ou du freelance...">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid gap-2 w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> Filtrer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Freelance</th>
                            <th>Date de création</th>
                            <th>Dernière activité</th>
                            <th>Messages</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversations as $conversation): ?>
                        <tr>
                            <td><?php echo $conversation['id_conversation']; ?></td>
                            <td>
                                <a href="utilisateur-details.php?id=<?php echo $conversation['client_user_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($conversation['client_prenom'] . ' ' . $conversation['client_nom']); ?>
                                </a>
                            </td>
                            <td>
                                <a href="utilisateur-details.php?id=<?php echo $conversation['freelancer_user_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($conversation['freelancer_prenom'] . ' ' . $conversation['freelancer_nom']); ?>
                                </a>
                            </td>
                            <td><?php echo formatDate($conversation['date_creation'], 'd/m/Y'); ?></td>
                            <td><?php echo $conversation['date_derniere_activite'] ? formatDate($conversation['date_derniere_activite'], 'd/m/Y H:i') : 'Jamais'; ?></td>
                            <td><?php echo $conversation['nb_messages']; ?></td>
                            <td>
                                <a href="conversation-details.php?id=<?php echo $conversation['id_conversation']; ?>" class="btn btn-sm btn-outline-primary" title="Voir les messages">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($conversations)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Aucune conversation trouvée.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&recherche=<?php echo urlencode($recherche); ?>">Précédent</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&recherche=<?php echo urlencode($recherche); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&recherche=<?php echo urlencode($recherche); ?>">Suivant</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
