<?php

declare(strict_types=1);

namespace WCDO\Controllers;

use InvalidArgumentException;
use WCDO\Http\Response;
use WCDO\Services\AuthService;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * POST /api/auth/register
     * 
     * Crée un nouveau compte client.
     * 
     * Body JSON attendu :
     * {
     *   "prenom": "Alice",
     *   "nom": "Durand",
     *   "email": "alice@mail.fr",
     *   "mot_de_passe": "secret123"
     * }
     * 
     * Validations :
     *   - Tous les champs requis
     *   - email UNIQUE en BDD (erreur 400 si déjà inscrit)
     *   - mot_de_passe hachée en bcrypt avant insertion
     * 
     * Réponse succès : HTTP 200
     * {
     *   "success": true,
     *   "data": {
     *     "client": { "id": 5, "prenom": "Alice", "nom": "Durand", "email": "alice@mail.fr", "points_fidelite": 0, ... }
     *   }
     * }
     * 
     * Réponse erreur : HTTP 400 (email déjà inscrit, champ manquant)
     */
    public function register(): never
    {
        // Lire le body JSON
        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        // Valider les champs requis
        $prenom = trim($body['prenom'] ?? '');
        $nom = trim($body['nom'] ?? '');
        $email = trim($body['email'] ?? '');
        $motDePasse = $body['mot_de_passe'] ?? '';

        if (empty($prenom)) {
            Response::error('prenom requis', 400);
        }
        if (empty($nom)) {
            Response::error('nom requis', 400);
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('email invalide', 400);
        }
        if (empty($motDePasse) || strlen($motDePasse) < 6) {
            Response::error('mot_de_passe requis (min 6 caractères)', 400);
        }

        try {
            // Créer le compte via le Service
            $client = $this->authService->register($prenom, $nom, $email, $motDePasse);

            Response::success([
                'client' => $client->toArray(),
                'message' => 'Inscription réussie',
            ]);

        } catch (InvalidArgumentException $e) {
            // Email déjà inscrit
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/auth/login
     * 
     * Connecte un client existant (démarre une session).
     * 
     * Body JSON attendu :
     * {
     *   "email": "alice@mail.fr",
     *   "mot_de_passe": "secret123"
     * }
     * 
     * Validations :
     *   - email + mot_de_passe corrects
     *   - Message d'erreur vague pour éviter user-enumeration
     * 
     * Réponse succès : HTTP 200 + Set-Cookie PHPSESSID
     * {
     *   "success": true,
     *   "data": {
     *     "client": { "id": 5, "prenom": "Alice", ... }
     *   }
     * }
     * 
     * Réponse erreur : HTTP 401
     * {
     *   "success": false,
     *   "error": "Email ou mot de passe incorrect"
     * }
     */
    public function login(): never
    {
        // Session obligatoire pour stocker client_id
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Lire le body JSON
        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        // Valider les champs requis
        $email = trim($body['email'] ?? '');
        $motDePasse = $body['mot_de_passe'] ?? '';

        if (empty($email) || empty($motDePasse)) {
            Response::error('email et mot_de_passe requis', 400);
        }

        try {
            // Vérifier les identifiants via le Service
            $client = $this->authService->login($email, $motDePasse);

            Response::success([
                'client' => $client->toArray(),
                'message' => 'Connexion réussie',
            ]);

        } catch (InvalidArgumentException $e) {
            // Identifiants invalides → message vague (anti user-enumeration)
            // On retourne 401 Unauthorized au lieu de 400
            Response::unauthorized('Email ou mot de passe incorrect');
        }
    }

    /**
     * POST /api/auth/logout
     * 
     * Déconnecte le client (détruit la session).
     * 
     * Réponse succès : HTTP 200
     * {
     *   "success": true,
     *   "data": {
     *     "message": "Déconnexion réussie"
     *   }
     * }
     */
    public function logout(): never
    {
        // Session optionnelle (peut déjà être fermée)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Détruire la session (supprime tout : client_id, panier_id, etc.)
        session_destroy();

        Response::success([
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * GET /api/auth/me
     * 
     * Retourne le profil du client connecté (via session).
     * 
     * Cas d'erreur :
     *   - Si pas connecté → 401 Unauthorized
     * 
     * Réponse succès : HTTP 200
     * {
     *   "success": true,
     *   "data": {
     *     "client": { "id": 5, "prenom": "Alice", "nom": "Durand", "email": "alice@mail.fr", "points_fidelite": 42, ... }
     *   }
     * }
     * 
     * Réponse erreur : HTTP 401
     * {
     *   "success": false,
     *   "error": "Non connecté"
     * }
     */
    public function me(): never
    {
        // Session optionnelle
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Récupérer le client depuis la session
        $client = $this->authService->getClientConnecte();

        if ($client === null) {
            Response::unauthorized('Non connecté');
        }

        Response::success([
            'client' => $client->toArray(),
        ]);
    }
}
