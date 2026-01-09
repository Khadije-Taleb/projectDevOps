<?php
require_once '../includes/header.php';

if (!isLoggedIn() || !isClient()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant que client pour créer un projet.');
    redirect('../index.php');
}

$database = new Database();
$conn = $database->getConnection();

$client_id = getClientId($conn, getUserId());
if (!$client_id) {
    setFlashMessage('danger', 'Impossible de récupérer votre profil client. Veuillez contacter l\'administrateur.');
    redirect('../index.php');
}

$stmt = $conn->query("SELECT DISTINCT categorie FROM PROJET WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$retour_url = isset($_GET['retour']) && $_GET['retour'] === 'mes_projets' 
    ? 'projets.php?mes_projets=1' 
    : 'projets.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0">Publier un nouveau projet</h1>
                </div>
                <div class="card-body">
                    <form id="createProjectForm" action="../api/projets.php?action=creer" method="post">
                        <div class="mb-3">
                            <label for="titre" class="form-label">Titre du projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titre" name="titre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description détaillée <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="6" required></textarea>
                            <div class="form-text">
                                Décrivez en détail ce que vous attendez, les compétences requises, les livrables, etc.
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="budget" class="form-label">Budget (UM-N) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="budget" name="budget" min="1" step="0.01" required>
                                    <span class="input-group-text">UM-N</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="date_limite" class="form-label">Date limite (optionnel)</label>
                                <input type="date" class="form-control" id="date_limite" name="date_limite">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="categorie" class="form-label">Catégorie</label>
                            <select class="form-select" id="categorie" name="categorie">
                                <option value="">Sélectionnez une catégorie</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                                <option value="autre">Autre (spécifier)</option>
                            </select>
                        </div>
                        
                        <div class="mb-4" id="autre-categorie-container" style="display: none;">
                            <label for="autre_categorie" class="form-label">Spécifiez la catégorie</label>
                            <input type="text" class="form-control" id="autre_categorie" name="autre_categorie">
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo $retour_url; ?>" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">Publier le projet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categorieSelect = document.getElementById('categorie');
    const autreCategorieContainer = document.getElementById('autre-categorie-container');
    const autreCategorie = document.getElementById('autre_categorie');
    
    categorieSelect.addEventListener('change', function() {
        if (this.value === 'autre') {
            autreCategorieContainer.style.display = 'block';
            autreCategorie.setAttribute('required', 'required');
        } else {
            autreCategorieContainer.style.display = 'none';
            autreCategorie.removeAttribute('required');
        }
    });
    
    const form = document.getElementById('createProjectForm');
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const formData = new FormData(form);
        
        if (categorieSelect.value === 'autre' && autreCategorie.value.trim() !== '') {
            formData.set('categorie', autreCategorie.value.trim());
        }
        
        fetch('../api/projets.php?action=creer', {
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
                    window.location.href = data.redirect || '<?php echo $retour_url; ?>';
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
                Une erreur est survenue lors de la création du projet. Veuillez réessayer.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            form.parentNode.insertBefore(alertDiv, form);
        });
    });
});
</script>

<style>
.form-label .text-danger {
    font-weight: bold;
}
</style>

<?php require_once '../includes/footer.php'; ?>
