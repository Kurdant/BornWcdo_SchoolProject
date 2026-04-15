Bloc 2 - Back End
Sujet d’examen : Bloc 2 - Développement back-end
Objectif : 
Développer l’interface d'administration (back-office) pour gérer l'ensemble des  données et traiter les commandes. 

Le back-office de l'application de borne de commande Wacdo vise à gérer efficacement et de manière sécurisée les données de l'application, les commandes des clients et la gestion des utilisateurs en fonction de leurs rôles...

Technologies et Outils/Langage de programmation : 
Utilisation d’un ou plusieurs langages serveur orienté objet pour le développement du Back-end 

Base de Données : 
Création & utilisation d'une base de données (par exemple, MySQL, PostgreSQL) pour gérer l'ensemble des données de l'application 

Sécurité : 
Mise en oeuvre de mesures de sécurité robustes 

API : 
Création d'une API pour communiquer avec le Front-end de l'application : 

Fournir la liste détaillée des menus 

Fournir la liste détaillée des produits (éventuellement par catégorie) 

Recevoir le détail d'une commande 

Gestion des Paiements : 
Pas de gestion des paiements, les tickets de commande sont gérés par un numéro d’identification renseigné à la commande. 

Fonctionnalités du Back-office : 
Le Back-end de l'application doit intégrer les fonctionnalités suivantes : 

Gestion des Utilisateurs : 
Mise en place des comptes utilisateurs (utilisateurs internes), avec la prise en compte des autorisations : 

Compte Administration : gestion des données et des utilisateurs 

Compte Préparation de commandes : peut voir les commandes et les valider 

Compte Accueil : peut saisir une commande (au comptoir ou prise par téléphone), et remettre une commande à un client 

Gestion des Produits et des menus : 
Back-office de gestion des informations sur les produits, de leur disponibilité, y compris les noms, descriptions, prix et images. 

Gestion des menus (et de leur composition, avec les options disponibles) 

Ces opérations sont réalisées par un utilisateur ayant les droits "administration" 

Saisie de commandes : 
Les équipiers travaillant au comptoir ou au centre d'appel peuvent saisir des commandes 

Les équipiers peuvent aussi remettre une commande à un client (et donc la déclarer livrée) 

Préparation de commandes : 
Les superviseurs des équipiers chargés de la confection des commandes ont accès à la liste des commandes à préparer (triées par heure de livraison croissante) 

Quand une commande est prête, elle est déclarée "préparée" (ce qui permet aux équipiers au comptoir ou en salle de la remettre au client) 

Sécurité : 
Sécurisation de l’application 

Protection des données 

Sessions et authentification sécurisées. 

Intégration de l'API : 
Mise en place de l'API pour communiquer avec le Front-end de l'application. 

Tests et Validation : 
Avant le déploiement de l'application, une série de tests devra être effectuée pour s'assurer que le Back-end répond aux spécifications mentionnées ci-dessus y compris pour la sécurité et la bonne communication avec le front-end. 

Les éléments attendus par les jurys : 
Le candidat conceptualise et développe from scratch (Depuis une page blanche sans code préconstruit) l’application demandée et sa base de données, à l'aide d’un langage de programmation serveur. 

L’application est développée en utilisant la programmation objet, incluant l'héritage et en utilisant une architecture de type MVC. 

Le candidat présente son travail aux jurys. Il argumente son modèle de données et ses schémas conceptuels. Le candidat doit être en mesure de modifier son code en direct selon les demandes imprévues des membres du jury. 

Livrables à fournir : 
Les jurys se basent sur les critères d’évaluation du référentiel du titre 37805 pour vous noter. Le référentiel est téléchargeable à cette adresse : https://www.francecompetences.fr/recherche/rncp/37805

Vous pourrez ainsi vérifier les critères d’évaluation sur lesquels les jurys se baseront lors de votre soutenance pour évaluer vos travaux

Les livrables attendus sont donc les suivants : 
Les schémas conceptuels et physiques du modèle de données 

Les schémas fonctionnels de l’application 

La base de données de l’application

L’application fonctionnelle déployée sur le serveur en mettant à disposition les sources et documents sur un dépôt Github. 