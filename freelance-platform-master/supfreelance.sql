CREATE DATABASE IF NOT EXISTS supfreelance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE supfreelance;

CREATE TABLE ROLE (
    id_role INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL
);

CREATE TABLE UTILISATEUR (
    id_utilisateur INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    photo_profile VARCHAR(255) DEFAULT NULL,
    date_inscription DATETIME NOT NULL,
    derniere_connexion DATETIME DEFAULT NULL,
    id_role INT NOT NULL,
    statut ENUM('actif', 'inactif', 'suspendu') NOT NULL DEFAULT 'actif',
    FOREIGN KEY (id_role) REFERENCES ROLE(id_role)
);

CREATE TABLE CLIENT (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_utilisateur INT NOT NULL UNIQUE,
    entreprise VARCHAR(100) DEFAULT NULL,
    site_web VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES UTILISATEUR(id_utilisateur) ON DELETE CASCADE
);

CREATE TABLE FREELANCER (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_utilisateur INT NOT NULL UNIQUE,
    competences TEXT DEFAULT NULL,
    experience TEXT DEFAULT NULL,
    tarif_horaire DECIMAL(10,2) DEFAULT NULL,
    disponibilite ENUM('disponible', 'occupe', 'indisponible') DEFAULT 'disponible',
    FOREIGN KEY (id_utilisateur) REFERENCES UTILISATEUR(id_utilisateur) ON DELETE CASCADE
);

CREATE TABLE PROJET (
    id_projet INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    budget DECIMAL(10,2) NOT NULL,
    categorie VARCHAR(100) DEFAULT NULL,
    date_crea DATETIME NOT NULL,
    date_limite DATE DEFAULT NULL,
    id_client INT NOT NULL,
    statut ENUM('ouvert', 'en_cours', 'terminé', 'annulé') NOT NULL DEFAULT 'ouvert',
    FOREIGN KEY (id_client) REFERENCES CLIENT(id) ON DELETE CASCADE
);

CREATE TABLE PROPOSITION (
    id_proposition INT PRIMARY KEY AUTO_INCREMENT,
    id_freelancer INT NOT NULL,
    id_projet INT NOT NULL,
    prix_souhaité DECIMAL(10,2) NOT NULL,
    message TEXT NOT NULL,
    date_proposition DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('en_attente', 'acceptée', 'refusée') NOT NULL DEFAULT 'en_attente',
    delai INT DEFAULT NULL,
    FOREIGN KEY (id_freelancer) REFERENCES FREELANCER(id) ON DELETE CASCADE,
    FOREIGN KEY (id_projet) REFERENCES PROJET(id_projet) ON DELETE CASCADE
);

CREATE TABLE EMBAUCHE (
    id_embauche INT PRIMARY KEY AUTO_INCREMENT,
    id_projet INT NOT NULL,
    id_freelancer INT NOT NULL,
    date_embauche DATETIME NOT NULL,
    date_fin DATETIME DEFAULT NULL,
    montant_final DECIMAL(10,2) DEFAULT NULL,
    FOREIGN KEY (id_projet) REFERENCES PROJET(id_projet) ON DELETE CASCADE,
    FOREIGN KEY (id_freelancer) REFERENCES FREELANCER(id) ON DELETE CASCADE
);

CREATE TABLE CONVERSATION (
    id_conversation INT PRIMARY KEY AUTO_INCREMENT,
    id_client INT NOT NULL,
    id_freelancer INT NOT NULL,
    date_creation DATETIME NOT NULL,
    date_derniere_activite DATETIME DEFAULT NULL,
    FOREIGN KEY (id_client) REFERENCES CLIENT(id) ON DELETE CASCADE,
    FOREIGN KEY (id_freelancer) REFERENCES FREELANCER(id) ON DELETE CASCADE
);

CREATE TABLE MESSAGE (
    id_message INT PRIMARY KEY AUTO_INCREMENT,
    id_conversation INT NOT NULL,
    id_sender INT NOT NULL,
    contenu TEXT NOT NULL,
    date_envoi DATETIME NOT NULL,
    lu BOOLEAN NOT NULL DEFAULT 0,
    FOREIGN KEY (id_conversation) REFERENCES CONVERSATION(id_conversation) ON DELETE CASCADE,
    FOREIGN KEY (id_sender) REFERENCES UTILISATEUR(id_utilisateur) ON DELETE CASCADE
);

CREATE TABLE EVALUATION (
    id_evaluation INT PRIMARY KEY AUTO_INCREMENT,
    id_client INT NOT NULL,
    id_freelancer INT NOT NULL,
    note INT NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire TEXT DEFAULT NULL,
    date_evaluation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES CLIENT(id) ON DELETE CASCADE,
    FOREIGN KEY (id_freelancer) REFERENCES FREELANCER(id) ON DELETE CASCADE
);

CREATE TABLE MEDIA (
    id_media INT PRIMARY KEY AUTO_INCREMENT,
    nom_fichier VARCHAR(255) NOT NULL,
    type_fichier VARCHAR(100) NOT NULL,
    taille INT NOT NULL,
    date_upload DATETIME NOT NULL,
    id_projet INT DEFAULT NULL,
    id_proposition INT DEFAULT NULL,
    FOREIGN KEY (id_projet) REFERENCES PROJET(id_projet) ON DELETE CASCADE,
    FOREIGN KEY (id_proposition) REFERENCES PROPOSITION(id_proposition) ON DELETE CASCADE
);


INSERT INTO ROLE (nom) VALUES ('Admin'), ('Client'), ('Freelancer');


INSERT INTO UTILISATEUR (
    nom, 
    prenom, 
    email, 
    mot_de_passe, 
    id_role, 
    statut, 
    date_inscription
) VALUES (
    'Admin', 
    'Système', 
    'admin@supfreelance.mr', 
    '$2y$10$wzPzZQnfG2Wl.wjBNlYR/OrCypQ1mwWOpA/jMhvnonUNElA.kP3K.',
    1, 
    'actif', 
    NOW()
);
