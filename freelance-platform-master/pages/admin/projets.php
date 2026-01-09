<?php
require_once '../../includes/header.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant qu\'administrateur pour accéder à cette page.');
    redirect('../../index.php');
}

$database = new Database();
$conn = $database->getConnection();


$statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$categorie = isset($_GET['categorie']) ? $_GET['categorie'] : '';
$recherche = isset($_GET['recherche']) ? $_GET['recherche'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$par_page = 10;
$offset = ($page - 1) * $par_page;


$sql = "SELECT p.*, c.id as client_id, u.nom, u.prenom, u.photo_profile,
        (SELECT COUNT(*) FROM PROPOSITION WHERE id_projet = p.id_projet) as nb_propositions
        FROM PROJET p 
        JOIN CLIENT c ON p.id_client = c.id 
        JOIN UTILISATEUR u ON c.id_utilisateur = u.id_utilisateur";

$where_clauses = [];
$params = [];

if (!empty($statut)) {
    $where_clauses[] = "p.statut = ?";
    $params[] = $statut;
}

if (!empty($categorie)) {
    $where_clauses[] = "p.categorie = ?";
    $params[] = $categorie;
}

if (!empty($recherche)) {
    $where_clauses[] = "(p.titre LIKE ? OR p.description LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}


$stmt_count = $conn->prepare("SELECT COUNT(*) FROM PROJET p JOIN CLIENT c ON p.id_client = c.id JOIN UTILISATEUR u ON c.id_utilisateur = u.id_utilisateur" . 
                            (!empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : ""));
$stmt_count->execute($params);
$total_projets = $stmt_count->fetchColumn();

$total_pages = ceil($total_projets / $par_page);


$sql .= " ORDER BY p.date_crea DESC LIMIT $par_page OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$projets = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $conn->query("SELECT DISTINCT categorie FROM PROJET WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestion des projets</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Retour au tableau de bord
        </a>
    </div>
    
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="recherche" class="form-label">Recherche</label>
                    <input type="text" class="form-control" id="recherche" name="recherche" value="<?php echo htmlspecialchars($recherche); ?>" placeholder="Titre, description...">
                </div>
                <div class="col-md-3">
                    <label for="categorie" class="form-label">Catégorie</label>
                    <select class="form-select" id="categorie" name="categorie">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categorie === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="statut" class="form-label">Statut</label>
                    <select class="form-select" id="statut" name="statut">
                        <option value="">Tous les statuts</option>
                        <option value="ouvert" <?php echo $statut === 'ouvert' ? 'selected' : ''; ?>>Ouvert</option>
                        <option value="en_cours" <?php echo $statut === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                        <option value="terminé" <?php echo $statut === 'terminé' ? 'selected' : ''; ?>>Terminé</option>
                        <option value="annulé" <?php echo $statut === 'annulé' ? 'selected' : ''; ?>>Annulé</option>
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
                            <th>Titre</th>
                            <th>Client</th>
                            <th>Budget</th>
                            <th>Catégorie</th>
                            <th>Date de création</th>
                            <th>Statut</th>
                            <th>Propositions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projets as $projet): ?>
                        <tr>
                            <td><?php echo $projet['id_projet']; ?></td>
                            <td><?php echo htmlspecialchars($projet['titre']); ?></td>
                            <td>
                                <a href="utilisateur-details.php?id=<?php echo $projet['client_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($projet['prenom'] . ' ' . $projet['nom']); ?>
                                </a>
                            </td>
                            <td><?php echo number_format($projet['budget'], 2); ?> UM-N</td>
                            <td><?php echo !empty($projet['categorie']) ? htmlspecialchars($projet['categorie']) : 'Non catégorisé'; ?></td>
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
                                <div class="btn-group">
                                    <a href="../../pages/projet-details.php?id=<?php echo $projet['id_projet']; ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($projet['statut'] === 'ouvert'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="changerStatutProjet(<?php echo $projet['id_projet']; ?>, 'annulé')" title="Annuler le projet">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($projets)): ?>
                        <tr>
                            <td colspan="9" class="text-center">Aucun projet trouvé.</td>
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
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&statut=<?php echo $statut; ?>&categorie=<?php echo urlencode($categorie); ?>&recherche=<?php echo urlencode($recherche); ?>">Précédent</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&statut=<?php echo $statut; ?>&categorie=<?php echo urlencode($categorie); ?>&recherche=<?php echo urlencode($recherche); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&statut=<?php echo $statut; ?>&categorie=<?php echo urlencode($categorie); ?>&recherche=<?php echo urlencode($recherche); ?>">Suivant</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function changerStatutProjet(id_projet, nouveau_statut) {
    if (confirm('Êtes-vous sûr de vouloir ' + (nouveau_statut === 'annulé' ? 'annuler' : 'changer le statut de') + ' ce projet ?')) {
        fetch('../../api/admin.php?action=changer_statut_projet', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id_projet=${id_projet}&statut=${nouveau_statut}`
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
