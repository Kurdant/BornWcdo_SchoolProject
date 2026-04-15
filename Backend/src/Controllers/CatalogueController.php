<?php

declare(strict_types=1);

namespace WCDO\Controllers;

use WCDO\Http\Response;
use WCDO\Repositories\CategorieRepository;
use WCDO\Repositories\ProduitRepository;
use WCDO\Repositories\SauceRepository;
use WCDO\Repositories\TailleBoissonRepository;

class CatalogueController
{
    private CategorieRepository $categorieRepo;
    private ProduitRepository $produitRepo;
    private SauceRepository $sauceRepo;
    private TailleBoissonRepository $tailleBoissonRepo;

    public function __construct()
    {
        $this->categorieRepo      = new CategorieRepository();
        $this->produitRepo        = new ProduitRepository();
        $this->sauceRepo          = new SauceRepository();
        $this->tailleBoissonRepo  = new TailleBoissonRepository();
    }

    /**
     * GET /api/categories
     * 
     * Retourne toutes les catégories de produits.
     * Utilisé au démarrage du front pour afficher le menu par catégories.
     * 
     * Réponse : HTTP 200 + JSON
     * {
     *   "success": true,
     *   "data": [
     *     { "id": 1, "nom": "Menus" },
     *     { "id": 2, "nom": "Sandwiches" },
     *     ...
     *   ]
     * }
     */
    public function getCategories(): never
    {
        $categories = $this->categorieRepo->findAll();
        $data = array_map(fn($cat) => $cat->toArray(), $categories);
        Response::success($data);
    }

    /**
     * GET /api/produits
     * 
     * Retourne TOUS les produits du catalogue.
     * 
     * Réponse : HTTP 200 + JSON
     * {
     *   "success": true,
     *   "data": [
     *     { "id": 1, "nom": "Big Mac", "prix": 5.40, "stock": 50, ... },
     *     ...
     *   ]
     * }
     */
    public function getProduits(): never
    {
        $produits = $this->produitRepo->findAll();
        $data = array_map(fn($prod) => $prod->toArray(), $produits);
        Response::success($data);
    }

    /**
     * GET /api/produits/{id}
     * 
     * Retourne UN produit par son ID.
     * Le Router passe l'ID via $params['id'].
     * 
     * Cas d'erreur : si produit inexistant → 404
     * 
     * Réponse succès : HTTP 200
     * {
     *   "success": true,
     *   "data": { "id": 1, "nom": "Big Mac", "prix": 5.40, "stock": 50, ... }
     * }
     */
    public function getProduit(array $params): never
    {
        // Valider que l'ID est présent et numérique
        if (!isset($params['id']) || !is_numeric($params['id'])) {
            Response::error('ID produit invalide', 400);
        }

        $id = (int) $params['id'];
        $produit = $this->produitRepo->findById($id);

        // Si le produit n'existe pas
        if ($produit === null) {
            Response::notFound('Produit introuvable');
        }

        Response::success($produit->toArray());
    }

    /**
     * GET /api/boissons
     * 
     * Retourne les BOISSONS (produits de la catégorie "Boissons Froides").
     * Cas d'usage : frontend affiche la section "Boissons" avec les produits disponibles.
     * 
     * COMMENT IDENTIFIER LES BOISSONS ?
     * Par catégorie ID = 5 (cf. seed BDD : Boissons Froides).
     * En production, on pourrait ajouter une colonne "est_boisson" pour plus de flexibilité.
     * 
     * Réponse : HTTP 200
     * {
     *   "success": true,
     *   "data": [
     *     { "id": 7, "nom": "Coca-Cola", "prix": 2.20, ... },
     *     ...
     *   ]
     * }
     */
    public function getBoissons(): never
    {
        // Catégorie 5 = Boissons
        $boissons = $this->produitRepo->findByCategorie(5);
        $data = array_map(fn($prod) => $prod->toArray(), $boissons);
        Response::success($data);
    }

    /**
     * GET /api/tailles-boissons
     * 
     * Retourne les tailles de boissons disponibles avec leurs prix.
     * Utilisé par le frontend : quand on ajoute une boisson au panier,
     * on propose "30cl (0€)" ou "50cl (+0,50€)".
     * 
     * RG-003 : supplement_prix = +0,50€ pour 50cl (ou autre)
     * 
     * Réponse : HTTP 200
     * {
     *   "success": true,
     *   "data": [
     *     { "id": 1, "nom": "30cl", "volume": 30, "supplement_prix": 0.00 },
     *     { "id": 2, "nom": "50cl", "volume": 50, "supplement_prix": 0.50 }
     *   ]
     * }
     */
    public function getTaillesBoissons(): never
    {
        $tailles = $this->tailleBoissonRepo->findAll();
        $data = array_map(fn($t) => $t->toArray(), $tailles);
        Response::success($data);
    }

    /**
     * GET /api/sauces
     * 
     * Retourne toutes les sauces disponibles.
     * Utilisé par le frontend : quand on ajoute un burger/menu,
     * on propose un choix parmi les sauces (max 2 par menu, RG-002).
     * 
     * Réponse : HTTP 200
     * {
     *   "success": true,
     *   "data": [
     *     { "id": 1, "nom": "Barbecue" },
     *     { "id": 2, "nom": "Moutarde" },
     *     ...
     *   ]
     * }
     */
    public function getSauces(): never
    {
        $sauces = $this->sauceRepo->findAll();
        $data = array_map(fn($s) => $s->toArray(), $sauces);
        Response::success($data);
    }
}
