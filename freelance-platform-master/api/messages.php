<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$response = ['success' => false, 'message' => 'Action non spécifiée.'];

switch ($action) {
       case 'envoyer':
        if (!isLoggedIn()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté pour envoyer un message.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_conversation = intval($_POST['id_conversation'] ?? 0);
            $contenu = sanitize($_POST['contenu'] ?? '');
            
            
            if ($id_conversation <= 0 || empty($contenu)) {
                $response = ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'];
                break;
            }
            
            try {
                
                if (isClient()) {
                    $stmt = $conn->prepare("
                        SELECT c.* FROM CONVERSATION c
                        JOIN CLIENT cl ON c.id_client = cl.id
                        WHERE c.id_conversation = ? AND cl.id_utilisateur = ?
                    ");
                } else {
                    $stmt = $conn->prepare("
                        SELECT c.* FROM CONVERSATION c
                        JOIN FREELANCER f ON c.id_freelancer = f.id
                        WHERE c.id_conversation = ? AND f.id_utilisateur = ?
                    ");
                }
                
                $stmt->execute([$id_conversation, getUserId()]);
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$conversation) {
                    $response = ['success' => false, 'message' => 'Conversation non trouvée ou vous n\'êtes pas autorisé à y participer.'];
                    break;
                }
                
                
                $stmt = $conn->prepare("INSERT INTO MESSAGE (id_conversation, id_sender, contenu, date_envoi, lu) VALUES (?, ?, ?, NOW(), 0)");
                $stmt->execute([$id_conversation, getUserId(), $contenu]);
                
                
                $stmt = $conn->prepare("UPDATE CONVERSATION SET date_derniere_activite = NOW() WHERE id_conversation = ?");
                $stmt->execute([$id_conversation]);
                
                
                $id_message = $conn->lastInsertId();
                $stmt = $conn->prepare("
                    SELECT m.*, u.nom, u.prenom, u.photo_profile
                    FROM MESSAGE m
                    JOIN UTILISATEUR u ON m.id_sender = u.id_utilisateur
                    WHERE m.id_message = ?
                ");
                $stmt->execute([$id_message]);
                $message = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $response = [
                    'success' => true, 
                    'message' => 'Message envoyé avec succès.',
                    'data' => [
                        'id_message' => $message['id_message'],
                        'id_conversation' => $message['id_conversation'],
                        'id_sender' => $message['id_sender'],
                        'contenu' => $message['contenu'] ?? '',
                        'date_envoi' => $message['date_envoi'],
                        'lu' => $message['lu'],
                        'nom' => $message['nom'],
                        'prenom' => $message['prenom'],
                        'photo_profile' => $message['photo_profile'],
                        'is_sender' => true
                    ]
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors de l\'envoi du message: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'charger_messages':
        if (!isLoggedIn()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté pour charger les messages.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $id_conversation = intval($_GET['id_conversation'] ?? 0);
            $last_id = intval($_GET['last_id'] ?? 0);
          
            if ($id_conversation <= 0) {
                $response = ['success' => false, 'message' => 'Paramètres invalides.'];
                break;
            }
            
            try {
               
                if (isClient()) {
                    $stmt = $conn->prepare("
                        SELECT c.* FROM CONVERSATION c
                        JOIN CLIENT cl ON c.id_client = cl.id
                        WHERE c.id_conversation = ? AND cl.id_utilisateur = ?
                    ");
                } else {
                    $stmt = $conn->prepare("
                        SELECT c.* FROM CONVERSATION c
                        JOIN FREELANCER f ON c.id_freelancer = f.id
                        WHERE c.id_conversation = ? AND f.id_utilisateur = ?
                    ");
                }
                
                $stmt->execute([$id_conversation, getUserId()]);
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$conversation) {
                    $response = ['success' => false, 'message' => 'Conversation non trouvée ou vous n\'êtes pas autorisé à y participer.'];
                    break;
                }
               
                if ($last_id > 0) {
                    $sql = "
                        SELECT m.*, u.nom, u.prenom, u.photo_profile
                        FROM MESSAGE m
                        JOIN UTILISATEUR u ON m.id_sender = u.id_utilisateur
                        WHERE m.id_conversation = ? AND m.id_message > ?
                        ORDER BY m.date_envoi ASC
                    ";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$id_conversation, $last_id]);
                } else {
                    $sql = "
                        SELECT m.*, u.nom, u.prenom, u.photo_profile
                        FROM MESSAGE m
                        JOIN UTILISATEUR u ON m.id_sender = u.id_utilisateur
                        WHERE m.id_conversation = ?
                        ORDER BY m.date_envoi ASC
                    ";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$id_conversation]);
                }
                
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
                if (!empty($messages)) {
                    $stmt = $conn->prepare("
                        UPDATE MESSAGE 
                        SET lu = 1 
                        WHERE id_conversation = ? AND id_sender != ? AND lu = 0
                    ");
                    $stmt->execute([$id_conversation, getUserId()]);
                }
              
                $formatted_messages = [];
                foreach ($messages as $message) {
                    $formatted_messages[] = [
                        'id_message' => $message['id_message'],
                        'id_conversation' => $message['id_conversation'],
                        'id_sender' => $message['id_sender'],
                        'contenu' => $message['contenu'] ?? '',
                        'date_envoi' => $message['date_envoi'],
                        'lu' => $message['lu'],
                        'nom' => $message['nom'],
                        'prenom' => $message['prenom'],
                        'photo_profile' => $message['photo_profile'],
                        'is_sender' => $message['id_sender'] == getUserId()
                    ];
                }
                
                $response = [
                    'success' => true, 
                    'message' => 'Messages chargés avec succès.',
                    'data' => $formatted_messages
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors du chargement des messages: ' . $e->getMessage()];
            }
        }
        break;
        
        
    case 'charger_messages':
        if (!isLoggedIn()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté pour charger les messages.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $id_conversation = intval($_GET['id_conversation'] ?? 0);
            $last_id = intval($_GET['last_id'] ?? 0);
            
            
            if ($id_conversation <= 0) {
                $response = ['success' => false, 'message' => 'Paramètres invalides.'];
                break;
            }
            
            try {
                
                if (isClient()) {
                    $stmt = $conn->prepare("
                        SELECT c.* FROM CONVERSATION c
                        JOIN CLIENT cl ON c.id_client = cl.id
                        WHERE c.id_conversation = ? AND cl.id_utilisateur = ?
                    ");
                } else {
                    $stmt = $conn->prepare("
                        SELECT c.* FROM CONVERSATION c
                        JOIN FREELANCER f ON c.id_freelancer = f.id
                        WHERE c.id_conversation = ? AND f.id_utilisateur = ?
                    ");
                }
                
                $stmt->execute([$id_conversation, getUserId()]);
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$conversation) {
                    $response = ['success' => false, 'message' => 'Conversation non trouvée ou vous n\'êtes pas autorisé à y participer.'];
                    break;
                }
                
                
                if ($last_id > 0) {
                    $sql = "
                        SELECT m.*, u.nom, u.prenom, u.photo_profile
                        FROM MESSAGE m
                        JOIN UTILISATEUR u ON m.id_sender = u.id_utilisateur
                        WHERE m.id_conversation = ? AND m.id_message > ?
                        ORDER BY m.date_envoi ASC
                    ";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$id_conversation, $last_id]);
                } else {
                    $sql = "
                        SELECT m.*, u.nom, u.prenom, u.photo_profile
                        FROM MESSAGE m
                        JOIN UTILISATEUR u ON m.id_sender = u.id_utilisateur
                        WHERE m.id_conversation = ?
                        ORDER BY m.date_envoi ASC
                    ";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$id_conversation]);
                }
                
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                
                if (!empty($messages)) {
                    $stmt = $conn->prepare("
                        UPDATE MESSAGE 
                        SET lu = 1 
                        WHERE id_conversation = ? AND id_sender != ? AND lu = 0
                    ");
                    $stmt->execute([$id_conversation, getUserId()]);
                }
                
                
                $formatted_messages = [];
                foreach ($messages as $message) {
                    $formatted_messages[] = [
                        'id_message' => $message['id_message'],
                        'id_conversation' => $message['id_conversation'],
                        'id_sender' => $message['id_sender'],
                        'contenu' => $message['contenu'] ?? '',
                        'date_envoi' => $message['date_envoi'],
                        'lu' => $message['lu'],
                        'nom' => $message['nom'],
                        'prenom' => $message['prenom'],
                        'photo_profile' => $message['photo_profile'],
                        'is_sender' => $message['id_sender'] == getUserId()
                    ];
                }
                
                $response = [
                    'success' => true, 
                    'message' => 'Messages chargés avec succès.',
                    'data' => $formatted_messages
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors du chargement des messages: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'charger_conversations':
        if (!isLoggedIn()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté pour charger les conversations.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            try {
                
                if (isClient()) {
                    $stmt = $conn->prepare("
                        SELECT c.id_conversation, c.date_creation, c.date_derniere_activite,
                               f.id as freelancer_id, u.id_utilisateur as freelancer_user_id, u.nom, u.prenom, u.photo_profile,
                               (SELECT COUNT(*) FROM MESSAGE m WHERE m.id_conversation = c.id_conversation AND m.id_sender != ? AND m.lu = 0) as nb_non_lus,
                               (SELECT m.contenu FROM MESSAGE m WHERE m.id_conversation = c.id_conversation ORDER BY m.date_envoi DESC LIMIT 1) as dernier_message,
                               (SELECT m.date_envoi FROM MESSAGE m WHERE m.id_conversation = c.id_conversation ORDER BY m.date_envoi DESC LIMIT 1) as date_dernier_message
                        FROM CONVERSATION c
                        JOIN CLIENT cl ON c.id_client = cl.id
                        JOIN FREELANCER f ON c.id_freelancer = f.id
                        JOIN UTILISATEUR u ON f.id_utilisateur = u.id_utilisateur
                        WHERE cl.id_utilisateur = ?
                        ORDER BY c.date_derniere_activite DESC
                    ");
                    $stmt->execute([getUserId(), getUserId()]);
                } else {
                    $stmt = $conn->prepare("
                        SELECT c.id_conversation, c.date_creation, c.date_derniere_activite,
                               cl.id as client_id, u.id_utilisateur as client_user_id, u.nom, u.prenom, u.photo_profile,
                               (SELECT COUNT(*) FROM MESSAGE m WHERE m.id_conversation = c.id_conversation AND m.id_sender != ? AND m.lu = 0) as nb_non_lus,
                               (SELECT m.contenu FROM MESSAGE m WHERE m.id_conversation = c.id_conversation ORDER BY m.date_envoi DESC LIMIT 1) as dernier_message,
                               (SELECT m.date_envoi FROM MESSAGE m WHERE m.id_conversation = c.id_conversation ORDER BY m.date_envoi DESC LIMIT 1) as date_dernier_message
                        FROM CONVERSATION c
                        JOIN FREELANCER f ON c.id_freelancer = f.id
                        JOIN CLIENT cl ON c.id_client = cl.id
                        JOIN UTILISATEUR u ON cl.id_utilisateur = u.id_utilisateur
                        WHERE f.id_utilisateur = ?
                        ORDER BY c.date_derniere_activite DESC
                    ");
                    $stmt->execute([getUserId(), getUserId()]);
                }
                
                $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = [
                    'success' => true, 
                    'message' => 'Conversations chargées avec succès.',
                    'data' => $conversations
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors du chargement des conversations: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'creer_conversation':
        if (!isLoggedIn()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté pour créer une conversation.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $freelancer_id = intval($_POST['freelancer_id'] ?? 0);
            $client_id = intval($_POST['client_id'] ?? 0);
            
            
            if (isClient() && $freelancer_id <= 0) {
                $response = ['success' => false, 'message' => 'ID du freelance non spécifié.'];
                break;
            } elseif (isFreelancer() && $client_id <= 0) {
                $response = ['success' => false, 'message' => 'ID du client non spécifié.'];
                break;
            }
            
            try {
                
                if (isClient()) {
                    $stmt = $conn->prepare("SELECT id FROM CLIENT WHERE id_utilisateur = ?");
                    $stmt->execute([getUserId()]);
                    $user_profile = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$user_profile) {
                        $response = ['success' => false, 'message' => 'Profil client non trouvé.'];
                        break;
                    }
                    
                    $client_id = $user_profile['id'];
                    
                    
                    $stmt = $conn->prepare("SELECT id FROM FREELANCER WHERE id_utilisateur = ?");
                    $stmt->execute([$freelancer_id]);
                    $freelancer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$freelancer) {
                        $response = ['success' => false, 'message' => 'Profil freelance non trouvé.'];
                        break;
                    }
                    
                    $freelancer_id = $freelancer['id'];
                } else {
                    $stmt = $conn->prepare("SELECT id FROM FREELANCER WHERE id_utilisateur = ?");
                    $stmt->execute([getUserId()]);
                    $user_profile = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$user_profile) {
                        $response = ['success' => false, 'message' => 'Profil freelance non trouvé.'];
                        break;
                    }
                    
                    $freelancer_id = $user_profile['id'];
                    
                    
                    $stmt = $conn->prepare("SELECT id FROM CLIENT WHERE id_utilisateur = ?");
                    $stmt->execute([$client_id]);
                    $client = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$client) {
                        $response = ['success' => false, 'message' => 'Profil client non trouvé.'];
                        break;
                    }
                    
                    $client_id = $client['id'];
                }
                
                
                $stmt = $conn->prepare("SELECT id_conversation FROM CONVERSATION WHERE id_client = ? AND id_freelancer = ?");
                $stmt->execute([$client_id, $freelancer_id]);
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($conversation) {
                    $response = [
                        'success' => true, 
                        'message' => 'Conversation existante récupérée.',
                        'data' => [
                            'id_conversation' => $conversation['id_conversation'],
                            'is_new' => false
                        ]
                    ];
                } else {
                    
                    $stmt = $conn->prepare("INSERT INTO CONVERSATION (id_client, id_freelancer, date_creation, date_derniere_activite) VALUES (?, ?, NOW(), NOW())");
                    $stmt->execute([$client_id, $freelancer_id]);
                    $id_conversation = $conn->lastInsertId();
                    
                    $response = [
                        'success' => true, 
                        'message' => 'Conversation créée avec succès.',
                        'data' => [
                            'id_conversation' => $id_conversation,
                            'is_new' => true
                        ]
                    ];
                }
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors de la création de la conversation: ' . $e->getMessage()];
            }
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Action non reconnue.'];
        break;
}

echo json_encode($response);
?>
