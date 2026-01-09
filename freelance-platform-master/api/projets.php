<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$response = ['success' => false, 'message' => 'Action non spécifiée.'];

switch ($action) {
    case 'creer':
        if (!isLoggedIn() || !isClient()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté en tant que client pour créer un projet.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $titre = sanitize($_POST['titre'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $budget = floatval($_POST['budget'] ?? 0);
            $categorie = sanitize($_POST['categorie'] ?? '');
            $date_limite = !empty($_POST['date_limite']) ? $_POST['date_limite'] : null;
            
            
            if (empty($titre) || empty($description) || $budget <= 0) {
                $response = ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'];
                break;
            }
            
            try {
                
                $client_id = getClientId($conn, getUserId());
                if (!$client_id) {
                    $response = ['success' => false, 'message' => 'Impossible de récupérer votre profil client. Veuillez contacter l\'administrateur.'];
                    break;
                }
                
                
                $stmt = $conn->prepare("INSERT INTO PROJET (titre, description, budget, categorie, date_crea, date_limite, id_client, statut) VALUES (?, ?, ?, ?, NOW(), ?, ?, 'ouvert')");
                $stmt->execute([$titre, $description, $budget, $categorie, $date_limite, $client_id]);
                $id_projet = $conn->lastInsertId();
                
                $response = [
                    'success' => true, 
                    'message' => 'Projet créé avec succès !',
                    'redirect' => '../pages/projet-details.php?id=' . $id_projet
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors de la création du projet: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'modifier':
        if (!isLoggedIn() || !isClient()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté en tant que client pour modifier un projet.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_projet = intval($_POST['id_projet'] ?? 0);
            $titre = sanitize($_POST['titre'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $budget = floatval($_POST['budget'] ?? 0);
            $categorie = sanitize($_POST['categorie'] ?? '');
            $date_limite = !empty($_POST['date_limite']) ? $_POST['date_limite'] : null;
            
            
            if (empty($titre) || empty($description) || $budget <= 0) {
                $response = ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'];
                break;
            }
            
            try {
                
                $stmt = $conn->prepare("SELECT p.*, c.id_utilisateur 
                                       FROM PROJET p 
                                       JOIN CLIENT c ON p.id_client = c.id 
                                       WHERE p.id_projet = ?");
                $stmt->execute([$id_projet]);
                $projet = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$projet) {
                    $response = ['success' => false, 'message' => 'Projet non trouvé.'];
                    break;
                }
                
                if ($projet['id_utilisateur'] != getUserId()) {
                    $response = ['success' => false, 'message' => 'Vous n\'êtes pas autorisé à modifier ce projet.'];
                    break;
                }
                
                if ($projet['statut'] !== 'ouvert') {
                    $response = ['success' => false, 'message' => 'Ce projet ne peut plus être modifié car il n\'est plus ouvert.'];
                    break;
                }
                
                
                $stmt = $conn->prepare("UPDATE PROJET SET titre = ?, description = ?, budget = ?, categorie = ?, date_limite = ? WHERE id_projet = ?");
                $stmt->execute([$titre, $description, $budget, $categorie, $date_limite, $id_projet]);
                
                $response = [
                    'success' => true, 
                    'message' => 'Projet modifié avec succès !',
                    'redirect' => '../pages/projet-details.php?id=' . $id_projet
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors de la modification du projet: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'supprimer':
        if (!isLoggedIn() || (!isAdmin() && !isClient())) {
            $response = ['success' => false, 'message' => 'Vous n\'êtes pas autorisé à supprimer ce projet.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_projet = intval($_POST['id_projet'] ?? 0);
            
            try {
                
                $stmt = $conn->prepare("SELECT p.*, c.id_utilisateur, c.id as id_client
                                       FROM PROJET p 
                                       JOIN CLIENT c ON p.id_client = c.id 
                                       WHERE p.id_projet = ?");
                $stmt->execute([$id_projet]);
                $projet = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$projet) {
                    $response = ['success' => false, 'message' => 'Projet non trouvé.'];
                    break;
                }
                
                if (!isAdmin() && $projet['id_utilisateur'] != getUserId()) {
                    $response = ['success' => false, 'message' => 'Vous n\'êtes pas autorisé à supprimer ce projet.'];
                    break;
                }
                
                $conn->beginTransaction();
                
                
                $stmt = $conn->prepare("SELECT id_freelancer FROM EMBAUCHE WHERE id_projet = ?");
                $stmt->execute([$id_projet]);
                $freelancers = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                
                $stmt = $conn->prepare("DELETE FROM MEDIA WHERE id_projet = ?");
                $stmt->execute([$id_projet]);
                
                
                $stmt = $conn->prepare("DELETE FROM MEDIA WHERE id_proposition IN (SELECT id_proposition FROM PROPOSITION WHERE id_projet = ?)");
                $stmt->execute([$id_projet]);
                
                
                if (!empty($freelancers)) {
                    foreach ($freelancers as $freelancer_id) {
                        
                        $stmt = $conn->prepare("SELECT id_conversation FROM CONVERSATION 
                                               WHERE id_client = ? AND id_freelancer = ?");
                        $stmt->execute([$projet['id_client'], $freelancer_id]);
                        $conversations = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        if (!empty($conversations)) {
                            $conv_placeholders = implode(',', array_fill(0, count($conversations), '?'));
                            
                            
                            $stmt = $conn->prepare("DELETE FROM MESSAGE WHERE id_conversation IN ($conv_placeholders)");
                            $stmt->execute($conversations);
                            
                            
                            $stmt = $conn->prepare("DELETE FROM CONVERSATION WHERE id_conversation IN ($conv_placeholders)");
                            $stmt->execute($conversations);
                        }
                        
                        
                        $stmt = $conn->prepare("DELETE FROM EVALUATION WHERE id_client = ? AND id_freelancer = ?");
                        $stmt->execute([$projet['id_client'], $freelancer_id]);
                    }
                }
                
                
                $stmt = $conn->prepare("DELETE FROM EMBAUCHE WHERE id_projet = ?");
                $stmt->execute([$id_projet]);
                
                
                $stmt = $conn->prepare("DELETE FROM PROPOSITION WHERE id_projet = ?");
                $stmt->execute([$id_projet]);
                
                
                $stmt = $conn->prepare("DELETE FROM PROJET WHERE id_projet = ?");
                $stmt->execute([$id_projet]);
                
                $conn->commit();
                
                $response = [
                    'success' => true, 
                    'message' => 'Projet supprimé avec succès.',
                    'redirect' => isAdmin() ? '../pages/admin/projets.php' : '../pages/projets.php?mes_projets=1'
                ];
            } catch (PDOException $e) {
                $conn->rollBack();
                $response = ['success' => false, 'message' => 'Erreur lors de la suppression du projet: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'terminer_projet':
        if (!isLoggedIn() || !isClient()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté en tant que client pour terminer un projet.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_projet = intval($_POST['id_projet'] ?? 0);
            $id_embauche = intval($_POST['id_embauche'] ?? 0);
            $montant_final = floatval($_POST['montant_final'] ?? 0);
            $note = intval($_POST['note'] ?? 0);
            $commentaire = sanitize($_POST['commentaire'] ?? '');
            
            
            if ($id_projet <= 0 || $id_embauche <= 0 || $montant_final <= 0 || $note < 1 || $note > 5) {
                $response = ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'];
                break;
            }
            
            try {
                
                $stmt = $conn->prepare("SELECT p.*, c.id_utilisateur, c.id as id_client
                                       FROM PROJET p 
                                       JOIN CLIENT c ON p.id_client = c.id 
                                       WHERE p.id_projet = ?");
                $stmt->execute([$id_projet]);
                $projet = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$projet) {
                    $response = ['success' => false, 'message' => 'Projet non trouvé.'];
                    break;
                }
                
                if ($projet['id_utilisateur'] != getUserId()) {
                    $response = ['success' => false, 'message' => 'Vous n\'êtes pas autorisé à terminer ce projet.'];
                    break;
                }
                
                if ($projet['statut'] !== 'en_cours') {
                    $response = ['success' => false, 'message' => 'Ce projet ne peut pas être terminé car il n\'est pas en cours.'];
                    break;
                }
                
                
                $stmt = $conn->prepare("SELECT * FROM EMBAUCHE WHERE id_embauche = ? AND id_projet = ?");
                $stmt->execute([$id_embauche, $id_projet]);
                $embauche = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$embauche) {
                    $response = ['success' => false, 'message' => 'Embauche non trouvée ou ne correspond pas au projet.'];
                    break;
                }
                
                $conn->beginTransaction();
                
                
                $stmt = $conn->prepare("UPDATE PROJET SET statut = 'terminé' WHERE id_projet = ?");
                $stmt->execute([$id_projet]);
                
                
                $stmt = $conn->prepare("UPDATE EMBAUCHE SET date_fin = NOW(), montant_final = ? WHERE id_embauche = ?");
                $stmt->execute([$montant_final, $id_embauche]);
                
                
                $stmt = $conn->prepare("INSERT INTO EVALUATION (id_client, id_freelancer, note, commentaire, date_evaluation) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$projet['id_client'], $embauche['id_freelancer'], $note, $commentaire]);
                
                $conn->commit();
                
                $response = [
                    'success' => true, 
                    'message' => 'Projet terminé avec succès !',
                    'redirect' => '../pages/projet-details.php?id=' . $id_projet
                ];
            } catch (PDOException $e) {
                $conn->rollBack();
                $response = ['success' => false, 'message' => 'Erreur lors de la finalisation du projet: ' . $e->getMessage()];
            }
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Action non reconnue.'];
        break;
}

echo json_encode($response);
?>
