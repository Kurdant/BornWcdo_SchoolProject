<?php

declare(strict_types=1);

namespace WCDO\Controllers;

use InvalidArgumentException;
use WCDO\Exceptions\StockInsuffisantException;
use WCDO\Http\Response;
use WCDO\Services\PanierService;

class PanierController
{
    private PanierService $panierService;

    public function __construct()
    {
        $this->panierService = new PanierService();
    }

    /**
     * GET /api/panier
     * 
     * Retourne le panier courant (lié à la session).
     * Cas d'usage : frontend charge la page "Mon panier" → affiche les articles + total.
     * 
     * Session identifie le panier (même si client anonyme).
     * Si aucun panier existant → retourne panier vide.
     * 
     * Réponse : HTTP 200 + JSON
     * {
     *   "success": true,
     *   "data": {
     *     "panier": { "id": 3, "session_id": "abc123...", "client_id": null, ... },
     *     "lignes": [
     *       { "id": 1, "id_produit": 2, "quantite": 1, "prix_unitaire": 5.40, "sous_total": 5.40, ... },
     *       ...
     *     ],
     *     "total": 12.50
     *   }
     * }
     */
    public function getPanier(): never
    {
        // Démarrer la session si pas déjà fait (par index.php normalement)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionId = session_id();
        $result = $this->panierService->getPanier($sessionId);

        Response::success($result);
    }

    /**
     * POST /api/panier/ajouter
     * 
     * Ajoute UN produit au panier (ou augmente la quantité s'il y est déjà).
     * 
     * Body JSON attendu :
     * {
     *   "id_produit": 2,
     *   "quantite": 1,
     *   "details": {
     *     "sauces": [1, 3],           ← max 2 (RG-002)
     *     "taille_id": 2              ← supplément prix (RG-003)
     *   }
     * }
     * 
     * Validations :
     *   - RG-001 : produit doit exister et avoir du stock
     *   - RG-002 : max 2 sauces par menu
     *   - RG-003 : supplément taille boisson
     * 
     * Réponse succès : HTTP 200 + la nouvelle ligne créée
     * {
     *   "success": true,
     *   "data": {
     *     "ligne": { "id": 5, "id_produit": 2, ... },
     *     "panier": { ... },
     *     "total": 12.50
     *   }
     * }
     * 
     * Réponse erreur : HTTP 400 (validation), 404 (produit inexistant)
     */
    public function ajouter(): never
    {
        // Session obligatoire pour identifier le panier
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Lire le body JSON
        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        // Valider les champs requis
        $idProduit = $body['id_produit'] ?? null;
        $quantite = $body['quantite'] ?? null;
        $details = $body['details'] ?? [];

        if (!isset($idProduit) || !is_numeric($idProduit) || $idProduit <= 0) {
            Response::error('id_produit invalide (doit être > 0)', 400);
        }

        if (!isset($quantite) || !is_numeric($quantite) || $quantite <= 0) {
            Response::error('quantite invalide (doit être > 0)', 400);
        }

        if (!is_array($details)) {
            Response::error('details doit être un objet JSON', 400);
        }

        $sessionId = session_id();
        $clientId = $_SESSION['client_id'] ?? null;

        try {
            // Ajouter au panier via le Service
            // Le Service valide RG-001, RG-002, RG-003
            $ligne = $this->panierService->ajouter(
                sessionId: $sessionId,
                produitId: (int) $idProduit,
                quantite: (int) $quantite,
                details: $details,
                clientId: $clientId ? (int) $clientId : null
            );

            // Récupérer le panier mis à jour
            $panierData = $this->panierService->getPanier($sessionId);

            Response::success([
                'ligne' => $ligne->toArray(),
                'panier' => $panierData['panier'],
                'lignes' => $panierData['lignes'],
                'total' => $panierData['total'],
            ]);

        } catch (StockInsuffisantException $e) {
            // RG-001 : stock insuffisant (déjà au panier + quantité demandée > stock)
            Response::error($e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            // RG-001 (produit indisponible) ou RG-002 (max 2 sauces)
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/panier/ligne/{id}
     * 
     * Supprime UNE LIGNE du panier (pas le produit entier, juste cette entrée).
     * Le Router passe l'ID de la ligne via $params['id'].
     * 
     * Cas d'erreur :
     *   - Si ligne inexistante → 404
     * 
     * Réponse succès : HTTP 200 + le panier mis à jour
     * {
     *   "success": true,
     *   "data": {
     *     "panier": { ... },
     *     "lignes": [ ... ],
     *     "total": 6.80
     *   }
     * }
     */
    public function supprimerLigne(array $params): never
    {
        // Session obligatoire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Valider l'ID de la ligne
        if (!isset($params['id']) || !is_numeric($params['id'])) {
            Response::error('ID ligne invalide', 400);
        }

        $ligneId = (int) $params['id'];
        $sessionId = session_id();

        // Supprimer la ligne
        $success = $this->panierService->supprimerLigne($ligneId);

        if (!$success) {
            Response::notFound('Ligne panier introuvable');
        }

        // Récupérer le panier mis à jour
        $panierData = $this->panierService->getPanier($sessionId);

        Response::success([
            'panier' => $panierData['panier'],
            'lignes' => $panierData['lignes'],
            'total' => $panierData['total'],
        ]);
    }

    /**
     * DELETE /api/panier
     * 
     * Vide COMPLÈTEMENT le panier (supprime toutes les lignes).
     * Cas d'usage : client clique "Vider le panier" ou "Continuer shopping".
     * 
     * Réponse succès : HTTP 200
     * {
     *   "success": true,
     *   "data": {
     *     "message": "Panier vidé"
     *   }
     * }
     */
    public function vider(): never
    {
        // Session obligatoire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionId = session_id();
        $this->panierService->vider($sessionId);

        Response::success([
            'message' => 'Panier vidé',
        ]);
    }
}
