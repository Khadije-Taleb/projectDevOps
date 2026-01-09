<?php
require_once '../../includes/header.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant qu\'administrateur pour accéder à cette page.');
    redirect('../../index.php');
}

$database = new Database();
$conn = $database->getConnection();


$role = isset($_GET['role']) ? intval($_GET['role']) : 0;
$statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$recherche = isset($_GET['recherche']) ? $_GET['recherche'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$par_page = 10;
$offset = ($page - 1) * $par_page;


$sql = "SELECT u.*, r.nom as role_nom 
        FROM UTILISATEUR u 
        JOIN ROLE r ON u.id_role = r.id_role";

$where_clauses = [];
$params = [];

if ($role > 0) {
    $where_clauses[] = "u.id_role = ?";
    $params[] = $role;
}

if (!empty($statut)) {
    $where_clauses[] = "u.statut = ?";
    $params[] = $statut;
}

if (!empty($recherche)) {
    $where_clauses[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY u.date_inscription DESC";


$stmt_count = $conn->prepare("SELECT COUNT(*) FROM UTILISATEUR u JOIN ROLE r ON u.id_role = r.id_role" . 
                            (!empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : ""));
$stmt_count->execute($params);
$total_utilisateurs = $stmt_count->fetchColumn();

$total_pages = ceil($total_utilisateurs / $par_page);


$sql .= " LIMIT $par_page OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $conn->query("SELECT * FROM ROLE ORDER BY id_role");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestion des utilisateurs</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Retour au tableau de bord
        </a>
    </div>
    
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="recherche" class="form-label">Recherche</label>
                    <input type="text" class="form-control" id="recherche" name="recherche" value="<?php echo htmlspecialchars($recherche); ?>" placeholder="Nom, prénom, email...">
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label">Rôle</label>
                    <select class="form-select" id="role" name="role">
                        <option value="0">Tous les rôles</option>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id_role']; ?>" <?php echo $role === $r['id_role'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['nom']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="statut" class="form-label">Statut</label>
                    <select class="form-select" id="statut" name="statut">
                        <option value="">Tous les statuts</option>
                        <option value="actif" <?php echo $statut === 'actif' ? 'selected' : ''; ?>>Actif</option>
                        <option value="inactif" <?php echo $statut === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                        <option value="suspendu" <?php echo $statut === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                    </select>
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
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Date d'inscription</th>
                            <th>Dernière connexion</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $utilisateur): ?>
                        <tr>
                            <td><?php echo $utilisateur['id_utilisateur']; ?></td>
                            <td><?php echo htmlspecialchars($utilisateur['prenom'] . ' ' . $utilisateur['nom']); ?></td>
                            <td><?php echo htmlspecialchars($utilisateur['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $utilisateur['role_nom'] === 'Admin' ? 'danger' : ($utilisateur['role_nom'] === 'Client' ? 'primary' : 'success'); ?>">
                                    <?php echo $utilisateur['role_nom']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $utilisateur['statut'] === 'actif' ? 'success' : ($utilisateur['statut'] === 'inactif' ? 'warning' : 'danger'); ?>">
                                    <?php echo ucfirst($utilisateur['statut']); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($utilisateur['date_inscription'], 'd/m/Y'); ?></td>
                            <td><?php echo $utilisateur['derniere_connexion'] ? formatDate($utilisateur['derniere_connexion'], 'd/m/Y H:i') : 'Jamais'; ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="utilisateur-details.php?id=<?php echo $utilisateur['id_utilisateur']; ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-<?php echo $utilisateur['statut'] === 'actif' ? 'warning' : 'success'; ?>" 
                                            onclick="changerStatut(<?php echo $utilisateur['id_utilisateur']; ?>, '<?php echo $utilisateur['statut'] === 'actif' ? 'suspendu' : 'actif'; ?>')" 
                                            title="<?php echo $utilisateur['statut'] === 'actif' ? 'Suspendre' : 'Activer'; ?>">
                                        <i class="fas fa-<?php echo $utilisateur['statut'] === 'actif' ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($utilisateurs)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Aucun utilisateur trouvé.</td>
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
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&role=<?php echo $role; ?>&statut=<?php echo $statut; ?>&recherche=<?php echo urlencode($recherche); ?>">Précédent</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&role=<?php echo $role; ?>&statut=<?php echo $statut; ?>&recherche=<?php echo urlencode($recherche); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&role=<?php echo $role; ?>&statut=<?php echo $statut; ?>&recherche=<?php echo urlencode($recherche); ?>">Suivant</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
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
