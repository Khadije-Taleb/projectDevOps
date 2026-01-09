<?php
session_start();

function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? 0;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

function isClient() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Client';
}

function isFreelancer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Freelancer';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function checkUserStatus() {
    if (isLoggedIn()) {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT statut FROM UTILISATEUR WHERE id_utilisateur = ?");
        $stmt->execute([getUserId()]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['statut'] !== 'actif') {
            session_destroy();
            setFlashMessage('danger', 'Votre compte a été ' . ($user['statut'] === 'suspendu' ? 'suspendu' : 'désactivé') . '. Veuillez contacter l\'administrateur.');
            redirect('/SupFreelance/index.php');
        }
    }
}

function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

function formatDate($date, $format = 'd/m/Y à H:i') {
    return date($format, strtotime($date));
}

function getClientId($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM CLIENT WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client && isClient()) {
        $stmt = $conn->prepare("INSERT INTO CLIENT (id_utilisateur) VALUES (?)");
        $stmt->execute([$user_id]);
        
        $stmt = $conn->prepare("SELECT id FROM CLIENT WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $client ? $client['id'] : null;
}

function getFreelancerId($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM FREELANCER WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $freelancer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$freelancer && isFreelancer()) {
        $stmt = $conn->prepare("INSERT INTO FREELANCER (id_utilisateur) VALUES (?)");
        $stmt->execute([$user_id]);
        
        $stmt = $conn->prepare("SELECT id FROM FREELANCER WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $freelancer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $freelancer ? $freelancer['id'] : null;
}
