<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$response = ['success' => false, 'message' => 'Action non spécifiée.'];

switch ($action) {
    case 'inscription':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = sanitize($_POST['email'] ?? '');
            $mot_de_passe = $_POST['mot_de_passe'] ?? '';
            $nom = sanitize($_POST['nom'] ?? '');
            $prenom = sanitize($_POST['prenom'] ?? '');
            $type = sanitize($_POST['type'] ?? ''); 
            
            
            if (empty($email) || empty($mot_de_passe) || empty($nom) || empty($prenom) || empty($type) || !in_array($type, ['client', 'freelancer'])) {
                $response = ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'];
                break;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response = ['success' => false, 'message' => 'Adresse email invalide.'];
                break;
            }
            
            if (strlen($mot_de_passe) < 8) {
                $response = ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
                break;
            }
            
            try {
                
                $stmt = $conn->prepare("SELECT id_utilisateur FROM UTILISATEUR WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    $response = ['success' => false, 'message' => 'Cette adresse email est déjà utilisée.'];
                    break;
                }
                
                
                $role_id = ($type === 'client') ? 2 : 3; 
                
                
                $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO UTILISATEUR (email, mot_de_passe, nom, prenom, id_role, date_inscription) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$email, $mot_de_passe_hash, $nom, $prenom, $role_id]);
                $user_id = $conn->lastInsertId();
                
                
                if ($type === 'client') {
                    $stmt = $conn->prepare("INSERT INTO CLIENT (id_utilisateur) VALUES (?)");
                    $stmt->execute([$user_id]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO FREELANCER (id_utilisateur) VALUES (?)");
                    $stmt->execute([$user_id]);
                }
                
                
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['nom'] = $nom;
                $_SESSION['prenom'] = $prenom;
                $_SESSION['role'] = ($type === 'client') ? 'Client' : 'Freelancer';
                
                
                $stmt = $conn->prepare("UPDATE UTILISATEUR SET derniere_connexion = NOW() WHERE id_utilisateur = ?");
                $stmt->execute([$user_id]);
                
                $response = [
                    'success' => true, 
                    'message' => 'Inscription réussie ! Vous êtes maintenant connecté.',
                    'redirect' => '../pages/accueil.php'
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors de l\'inscription: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'connexion':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = sanitize($_POST['email'] ?? '');
            $mot_de_passe = $_POST['mot_de_passe'] ?? '';
            $se_souvenir = isset($_POST['se_souvenir']);
            
            
            if (empty($email) || empty($mot_de_passe)) {
                $response = ['success' => false, 'message' => 'Veuillez remplir tous les champs.'];
                break;
            }
            
            try {
                $stmt = $conn->prepare("SELECT u.*, r.nom as role_nom FROM UTILISATEUR u JOIN ROLE r ON u.id_role = r.id_role WHERE u.email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user || !password_verify($mot_de_passe, $user['mot_de_passe'])) {
                    $response = ['success' => false, 'message' => 'Email ou mot de passe incorrect.'];
                    break;
                }
                
                if ($user['statut'] !== 'actif') {
                    $response = ['success' => false, 'message' => 'Votre compte a été ' . ($user['statut'] === 'suspendu' ? 'suspendu' : 'désactivé') . '. Veuillez contacter l\'administrateur.'];
                    break;
                }
                
                
                $_SESSION['user_id'] = $user['id_utilisateur'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['nom'] = $user['nom'];
                $_SESSION['prenom'] = $user['prenom'];
                $_SESSION['role'] = $user['role_nom'];
                $_SESSION['photo_profile'] = $user['photo_profile'];
                
                
                $stmt = $conn->prepare("UPDATE UTILISATEUR SET derniere_connexion = NOW() WHERE id_utilisateur = ?");
                $stmt->execute([$user['id_utilisateur']]);
                
                
               
                
                $redirect = '../pages/accueil.php';
                if ($user['role_nom'] === 'Admin') {
                    $redirect = '../pages/admin/dashboard.php';
                }
                
                $response = [
                    'success' => true, 
                    'message' => 'Connexion réussie !',
                    'redirect' => $redirect
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors de la connexion: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'modifier_profil':
        if (!isLoggedIn()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté pour modifier votre profil.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nom = sanitize($_POST['nom'] ?? '');
            $prenom = sanitize($_POST['prenom'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $mot_de_passe_actuel = $_POST['mot_de_passe_actuel'] ?? '';
            $nouveau_mot_de_passe = $_POST['nouveau_mot_de_passe'] ?? '';
            
            
            if (empty($nom) || empty($prenom) || empty($email)) {
                $response = ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'];
                break;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response = ['success' => false, 'message' => 'Adresse email invalide.'];
                break;
            }
            
            try {
                
                $stmt = $conn->prepare("SELECT id_utilisateur FROM UTILISATEUR WHERE email = ? AND id_utilisateur != ?");
                $stmt->execute([$email, getUserId()]);
                if ($stmt->rowCount() > 0) {
                    $response = ['success' => false, 'message' => 'Cette adresse email est déjà utilisée par un autre utilisateur.'];
                    break;
                }
                
                
                $stmt = $conn->prepare("SELECT mot_de_passe FROM UTILISATEUR WHERE id_utilisateur = ?");
                $stmt->execute([getUserId()]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                
                $stmt = $conn->prepare("UPDATE UTILISATEUR SET nom = ?, prenom = ?, email = ? WHERE id_utilisateur = ?");
                $stmt->execute([$nom, $prenom, $email, getUserId()]);
                
                
                if (!empty($mot_de_passe_actuel) && !empty($nouveau_mot_de_passe)) {
                    if (!password_verify($mot_de_passe_actuel, $user['mot_de_passe'])) {
                        $response = ['success' => false, 'message' => 'Le mot de passe actuel est incorrect.'];
                        break;
                    }
                    
                    if (strlen($nouveau_mot_de_passe) < 8) {
                        $response = ['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'];
                        break;
                    }
                    
                    $mot_de_passe_hash = password_hash($nouveau_mot_de_passe, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE UTILISATEUR SET mot_de_passe = ? WHERE id_utilisateur = ?");
                    $stmt->execute([$mot_de_passe_hash, getUserId()]);
                }
                
                
                if (isClient()) {
                    $entreprise = sanitize($_POST['entreprise'] ?? '');
                    $site_web = sanitize($_POST['site_web'] ?? '');
                    $description = sanitize($_POST['description'] ?? '');
                    
                    $stmt = $conn->prepare("UPDATE CLIENT SET entreprise = ?, site_web = ?, description = ? WHERE id_utilisateur = ?");
                    $stmt->execute([$entreprise, $site_web, $description, getUserId()]);
                } elseif (isFreelancer()) {
                    $competences = sanitize($_POST['competences'] ?? '');
                    $experience = sanitize($_POST['experience'] ?? '');
                    $tarif_horaire = floatval($_POST['tarif_horaire'] ?? 0);
                    $disponibilite = sanitize($_POST['disponibilite'] ?? 'disponible');
                    
                    $stmt = $conn->prepare("UPDATE FREELANCER SET competences = ?, experience = ?, tarif_horaire = ?, disponibilite = ? WHERE id_utilisateur = ?");
                    $stmt->execute([$competences, $experience, $tarif_horaire, $disponibilite, getUserId()]);
                }
                
                
                if (isset($_FILES['photo_profile']) && $_FILES['photo_profile']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 2 * 1024 * 1024; 
                    
                                        if (!in_array($_FILES['photo_profile']['type'], $allowed_types)) {
                        $response = ['success' => false, 'message' => 'Format de fichier non autorisé. Utilisez JPG, PNG ou GIF.'];
                        break;
                    }
                    
                    if ($_FILES['photo_profile']['size'] > $max_size) {
                        $response = ['success' => false, 'message' => 'La taille du fichier ne doit pas dépasser 2 MB.'];
                        break;
                    }
                    
                    $extension = pathinfo($_FILES['photo_profile']['name'], PATHINFO_EXTENSION);
                    $new_filename = 'profile_' . getUserId() . '_' . time() . '.' . $extension;
                    $upload_path = '../assets/img/' . $new_filename;
                    
                    if (move_uploaded_file($_FILES['photo_profile']['tmp_name'], $upload_path)) {
                        
                        $stmt = $conn->prepare("SELECT photo_profile FROM UTILISATEUR WHERE id_utilisateur = ?");
                        $stmt->execute([getUserId()]);
                        $old_photo = $stmt->fetchColumn();
                        
                        if ($old_photo && $old_photo !== 'default.jpg' && file_exists('../assets/img/' . $old_photo)) {
                            unlink('../assets/img/' . $old_photo);
                        }
                        
                        
                        $stmt = $conn->prepare("UPDATE UTILISATEUR SET photo_profile = ? WHERE id_utilisateur = ?");
                        $stmt->execute([$new_filename, getUserId()]);
                        
                        
                        $_SESSION['photo_profile'] = $new_filename;
                    } else {
                        $response = ['success' => false, 'message' => 'Erreur lors du téléchargement de la photo de profil.'];
                        break;
                    }
                }
                
                
                $_SESSION['nom'] = $nom;
                $_SESSION['prenom'] = $prenom;
                $_SESSION['email'] = $email;
                
                $response = [
                    'success' => true, 
                    'message' => 'Profil mis à jour avec succès !',
                    'redirect' => '../pages/profil.php'
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors de la mise à jour du profil: ' . $e->getMessage()];
            }
        }
        break;
        
    case 'changer_role':
        if (!isLoggedIn()) {
            $response = ['success' => false, 'message' => 'Vous devez être connecté pour changer de rôle.'];
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nouveau_role = '';
            $role_actuel = $_SESSION['role'];
            
            if ($role_actuel === 'Client') {
                $nouveau_role = 'Freelancer';
                $nouveau_role_id = 3; 
            } else if ($role_actuel === 'Freelancer') {
                $nouveau_role = 'Client';
                $nouveau_role_id = 2; 
            } else {
                $response = ['success' => false, 'message' => 'Vous ne pouvez pas changer de rôle en tant qu\'administrateur.'];
                break;
            }
            
            try {
                
                $stmt = $conn->prepare("UPDATE UTILISATEUR SET id_role = ? WHERE id_utilisateur = ?");
                $stmt->execute([$nouveau_role_id, getUserId()]);
                
                
                if ($nouveau_role === 'Client') {
                    $stmt = $conn->prepare("SELECT id FROM CLIENT WHERE id_utilisateur = ?");
                    $stmt->execute([getUserId()]);
                    if ($stmt->rowCount() === 0) {
                        
                        $stmt = $conn->prepare("INSERT INTO CLIENT (id_utilisateur) VALUES (?)");
                        $stmt->execute([getUserId()]);
                    }
                } else {
                    $stmt = $conn->prepare("SELECT id FROM FREELANCER WHERE id_utilisateur = ?");
                    $stmt->execute([getUserId()]);
                    if ($stmt->rowCount() === 0) {
                        
                        $stmt = $conn->prepare("INSERT INTO FREELANCER (id_utilisateur) VALUES (?)");
                        $stmt->execute([getUserId()]);
                    }
                }
                
                
                $_SESSION['role'] = $nouveau_role;
                
                $response = [
                    'success' => true, 
                    'message' => 'Votre rôle a été changé avec succès en ' . $nouveau_role . '.',
                    'redirect' => '../pages/accueil.php'
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Erreur lors du changement de rôle: ' . $e->getMessage()];
            }
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Action non reconnue.'];
        break;
}

echo json_encode($response);
?>
