------ Rapport Technique et Documentation du Projet ---------

Ce document détaillé explique l'architecture et le rôle de chaque composant.

Rapport de Projet et Documentation Technique - SupFreelance

1. Introduction
Ce document fournit une description technique complète de la plateforme SupFreelance. L'objectif est de détailler l'architecture, la structure des fichiers, le rôle de chaque composant, ainsi que les flux de travail principaux, notamment les systèmes de paiement et de litiges.

2. Architecture du Système
Le projet suit une architecture simplifiée de type Modèle-Vue-Contrôleur (MVC) :

Modèle : La logique d'accès aux données est gérée par la classe Database dans config/database.php et les requêtes SQL directes dans les fichiers de l'API. Les fonctions utilitaires dans includes/functions.php servent également de support au modèle.
Vue : Les fichiers PHP situés dans le dossier /pages sont responsables de l'affichage et de la présentation des données (HTML). Ils contiennent une logique minimale, principalement pour l'affichage conditionnel.
Contrôleur : Le dossier /api agit comme la couche de contrôle. Ces fichiers reçoivent les requêtes des utilisateurs (souvent via AJAX), traitent les données, interagissent avec la base de données, et renvoient des réponses (généralement en JSON).

3. Structure des Dossiers et Fichiers
Racine du projet (/)
index.php: Page d'accueil principale de la plateforme. C'est le point d'entrée pour les visiteurs.
.htaccess: (Optionnel) Utilisé pour la réécriture d'URL (URL propres) et pour des configurations de sécurité.
Dossier api/ (Contrôleurs)
Ce dossier contient la logique métier principale de l'application.

paiements.php: Gère toutes les opérations liées aux paiements : soumission des preuves de virement par les clients et traitement (acceptation/refus) par les administrateurs.
litiges.php: Gère le système de signalement. Traite la création de nouveaux litiges par les utilisateurs et leur résolution par les administrateurs.
projets.php: Gère la création, la modification, et la suppression des projets.
propositions.php: Traite la soumission et l'acceptation des offres des freelances.
messages.php: Gère l'envoi et la réception des messages de la messagerie interne.
utilisateurs.php: Gère l'inscription, la connexion et la mise à jour des profils utilisateurs.
admin.php: Gère les actions spécifiques à l'administration (ex: suspendre un utilisateur).
Dossier assets/ (Ressources)
Contient tous les fichiers statiques.

css/style.css: Feuille de style principale pour la personnalisation de l'apparence du site.
js/main.js: Fichier JavaScript principal contenant les interactions AJAX, la validation des formulaires et autres scripts côté client.
img/: Contient les images statiques de l'interface (logos, icônes, etc.).
Dossier config/ (Configuration)
database.php: Fichier crucial contenant les informations de connexion à la base de données (hôte, nom d'utilisateur, mot de passe, nom de la base) et la classe PHP Database pour établir la connexion PDO.
Dossier includes/ (Fichiers Partagés)
Contient les morceaux de code PHP réutilisés sur plusieurs pages.

header.php: En-tête de toutes les pages. Gère le début de la session, inclut les fichiers CSS, affiche la barre de navigation et les messages flash (notifications).
footer.php: Pied de page de toutes les pages. Affiche les liens utiles, les informations de contact, et inclut les fichiers JavaScript.
functions.php: Fichier utilitaire contenant des fonctions PHP globales (ex: isLoggedIn(), isAdmin(), sanitize(), formatDate()).
Dossier pages/ (Vues)
Contient les pages web visibles par les utilisateurs.

inscription.php, connexion.php: Pages pour l'authentification.
profil.php: Page de gestion du profil utilisateur.
creer-projet.php: Formulaire de création d'un nouveau projet.
paiement-projet.php: Page où le client soumet sa preuve de paiement pour un projet.
projets.php: Affiche la liste des projets "ouverts" aux freelances.
mes-projets.php: Affiche la liste des projets créés par le client connecté.
projet-details.php: Affiche les détails complets d'un projet, y compris les offres, et les boutons d'action (livrer, accepter, signaler un litige).
messages.php: Interface de la messagerie.
recharger-solde.php: Page où le client peut initier une demande de rechargement de son solde.
Dossier pages/admin/ (Vues Administratives)
Section privée réservée aux administrateurs.

dashboard.php: Page d'accueil de l'administration avec des statistiques clés.
paiements.php: Interface pour vérifier et traiter les demandes de paiement en attente.
litiges.php: Liste des litiges ouverts nécessitant une intervention.
litige-details.php: Page pour examiner un litige en détail (motif, conversation) et prendre une décision.
utilisateurs.php, projets.php: Pages de gestion et de modération des utilisateurs et des projets.
Dossier scripts/ (Tâches Automatisées)
auto_release_payments.php: Script conçu pour être exécuté par une tâche Cron. Il vérifie les projets livrés dont le délai de validation par le client a expiré et libère automatiquement les fonds au freelance.
Dossier uploads/ (Fichiers Téléversés)
Dossier sécurisé pour stocker les fichiers envoyés par les utilisateurs.

preuves/: Stocke les captures d'écran des virements bancaires.
profils/: Stocke les photos de profil des utilisateurs.

4. Flux de Travail Détaillés
A. Flux de Paiement et de Garantie (Escrow)
Création : Le client remplit le formulaire sur creer-projet.php. L'API (api/projets.php) crée le projet avec le statut en_attente_paiement.
Paiement : Le client est redirigé vers paiement-projet.php. Il effectue le virement externe, puis soumet le formulaire avec la preuve. L'API (api/paiements.php) enregistre la demande.
Vérification : L'administrateur, sur pages/admin/paiements.php, voit la demande. Il vérifie la preuve et clique sur "Accepter".
Activation : L'API (api/paiements.php) met à jour le statut du projet à ouvert. Le projet devient visible pour les freelances sur pages/projets.php.
Embauche : Le client accepte une offre sur projet-details.php. L'API (api/propositions.php) vérifie le solde du client, déduit le montant, et crée une transaction bloque. Le statut du projet passe à en_cours.
Livraison : Le freelance, sur projet-details.php, clique sur "Livrer le travail". Le statut du projet passe à livré.
Libération :
Manuelle : Le client confirme la réception sur projet-details.php. L'API libère les fonds au freelance (après déduction de la commission de 20%) et met à jour la transaction à libere.
Automatique : Le script auto_release_payments.php (via Cron Job) exécute la même logique de libération si le client n'a pas réagi après 5 jours.
B. Flux de Gestion des Litiges
Signalement : Sur un projet en_cours ou livré, un utilisateur clique sur "Signaler un problème" sur projet-details.php.
Création : Un formulaire modal s'ouvre. L'utilisateur décrit le problème. L'API (api/litiges.php) crée un enregistrement dans la table LITIGE et change le statut du projet à en_litige.
Gestion : L'administrateur voit le nouveau litige sur pages/admin/litiges.php et accède à pages/admin/litige-details.php.
Résolution : L'admin examine le motif, la conversation, et prend une décision (en faveur du client ou du freelance). Il soumet sa décision via le formulaire.
Action : L'API (api/litiges.php) met à jour le statut du litige et déclenche l'action correspondante (remboursement au client ou paiement au freelance).

5. Conclusion
Cette architecture modulaire et ces flux de travail bien définis garantissent une plateforme robuste, sécurisée et facile à maintenir. L'implémentation de ces systèmes de paiement et de litiges est essentielle pour créer un environnement de confiance et encourager l'utilisation de la plateforme SupFreelance.