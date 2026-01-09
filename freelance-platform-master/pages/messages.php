<?php
require_once '../includes/header.php';

if (!isLoggedIn() || isAdmin()) {
    setFlashMessage('danger', 'Vous devez être connecté en tant que client ou freelance pour accéder à la messagerie.');
    redirect('../index.php');
}

$database = new Database();
$conn = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$freelancer_id = isset($_GET['freelancer_id']) ? intval($_GET['freelancer_id']) : 0;
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$id_conversation = isset($_GET['id_conversation']) ? intval($_GET['id_conversation']) : 0;


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


$conversation_active = null;
$interlocuteur = null;

if ($action === 'chat') {
    if ($freelancer_id > 0 || $client_id > 0) {
        
        if (isClient() && $freelancer_id > 0) {
            
            $stmt = $conn->prepare("SELECT id FROM CLIENT WHERE id_utilisateur = ?");
            $stmt->execute([getUserId()]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($client) {
                
                $stmt = $conn->prepare("
                    SELECT c.*, u.nom, u.prenom, u.photo_profile
                    FROM CONVERSATION c
                    JOIN FREELANCER f ON c.id_freelancer = f.id
                    JOIN UTILISATEUR u ON f.id_utilisateur = u.id_utilisateur
                    WHERE c.id_client = ? AND f.id_utilisateur = ?
                ");
                $stmt->execute([$client['id'], $freelancer_id]);
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($conversation) {
                    $conversation_active = $conversation;
                    $interlocuteur = [
                        'id_utilisateur' => $freelancer_id,
                        'nom' => $conversation['nom'],
                        'prenom' => $conversation['prenom'],
                        'photo_profile' => $conversation['photo_profile']
                    ];
                } else {
                    
                    $stmt = $conn->prepare("
                        SELECT f.id, u.nom, u.prenom, u.photo_profile
                        FROM FREELANCER f
                        JOIN UTILISATEUR u ON f.id_utilisateur = u.id_utilisateur
                        WHERE f.id_utilisateur = ?
                    ");
                    $stmt->execute([$freelancer_id]);
                    $freelancer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($freelancer) {
                        
                        $stmt = $conn->prepare("INSERT INTO CONVERSATION (id_client, id_freelancer, date_creation, date_derniere_activite) VALUES (?, ?, NOW(), NOW())");
                        $stmt->execute([$client['id'], $freelancer['id']]);
                        $id_conversation = $conn->lastInsertId();
                        
                        
                        $stmt = $conn->prepare("SELECT * FROM CONVERSATION WHERE id_conversation = ?");
                        $stmt->execute([$id_conversation]);
                        $conversation_active = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $interlocuteur = [
                            'id_utilisateur' => $freelancer_id,
                            'nom' => $freelancer['nom'],
                            'prenom' => $freelancer['prenom'],
                            'photo_profile' => $freelancer['photo_profile']
                        ];
                    }
                }
            }
        } elseif (isFreelancer() && $client_id > 0) {
            
            $stmt = $conn->prepare("SELECT id FROM FREELANCER WHERE id_utilisateur = ?");
            $stmt->execute([getUserId()]);
            $freelancer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($freelancer) {
                
                $stmt = $conn->prepare("
                    SELECT c.*, u.nom, u.prenom, u.photo_profile
                    FROM CONVERSATION c
                    JOIN CLIENT cl ON c.id_client = cl.id
                    JOIN UTILISATEUR u ON cl.id_utilisateur = u.id_utilisateur
                    WHERE c.id_freelancer = ? AND cl.id_utilisateur = ?
                ");
                $stmt->execute([$freelancer['id'], $client_id]);
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($conversation) {
                    $conversation_active = $conversation;
                    $interlocuteur = [
                        'id_utilisateur' => $client_id,
                        'nom' => $conversation['nom'],
                        'prenom' => $conversation['prenom'],
                        'photo_profile' => $conversation['photo_profile']
                    ];
                } else {
                    
                    $stmt = $conn->prepare("
                        SELECT cl.id, u.nom, u.prenom, u.photo_profile
                        FROM CLIENT cl
                        JOIN UTILISATEUR u ON cl.id_utilisateur = u.id_utilisateur
                        WHERE cl.id_utilisateur = ?
                    ");
                    $stmt->execute([$client_id]);
                    $client = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($client) {
                        
                        $stmt = $conn->prepare("INSERT INTO CONVERSATION (id_client, id_freelancer, date_creation, date_derniere_activite) VALUES (?, ?, NOW(), NOW())");
                        $stmt->execute([$client['id'], $freelancer['id']]);
                        $id_conversation = $conn->lastInsertId();
                        
                        
                        $stmt = $conn->prepare("SELECT * FROM CONVERSATION WHERE id_conversation = ?");
                        $stmt->execute([$id_conversation]);
                        $conversation_active = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $interlocuteur = [
                            'id_utilisateur' => $client_id,
                            'nom' => $client['nom'],
                            'prenom' => $client['prenom'],
                            'photo_profile' => $client['photo_profile']
                        ];
                    }
                }
            }
        }
    } elseif ($id_conversation > 0) {
        
        if (isClient()) {
            $stmt = $conn->prepare("
                SELECT c.*, u.id_utilisateur as freelancer_user_id, u.nom, u.prenom, u.photo_profile
                FROM CONVERSATION c
                JOIN CLIENT cl ON c.id_client = cl.id
                JOIN FREELANCER f ON c.id_freelancer = f.id
                JOIN UTILISATEUR u ON f.id_utilisateur = u.id_utilisateur
                WHERE c.id_conversation = ? AND cl.id_utilisateur = ?
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT c.*, u.id_utilisateur as client_user_id, u.nom, u.prenom, u.photo_profile
                FROM CONVERSATION c
                JOIN FREELANCER f ON c.id_freelancer = f.id
                JOIN CLIENT cl ON c.id_client = cl.id
                JOIN UTILISATEUR u ON cl.id_utilisateur = u.id_utilisateur
                WHERE c.id_conversation = ? AND f.id_utilisateur = ?
            ");
        }
        
        $stmt->execute([$id_conversation, getUserId()]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conversation) {
            $conversation_active = $conversation;
            $interlocuteur = [
                'id_utilisateur' => isClient() ? $conversation['freelancer_user_id'] : $conversation['client_user_id'],
                'nom' => $conversation['nom'],
                'prenom' => $conversation['prenom'],
                'photo_profile' => $conversation['photo_profile']
            ];
        }
    } elseif (!empty($conversations)) {
        
        $conversation_active = $conversations[0];
        $interlocuteur = [
            'id_utilisateur' => isClient() ? $conversation_active['freelancer_user_id'] : $conversation_active['client_user_id'],
            'nom' => $conversation_active['nom'],
            'prenom' => $conversation_active['prenom'],
            'photo_profile' => $conversation_active['photo_profile']
        ];
    }
}


$messages = [];
if ($conversation_active) {
    $stmt = $conn->prepare("
        SELECT m.*, u.nom, u.prenom, u.photo_profile
        FROM MESSAGE m
        JOIN UTILISATEUR u ON m.id_sender = u.id_utilisateur
        WHERE m.id_conversation = ?
        ORDER BY m.date_envoi ASC
    ");
    $stmt->execute([$conversation_active['id_conversation']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    $stmt = $conn->prepare("
        UPDATE MESSAGE 
        SET lu = 1 
        WHERE id_conversation = ? AND id_sender != ? AND lu = 0
    ");
    $stmt->execute([$conversation_active['id_conversation'], getUserId()]);
}
?>
<div class="container py-5">
    <div class="row">
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Conversations</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($conversations)): ?>
                    <div class="text-center p-4">
                        <p class="text-muted mb-0">Aucune conversation pour le moment.</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($conversations as $conversation): ?>
                        <a href="?action=chat&id_conversation=<?php echo $conversation['id_conversation']; ?>" 
                           class="list-group-item list-group-item-action <?php echo $conversation_active && $conversation_active['id_conversation'] == $conversation['id_conversation'] ? 'active' : ''; ?>">
                            <div class="d-flex align-items-center">
                                <img src="../assets/img/<?php echo !empty($conversation['photo_profile']) ? htmlspecialchars($conversation['photo_profile']) : 'default.jpg'; ?>" 
                                     alt="Photo de profil" class="rounded-circle me-3" width="50" height="50">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($conversation['prenom'] . ' ' . $conversation['nom']); ?></h6>
                                        <?php if ($conversation['nb_non_lus'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill"><?php echo $conversation['nb_non_lus']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-0">
                                        <?php echo !empty($conversation['dernier_message']) ? htmlspecialchars(truncateText($conversation['dernier_message'], 30)) : 'Aucun message'; ?>
                                        <span class="mx-1">•</span>
                                        <?php echo !empty($conversation['date_dernier_message']) ? formatDate($conversation['date_dernier_message'], 'd/m/Y H:i') : ''; ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <?php if ($conversation_active): ?>
            <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <img src="../assets/img/<?php echo !empty($interlocuteur['photo_profile']) ? htmlspecialchars($interlocuteur['photo_profile']) : 'default.jpg'; ?>" 
                             alt="Photo de profil" class="rounded-circle me-3" width="40" height="40">
                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($interlocuteur['prenom'] . ' ' . $interlocuteur['nom']); ?></h5>
                    </div>
                    <button id="refresh-messages" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-sync-alt"></i> Actualiser
                    </button>
                </div>
                <div class="card-body p-0 flex-grow-1 overflow-auto" id="messages-container" style="max-height: 500px;">
                    <div class="p-3" id="messages-list">
                        <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <p class="text-muted mb-0">Aucun message pour le moment. Commencez la conversation !</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                        <div class="message mb-3 <?php echo $message['id_sender'] == getUserId() ? 'message-sent' : 'message-received'; ?>" data-message-id="<?php echo $message['id_message']; ?>">
                            <div class="message-content">
                                <div class="message-text">
                                    <?php echo nl2br(htmlspecialchars($message['contenu'] ?? '')); ?>
                                </div>
                                <div class="message-info">
                                    <small class="text-muted"><?php echo formatDate($message['date_envoi'], 'H:i'); ?></small>
                                    <?php if ($message['id_sender'] == getUserId() && $message['lu']): ?>
                                    <small class="text-primary ms-1"><i class="fas fa-check-double"></i></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <form id="message-form" data-conversation-id="<?php echo $conversation_active['id_conversation']; ?>">
                        <div class="input-group">
                            <textarea class="form-control" id="message-input" placeholder="Écrivez votre message..." rows="1" required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-comments fa-4x text-muted"></i>
                    </div>
                    <h3 class="h5 mb-3">Aucune conversation sélectionnée</h3>
                    <p class="text-muted">Sélectionnez une conversation dans la liste ou commencez une nouvelle conversation.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messagesContainer = document.getElementById('messages-container');
    const messagesList = document.getElementById('messages-list');
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message-input');
    const refreshButton = document.getElementById('refresh-messages');
    
    
    const displayedMessageIds = new Set();
    
    
    let lastMessageId = 0;
    
    
    const existingMessages = document.querySelectorAll('.message');
    existingMessages.forEach(message => {
        const messageId = parseInt(message.getAttribute('data-message-id'));
        if (messageId) {
            displayedMessageIds.add(messageId);
            lastMessageId = Math.max(lastMessageId, messageId);
        }
    });
    
    
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    
    if (messageForm) {
        messageForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const message = messageInput.value.trim();
            if (!message) return;
            
            const conversationId = messageForm.getAttribute('data-conversation-id');
            
            
            const submitButton = messageForm.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;
            
            
            messageInput.value = '';
            messageInput.style.height = 'auto';
            
            
            fetch('../api/messages.php?action=envoyer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id_conversation=${conversationId}&contenu=${encodeURIComponent(message)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    
                    if (!displayedMessageIds.has(data.data.id_message)) {
                        addMessageToChat(data.data);
                        displayedMessageIds.add(data.data.id_message);
                        lastMessageId = Math.max(lastMessageId, data.data.id_message);
                    }
                } else {
                    console.error('Erreur lors de l\'envoi du message:', data.message);
                    alert('Erreur lors de l\'envoi du message: ' + data.message);
                    
                    messageInput.value = message;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de l\'envoi du message.');
                
                messageInput.value = message;
            })
            .finally(() => {
                
                if (submitButton) submitButton.disabled = false;
            });
        });
    }
    
    
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        
        messageInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                messageForm.dispatchEvent(new Event('submit'));
            }
        });
    }
    
    
    function addMessageToChat(message) {
        
        if (document.querySelector(`.message[data-message-id="${message.id_message}"]`)) {
            return; 
        }
        
        const messageElement = document.createElement('div');
        messageElement.className = 'message mb-3 ' + (message.is_sender ? 'message-sent' : 'message-received');
        messageElement.setAttribute('data-message-id', message.id_message);
        
        const messageContent = message.contenu ? message.contenu.replace(/\n/g, '<br>') : '';
        
        messageElement.innerHTML = `
            <div class="message-content">
                <div class="message-text">
                    ${messageContent}
                </div>
                <div class="message-info">
                    <small class="text-muted">${formatTime(message.date_envoi)}</small>
                    ${message.is_sender ? '<small class="text-primary ms-1"><i class="fas fa-check"></i></small>' : ''}
                </div>
            </div>
        `;
        
        messagesList.appendChild(messageElement);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    
    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
    
    
    function fetchNewMessages() {
        if (!messageForm) return;
        
        const conversationId = messageForm.getAttribute('data-conversation-id');
        if (!conversationId) return;
        
        
        if (refreshButton) refreshButton.disabled = true;
        
        fetch(`../api/messages.php?action=charger_messages&id_conversation=${conversationId}&last_id=${lastMessageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    data.data.forEach(message => {
                        if (!displayedMessageIds.has(message.id_message)) {
                            addMessageToChat(message);
                            displayedMessageIds.add(message.id_message);
                            lastMessageId = Math.max(lastMessageId, message.id_message);
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des messages:', error);
            })
            .finally(() => {
                
                if (refreshButton) refreshButton.disabled = false;
            });
    }
    
    
    if (refreshButton) {
        refreshButton.addEventListener('click', fetchNewMessages);
    }
});
</script>


<style>
.list-group-item.active {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    color: #212529;
    border-left: 4px solid #0d6efd;
}

.message {
    display: flex;
    margin-bottom: 15px;
}

.message-sent {
    justify-content: flex-end;
}

.message-received {
    justify-content: flex-start;
}

.message-content {
    max-width: 70%;
    padding: 10px 15px;
    border-radius: 18px;
    position: relative;
}

.message-sent .message-content {
    background-color: #0d6efd;
    color: white;
    border-bottom-right-radius: 5px;
}

.message-received .message-content {
    background-color: #f0f2f5;
    color: #212529;
    border-bottom-left-radius: 5px;
}

.message-text {
    word-wrap: break-word;
}

.message-info {
    display: flex;
    justify-content: flex-end;
    margin-top: 5px;
    font-size: 0.75rem;
}

.message-sent .message-info {
    color: rgba(255, 255, 255, 0.7);
}

#message-input {
    resize: none;
    overflow: hidden;
    min-height: 38px;
    max-height: 120px;
}
</style>

<?php require_once '../includes/footer.php'; ?>
