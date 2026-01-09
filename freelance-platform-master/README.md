


# SupFreelance - Plateforme de Freelancing Sécurisée

SupFreelance est une plateforme web complète conçue pour mettre en relation des clients et des freelances de manière sécurisée et efficace. Le projet intègre un système de paiement par garantie (escrow) adapté aux virements bancaires manuels, une messagerie interne, et un système de gestion des litiges pour assurer la confiance entre les deux parties.

## Fonctionnalités Principales

-   **Gestion des Utilisateurs :** Inscription, connexion, et profils distincts pour clients et freelances.
-   **Gestion de Projets :** Publication de projets par les clients avec budget et description.
-   **Système de Propositions :** Les freelances peuvent soumettre des offres pour les projets.
-   **Système de Paiement Sécurisé (Escrow) :**
    -   Le client paie le montant du projet avant l'embauche.
    -   Les fonds sont bloqués par la plateforme.
    -   Vérification manuelle des preuves de virement par un administrateur.
    -   Libération des fonds au freelance uniquement après la validation du client ou l'expiration d'un délai.
-   **Messagerie Interne :** Communication directe et sécurisée entre le client et le freelance embauché.
-   **Système de Litiges :** Un mécanisme de signalement permet aux utilisateurs de rapporter un problème, qui sera ensuite arbitré par un administrateur.
-   **Tableau de Bord Administratif :** Interface complète pour la gestion des utilisateurs, des projets, des paiements et des litiges.

## Prérequis

-   PHP 7.4 ou supérieur
-   Serveur Web (Apache, Nginx)
-   MySQL 5.7 ou supérieur


## Installation

1.  **Cloner le dépôt**
    
    git clone https://github.com/lemana12/freelance-platform
    cd SupFreelancer
    
2.  **Base de données**
    -   Créez une base de données MySQL.
    -   Importez le fichier `supfreelance.sql` fourni dans votre base de données.

3.  **Configuration**
    -   Renommez le fichier `config/database.example.php` if exist en `config/database.php`.
    -   Modifiez les informations de connexion à la base de données dans ce fichier.

4.  **Permissions**
    -   Assurez-vous que le serveur web a les droits d'écriture sur le touts de dossiers

5.  **Accéder au site**
    -   Ouvrez votre navigateur et accédez à l'URL de votre projet (ex: `http://localhost/supfreelance`).

## Structure du Projet
/
├── api/ # Contrôleurs : logique métier et réponses AJAX
├── assets/ # Fichiers statiques (CSS, JS, images)
├── config/ # Configuration (base de données)
├── includes/ # Éléments PHP partagés (header, footer, fonctions)
├── pages/ # Vues : pages visibles par l'utilisateur
│ └── admin/ # Vues : section administrative
├── scripts/ # Scripts automatisés (Cron Jobs)
├── uploads/ # Dossier pour les fichiers téléversés
└── index.php # Point d'entrée principal


## Licence
Ce projet est sous licence MIT.
