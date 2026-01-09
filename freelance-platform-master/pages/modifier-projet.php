<?php
require_once '../includes/header.php';

if (!isLoggedIn() || !isClient()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant que client pour modifier un projet.');
    redirect('../index.php');
}

if (!isset($_GET['id'])) {
    setFlashMessage('danger', 'ID de projet non spécifié.');
    redirect('projets.php?mes_projets=1');
}

$id_projet = intval($_GET['id']);
$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("SELECT p.*, c.id_utilisateur 
                       FROM PROJET p 
                       JOIN CLIENT c ON p.id_client = c.id 
                       WHERE p.id_projet = ?");
$stmt->execute([$id_projet]);

if ($stmt->rowCount() === 0) {
    setFlashMessage('danger', 'Projet non trouvé.');
    redirect('projets.php?mes_projets=1');
}

$projet = $stmt->fetch(PDO::FETCH_ASSOC);

if ($projet['id_utilisateur'] != getUserId()) {
    setFlashMessage('danger', 'Vous n\'êtes pas autorisé à modifier ce projet.');
    redirect('projets.php?mes_projets=1');
}

if ($projet['statut'] !== 'ouvert') {
    setFlashMessage('danger', 'Ce projet ne peut plus être modifié car il n\'est plus ouvert.');
    redirect('projet-details.php?id=' . $id_projet);
}

$stmt = $conn->query("SELECT DISTINCT categorie FROM PROJET WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0">Modifier le projet</h1>
                </div>
                <div class="card-body">
                    <form id="editProjectForm" action="../api/projets.php?action=modifier" method="post">
                        <input type="hidden" name="id_projet" value="<?php echo $id_projet; ?>">
                        
                        <div class="mb-3">
                            <label for="titre" class="form-label">Titre du projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titre" name="titre" value="<?php echo htmlspecialchars($projet['titre']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description détaillée <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="6" required><?php echo htmlspecialchars($projet['description']); ?></textarea>
                            <div class="form-text">
                                Décrivez en détail ce que vous attendez, les compétences requises, les livrables, etc.
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="budget" class="form-label">Budget (UM-N) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="budget" name="budget" min="1" step="0.01" value="<?php echo $projet['budget']; ?>" required>
                                    <span class="input-group-text">UM-N</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="date_limite" class="form-label">Date limite (optionnel)</label>
                                <input type="date" class="form-control" id="date_limite" name="date_limite" value="<?php echo $projet['date_limite'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="categorie" class="form-label">Catégorie</label>
                            <select class="form-select" id="categorie" name="categorie">
                                <option value="">Sélectionnez une catégorie</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $projet['categorie'] === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                                <option value="autre" <?php echo !in_array($projet['categorie'], $categories) && !empty($projet['categorie']) ? 'selected' : ''; ?>>Autre (spécifier)</option>
                            </select>
                        </div>
                        
                        <div class="mb-4" id="autre-categorie-container" style="display: <?php echo !in_array($projet['categorie'], $categories) && !empty($projet['categorie']) ? 'block' : 'none'; ?>;">
                            <label for="autre_categorie" class="form-label">Spécifiez la catégorie</label>
                            <input type="text" class="form-control" id="autre_categorie" name="autre_categorie" value="<?php echo !in_array($projet['categorie'], $categories) && !empty($projet['categorie']) ? htmlspecialchars($projet['categorie']) : ''; ?>">
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="projet-details.php?id=<?php echo $id_projet; ?>" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
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
    
    const form = document.getElementById('editProjectForm');
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
        
        fetch('../api/projets.php?action=modifier', {
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
                    window.location.href = data.redirect || 'projet-details.php?id=<?php echo $id_projet; ?>';
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
                Une erreur est survenue lors de la modification du projet. Veuillez réessayer.
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
