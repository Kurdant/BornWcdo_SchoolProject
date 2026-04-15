<?php

declare(strict_types=1);

namespace WCDO\Controllers;

use InvalidArgumentException;
use WCDO\Exceptions\StockInsuffisantException;
use WCDO\Http\Response;
use WCDO\Services\CommandeService;

class CommandeController
{
    private CommandeService $commandeService;

    public function __construct()
    {
        $this->commandeService = new CommandeService();
    }

    /**
     * POST /api/commande
     * 
     * PASSE UNE COMMANDE : transforme le panier en commande avec transaction SQL atomique.
     * C'est LE point de non-retour : après cet appel, la commande est enregistrée (ou complètement annulée).
     * 
     * PRÉREQUIS : Le paiement doit être VALIDÉ avant cet appel.
     * Dans une vraie app, on appellerait Stripe/Paypal en amont pour verrouiller le paiement.
     * 
     * Body JSON attendu :
     * {
     *   "mode_paiement": "carte",           ← "carte" ou "especes"
     *   "type_commande": "a_emporter",     ← "a_emporter" ou "sur_place"
     *   "client_id": 5                      ← OPTIONNEL (null si anonyme)
     * }
     * 
     * Validations :
     *   - Panier non vide
     *   - mode_paiement valide (ENUM : carte, especes)
     *   - type_commande valide (ENUM : a_emporter, sur_place)
     *   - Stock suffisant pour chaque produit (RG-001, RG-008)
     * 
     * Réponse succès : HTTP 200 (ou 201 CREATED)
     * {
     *   "success": true,
     *   "data": {
     *     "commande": {
     *       "id": 1,
     *       "numero_commande": "CMD-20260324-A7B3F",
     *       "numero_chevalet": 42,
     *       "montant_total": 12.50,
     *       "client_id": 5,
     *       ...
     *     },
     *     "lignes": [ ... ],
     *     "message": "Commande validée ! Numéro de chevalet : 042"
     *   }
     * }
     * 
     * Réponse erreur 400 : panier vide, champs invalides
     * Réponse erreur 409 : stock insuffisant (StockInsuffisantException)
     * Réponse erreur 500 : erreur serveur
     */
    public function passer(): never
    {
        // Session optionnelle pour retrouver le session_id
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Lire le body JSON
        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        // Valider les champs requis
        $modePaiement = trim($body['mode_paiement'] ?? '');
        $typeCommande = trim($body['type_commande'] ?? '');
        $clientId = $body['client_id'] ?? null;

        // Valider mode_paiement (ENUM)
        if (!in_array($modePaiement, ['carte', 'especes'])) {
            Response::error('mode_paiement invalide (carte ou especes)', 400);
        }

        // Valider type_commande (ENUM)
        if (!in_array($typeCommande, ['a_emporter', 'sur_place'])) {
            Response::error('type_commande invalide (a_emporter ou sur_place)', 400);
        }

        // Convertir client_id en int si fourni et numérique
        if ($clientId !== null && is_numeric($clientId)) {
            $clientId = (int) $clientId;
        } elseif ($clientId !== null) {
            Response::error('client_id doit être numérique', 400);
        }

        $sessionId = session_id();

        try {
            // APPEL AU SERVICE : transaction SQL complète
            // RG-008 : atomicité garantie (tout ou rien)
            // RG-001, RG-005, RG-006 : toutes gérées par le Service
            $result = $this->commandeService->creer(
                sessionId: $sessionId,
                modePaiement: $modePaiement,
                typeCommande: $typeCommande,
                clientId: $clientId
            );

            // Formater le numéro de chevalet avec leading zeros (001, 042, 999)
            $numeroChevalet = str_pad(
                (string) $result['commande']['numero_chevalet'],
                3,
                '0',
                STR_PAD_LEFT
            );

            Response::success([
                'commande' => $result['commande'],
                'lignes' => $result['lignes'],
                'message' => "Commande validée ! Numéro de chevalet : {$numeroChevalet}",
            ]);

        } catch (StockInsuffisantException $e) {
            // RG-008 : stock insuffisant = transaction rollback
            // Retourne 409 Conflict (ressource en état incohérent)
            Response::error(
                "Stock insuffisant pour le produit {$e->getProduitId()}. " .
                "Demandé: {$e->getStockDemande()}, disponible: {$e->getStockDisponible()}",
                409
            );

        } catch (InvalidArgumentException $e) {
            // Panier vide, autre erreur métier
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/commande/{numero}
     * 
     * Récupère UNE COMMANDE par son numéro unique.
     * Le Router passe le numéro via $params['numero'].
     * 
     * Cas d'usage : un client reçoit un numéro de chevalet,
     * il veut consulter sa commande (voir ce qu'il a commandé, le montant, etc.)
     * 
     * Réponse succès : HTTP 200
     * {
     *   "success": true,
     *   "data": {
     *     "commande": { "id": 1, "numero_commande": "CMD-20260324-A7B3F", ... },
     *     "lignes": [
     *       { "id_produit": 2, "quantite": 1, "prix_unitaire": 5.40, ... },
     *       ...
     *     ]
     *   }
     * }
     * 
     * Réponse erreur 404 : commande introuvable
     */
    public function getByNumero(array $params): never
    {
        // Valider le numéro
        if (!isset($params['numero']) || empty($params['numero'])) {
            Response::error('numero commande invalide', 400);
        }

        $numero = trim((string) $params['numero']);

        // Récupérer la commande via le Service
        $result = $this->commandeService->getByNumero($numero);

        if ($result === null) {
            Response::notFound('Commande introuvable');
        }

        Response::success([
            'commande' => $result['commande'],
            'lignes' => $result['lignes'],
        ]);
    }

    /**
     * GET /api/commande (BONUS : non documenté dans la spec, mais utile pour admin)
     * 
     * Récupère TOUTES les commandes (ADMIN ONLY).
     * À implémenter plus tard si nécessaire.
     */
}
