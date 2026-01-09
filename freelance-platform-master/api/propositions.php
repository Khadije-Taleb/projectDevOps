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
        if (!isLoggedIn() || !isFreelancer()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté en tant que freelance pour faire une proposition.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_projet = intval($_POST['id_projet'] ?? 0);
            $prix_souhaite = floatval($_POST['prix_souhaité'] ?? 0);
            $message = sanitize($_POST['message'] ?? '');
            $delai = intval($_POST['delai'] ?? 0);
            
            
            if ($id_projet <= 0 || $prix_souhaite <= 0 || empty($message) || $delai <= 0) {
                $response = ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'];
                break;
            }
            
            try {
                
                $stmt = $conn->prepare("SELECT id_projet, statut FROM PROJET WHERE id_projet = ?");
                $stmt->execute([$id_projet]);
                $projet = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$projet) {
                    $response = ['success' => false, 'message' => 'Projet non trouvé.'];
                    break;
                }
                
                if ($projet['statut'] !== 'ouvert') {
                    $response = ['success' => false, 'message' => 'Ce projet n\'est plus ouvert aux propositions.'];
                    break;
                }
                
                
                $freelancer_id = getFreelancerId($conn, getUserId());
                if (!$freelancer_id) {
                    $response = ['success' => false, 'message' => 'Impossible de récupérer votre profil freelance. Veuillez contacter l\'administrateur.'];
                    break;
                }
                
                
                $stmt = $conn->prepare("SELECT id_proposition FROM PROPOSITION WHERE id_freelancer = ? AND id_projet = ?");
                $stmt->execute([$freelancer_id, $id_projet]);
                if ($stmt->rowCount() > 0) {
                    $response = ['success' => false, 'message' => 'Vous avez déjà fait une proposition pour ce projet.'];
                    break;
                }
                
                
                $stmt = $conn->prepare("INSERT INTO PROPOSITION (id_freelancer, id_projet, prix_souhaité, message, delai, date_proposition, statut) VALUES (?, ?, ?, ?, ?, NOW(), 'en_attente')");
                $stmt->execute([$freelancer_id, $id_projet, $prix_souhaite, $message, $delai]);
                
                $response = [
                    'success' => true, 
                    'message' => 'Votre proposition a été envoyée avec succès !',
                    'redirect' => '../pages/projet-details.php?id=' . $id_projet
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors de l\'envoi de la proposition: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'accepter':
        if (!isLoggedIn() || !isClient()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté en tant que client pour accepter une proposition.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_proposition = intval($_POST['id_proposition'] ?? 0);
            $id_projet = intval($_POST['id_projet'] ?? 0);
            
            
            if ($id_proposition <= 0 || $id_projet <= 0) {
                $response = ['success' => false, 'message' => 'Paramètres invalides.'];
                break;
            }
            
            try {
                
                $stmt = $conn->prepare("SELECT p.*, f.id as freelancer_id FROM PROPOSITION p JOIN FREELANCER f ON p.id_freelancer = f.id WHERE p.id_proposition = ?");
                $stmt->execute([$id_proposition]);
                $proposition = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$proposition) {
                    $response = ['success' => false, 'message' => 'Proposition non trouvée.'];
                    break;
                }
                
                
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
                    $response = ['success' => false, 'message' => 'Vous n\'êtes pas autorisé à accepter cette proposition.'];
                    break;
                }
                
                if ($projet['statut'] !== 'ouvert') {
                    $response = ['success' => false, 'message' => 'Ce projet n\'est plus ouvert.'];
                    break;
                }
                
                if ($proposition['id_projet'] != $id_projet) {
                    $response = ['success' => false, 'message' => 'Cette proposition ne correspond pas au projet spécifié.'];
                    break;
                }
                
                if ($proposition['statut'] !== 'en_attente') {
                    $response = ['success' => false, 'message' => 'Cette proposition a déjà été traitée.'];
                    break;
                }
                
                $conn->beginTransaction();
                
                
                $stmt = $conn->prepare("UPDATE PROPOSITION SET statut = 'acceptée' WHERE id_proposition = ?");
                $stmt->execute([$id_proposition]);
                
                
                $stmt = $conn->prepare("UPDATE PROPOSITION SET statut = 'refusée' WHERE id_projet = ? AND id_proposition != ?");
                $stmt->execute([$id_projet, $id_proposition]);
                
                
                $stmt = $conn->prepare("UPDATE PROJET SET statut = 'en_cours' WHERE id_projet = ?");
                $stmt->execute([$id_projet]);
                
                
                $stmt = $conn->prepare("INSERT INTO EMBAUCHE (id_projet, id_freelancer, date_embauche) VALUES (?, ?, NOW())");
                $stmt->execute([$id_projet, $proposition['freelancer_id']]);
                
                
                $stmt = $conn->prepare("SELECT id_conversation FROM CONVERSATION WHERE id_client = ? AND id_freelancer = ?");
                $stmt->execute([$projet['id_client'], $proposition['freelancer_id']]);
                if ($stmt->rowCount() === 0) {
                    $stmt = $conn->prepare("INSERT INTO CONVERSATION (id_client, id_freelancer, date_creation, date_derniere_activite) VALUES (?, ?, NOW(), NOW())");
                    $stmt->execute([$projet['id_client'], $proposition['freelancer_id']]);
                }
                
                $conn->commit();
                
                $response = [
                    'success' => true, 
                    'message' => 'Proposition acceptée avec succès ! Le projet est maintenant en cours.',
                    'redirect' => '../pages/projet-details.php?id=' . $id_projet
                ];
            } catch (PDOException $e) {
                $conn->rollBack();
                $response = ['success' => false, 'message' => 'Erreur lors de l\'acceptation de la proposition: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'refuser':
        if (!isLoggedIn() || !isClient()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté en tant que client pour refuser une proposition.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_proposition = intval($_POST['id_proposition'] ?? 0);
            
            
            if ($id_proposition <= 0) {
                $response = ['success' => false, 'message' => 'Paramètres invalides.'];
                break;
            }
            
            try {
                
                $stmt = $conn->prepare("
                    SELECT p.*, proj.id_client, c.id_utilisateur 
                    FROM PROPOSITION p 
                    JOIN PROJET proj ON p.id_projet = proj.id_projet 
                    JOIN CLIENT c ON proj.id_client = c.id 
                    WHERE p.id_proposition = ?
                ");
                $stmt->execute([$id_proposition]);
                $proposition = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$proposition) {
                    $response = ['success' => false, 'message' => 'Proposition non trouvée.'];
                    break;
                }
                
                if ($proposition['id_utilisateur'] != getUserId()) {
                    $response = ['success' => false, 'message' => 'Vous n\'êtes pas autorisé à refuser cette proposition.'];
                    break;
                }
                
                if ($proposition['statut'] !== 'en_attente') {
                    $response = ['success' => false, 'message' => 'Cette proposition a déjà été traitée.'];
                    break;
                }
                
                
                $stmt = $conn->prepare("UPDATE PROPOSITION SET statut = 'refusée' WHERE id_proposition = ?");
                $stmt->execute([$id_proposition]);
                
                $response = [
                    'success' => true, 
                    'message' => 'Proposition refusée avec succès.',
                    'redirect' => '../pages/projet-details.php?id=' . $proposition['id_projet']
                ];
            } catch (PDOException $e) {
                                $response = ['success' => false, 'message' => 'Erreur lors du refus de la proposition: ' . $e->getMessage()];
            }
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Action non reconnue.'];
        break;
}

echo json_encode($response);
?>
