<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$response = ['success' => false, 'message' => 'Action non spécifiée.'];

switch ($action) {
    case 'changer_statut_utilisateur':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_utilisateur = intval($_POST['id_utilisateur'] ?? 0);
            $statut = sanitize($_POST['statut'] ?? '');
            
            
            if ($id_utilisateur <= 0 || !in_array($statut, ['actif', 'inactif', 'suspendu'])) {
                $response = ['success' => false, 'message' => 'Paramètres invalides.'];
                break;
            }
            
            try {
                
                $stmt = $conn->prepare("SELECT id_utilisateur, id_role FROM UTILISATEUR WHERE id_utilisateur = ?");
                $stmt->execute([$id_utilisateur]);
                $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$utilisateur) {
                    $response = ['success' => false, 'message' => 'Utilisateur non trouvé.'];
                    break;
                }
                
                
                if ($utilisateur['id_role'] == 1 && getUserId() != $id_utilisateur) {
                    $response = ['success' => false, 'message' => 'Vous ne pouvez pas modifier le statut d\'un administrateur.'];
                    break;
                }
                
                
                $stmt = $conn->prepare("UPDATE UTILISATEUR SET statut = ? WHERE id_utilisateur = ?");
                $stmt->execute([$statut, $id_utilisateur]);
                
                $response = [
                    'success' => true, 
                    'message' => 'Le statut de l\'utilisateur a été mis à jour avec succès.',
                    'statut' => $statut
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors de la mise à jour du statut: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'changer_statut_projet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_projet = intval($_POST['id_projet'] ?? 0);
            $statut = sanitize($_POST['statut'] ?? '');
            
            
            if ($id_projet <= 0 || !in_array($statut, ['ouvert', 'en_cours', 'terminé', 'annulé'])) {
                $response = ['success' => false, 'message' => 'Paramètres invalides.'];
                break;
            }
            
            try {
                
                $stmt = $conn->prepare("SELECT id_projet FROM PROJET WHERE id_projet = ?");
                $stmt->execute([$id_projet]);
                if ($stmt->rowCount() === 0) {
                    $response = ['success' => false, 'message' => 'Projet non trouvé.'];
                    break;
                }
                
                
                $stmt = $conn->prepare("UPDATE PROJET SET statut = ? WHERE id_projet = ?");
                $stmt->execute([$statut, $id_projet]);
                
                
                if ($statut === 'annulé') {
                    $stmt = $conn->prepare("UPDATE PROPOSITION SET statut = 'refusée' WHERE id_projet = ? AND statut = 'en_attente'");
                    $stmt->execute([$id_projet]);
                }
                
                $response = [
                    'success' => true, 
                    'message' => 'Le statut du projet a été mis à jour avec succès.',
                    'statut' => $statut
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors de la mise à jour du statut: ' . $e->getMessage()];
            }
        }
        break;
        case 'supprimer_conversation':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_conversation = intval($_POST['id_conversation'] ?? 0);
        
        
        if ($id_conversation <= 0) {
            $response = ['success' => false, 'message' => 'Paramètres invalides.'];
            break;
        }
        
        try {
            
            $stmt = $conn->prepare("SELECT id_conversation FROM CONVERSATION WHERE id_conversation = ?");
            $stmt->execute([$id_conversation]);
            if ($stmt->rowCount() === 0) {
                $response = ['success' => false, 'message' => 'Conversation non trouvée.'];
                break;
            }
            
            $conn->beginTransaction();
            
            
            $stmt = $conn->prepare("DELETE FROM MESSAGE WHERE id_conversation = ?");
            $stmt->execute([$id_conversation]);
            
            
            $stmt = $conn->prepare("DELETE FROM CONVERSATION WHERE id_conversation = ?");
            $stmt->execute([$id_conversation]);
            
            $conn->commit();
            
            $response = [
                'success' => true, 
                'message' => 'La conversation a été supprimée avec succès.',
                'redirect' => '../pages/admin/conversations.php'
            ];
        } catch (PDOException $e) {
            $conn->rollBack();
            $response = ['success' => false, 'message' => 'Erreur lors de la suppression de la conversation: ' . $e->getMessage()];
        }
    }
    break;
 

        
    default:
        $response = ['success' => false, 'message' => 'Action non reconnue.'];
        break;
}

echo json_encode($response);
?>
