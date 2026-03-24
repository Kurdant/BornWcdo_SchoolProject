<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use WCDO\Http\Router;
use WCDO\Http\Response;
use WCDO\Controllers\CatalogueController;
use WCDO\Controllers\AuthController;
use WCDO\Controllers\PanierController;
use WCDO\Controllers\CommandeController;
use WCDO\Controllers\AdminController;

// Le navigateur envoie d'abord une requête OPTIONS avant tout POST/PUT/DELETE cross-origin
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

// Une seule instance par Controller
$catalogue = new CatalogueController();
$panier    = new PanierController();
$commande  = new CommandeController();
$auth      = new AuthController();
$admin     = new AdminController();

$router = new Router();

// Santé
$router->get('/api/health', fn() => Response::json(['status' => 'ok']));

// Catalogue
$router->get('/api/categories',                     [$catalogue, 'getCategories']);
$router->get('/api/produits',                       [$catalogue, 'getProduits']);
$router->get('/api/produits/(?P<id>[^/]+)',         [$catalogue, 'getProduit']);
$router->get('/api/boissons',                       [$catalogue, 'getBoissons']);
$router->get('/api/tailles-boissons',               [$catalogue, 'getTaillesBoissons']);
$router->get('/api/sauces',                         [$catalogue, 'getSauces']);

// Panier
$router->get('/api/panier',                         [$panier, 'getPanier']);
$router->post('/api/panier/ajouter',                [$panier, 'ajouter']);
$router->delete('/api/panier/ligne/(?P<id>[^/]+)',  [$panier, 'supprimerLigne']);
$router->delete('/api/panier',                      [$panier, 'vider']);

// Commande
$router->post('/api/commande',                      [$commande, 'passer']);
$router->get('/api/commande/(?P<numero>[^/]+)',      [$commande, 'getByNumero']);

// Auth client
$router->post('/api/auth/register', [$auth, 'register']);
$router->post('/api/auth/login',    [$auth, 'login']);
$router->post('/api/auth/logout',   [$auth, 'logout']);
$router->get('/api/auth/me',        [$auth, 'me']);

// Admin
$router->post('/api/admin/login',                       [$admin, 'login']);
$router->post('/api/admin/logout',                      [$admin, 'logout']);
$router->get('/api/admin/produits',                     [$admin, 'getProduits']);
$router->post('/api/admin/produits',                    [$admin, 'createProduit']);
$router->put('/api/admin/produits/(?P<id>[^/]+)',       [$admin, 'updateProduit']);
$router->delete('/api/admin/produits/(?P<id>[^/]+)',    [$admin, 'deleteProduit']);
$router->get('/api/admin/commandes',                    [$admin, 'getCommandes']);

// Handler global : toute exception non rattrapée remonte ici
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 500);
}