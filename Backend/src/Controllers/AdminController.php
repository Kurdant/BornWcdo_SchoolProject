<?php

declare(strict_types=1);

namespace WCDO\Controllers;

use PDO;
use WCDO\Config\Database;
use WCDO\Entities\Admin;
use WCDO\Entities\Commande;
use WCDO\Entities\Menu;
use WCDO\Entities\PanierLigne;
use WCDO\Http\Response;
use WCDO\Repositories\AdminRepository;
use WCDO\Repositories\CommandeProduitRepository;
use WCDO\Repositories\CommandeRepository;
use WCDO\Repositories\MenuRepository;
use WCDO\Repositories\ProduitRepository;
use WCDO\Services\CommandeAdminService;

class AdminController
{
    private PDO $pdo;
    private AdminRepository $adminRepo;
    private ProduitRepository $produitRepo;
    private CommandeRepository $commandeRepo;
    private CommandeProduitRepository $commandeProduitRepo;
    private MenuRepository $menuRepo;
    private CommandeAdminService $commandeAdminService;

    public function __construct()
    {
        $this->pdo                  = Database::getInstance();
        $this->adminRepo            = new AdminRepository();
        $this->produitRepo          = new ProduitRepository();
        $this->commandeRepo         = new CommandeRepository();
        $this->commandeProduitRepo  = new CommandeProduitRepository();
        $this->menuRepo             = new MenuRepository();
        $this->commandeAdminService = new CommandeAdminService();
    }

    // =========================================================================
    // AUTH ADMIN
    // =========================================================================

    /**
     * POST /api/admin/login
     *
     * Body JSON : { "email": "...", "mot_de_passe": "..." }
     * Réponse   : { admin: {id, nom, email, role}, message }
     * Session   : $_SESSION['admin_id'], $_SESSION['admin_role']
     */
    public function login(): never
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        $email      = trim($body['email'] ?? '');
        $motDePasse = $body['mot_de_passe'] ?? '';

        if (empty($email) || empty($motDePasse)) {
            Response::error('email et mot_de_passe requis', 400);
        }

        $admin = $this->adminRepo->findByEmail($email);

        if ($admin === null || !$admin->verifierMotDePasse($motDePasse)) {
            Response::unauthorized('Email ou mot de passe incorrect');
        }

        $_SESSION['admin_id']   = $admin->getId();
        $_SESSION['admin_role'] = $admin->getRole(); // Stocké pour verifierRole()

        Response::success([
            'admin'   => $admin->toArray(),
            'message' => 'Connexion admin réussie',
        ]);
    }

    /**
     * POST /api/admin/logout
     */
    public function logout(): never
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_destroy();

        Response::success(['message' => 'Déconnexion réussie']);
    }

    // =========================================================================
    // PRODUITS (administration uniquement)
    // =========================================================================

    /**
     * GET /api/admin/produits
     */
    public function getProduits(): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        $produits = $this->produitRepo->findAll();
        Response::success(array_map(fn($p) => $p->toArray(), $produits));
    }

    /**
     * POST /api/admin/produits
     *
     * Body JSON : { nom, description, prix, stock, id_categorie, image? }
     */
    public function createProduit(): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        $nom         = trim($body['nom'] ?? '');
        $description = trim($body['description'] ?? '');
        $prix        = $body['prix'] ?? null;
        $stock       = $body['stock'] ?? null;
        $idCategorie = $body['id_categorie'] ?? null;
        $image       = trim($body['image'] ?? '');

        if (empty($nom)) {
            Response::error('nom requis', 400);
        }
        if (!is_numeric($prix) || (float) $prix <= 0) {
            Response::error('prix invalide (doit être > 0)', 400);
        }
        if (!is_numeric($stock) || (int) $stock < 0) {
            Response::error('stock invalide (doit être >= 0)', 400);
        }
        if (!is_numeric($idCategorie) || (int) $idCategorie <= 0) {
            Response::error('id_categorie invalide', 400);
        }

        $produit = $this->produitRepo->create(
            nom:         $nom,
            description: $description,
            prix:        (float) $prix,
            stock:       (int) $stock,
            categorieId: (int) $idCategorie,
            image:       $image ?: null
        );

        Response::success(['produit' => $produit->toArray(), 'message' => 'Produit créé']);
    }

    /**
     * PUT /api/admin/produits/{id}
     *
     * Body JSON : { nom?, description?, prix?, stock?, id_categorie?, image? }
     * Les champs absents conservent la valeur actuelle en BDD.
     */
    public function updateProduit(array $params): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        if (!isset($params['id']) || !is_numeric($params['id'])) {
            Response::error('ID produit invalide', 400);
        }

        $id      = (int) $params['id'];
        $produit = $this->produitRepo->findById($id);

        if ($produit === null) {
            Response::notFound('Produit introuvable');
        }

        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        // Patch partiel : on garde les valeurs actuelles pour les champs absents
        $nom         = trim($body['nom']         ?? $produit->getNom());
        $description = trim($body['description'] ?? $produit->getDescription() ?? '');
        $prix        = $body['prix']        ?? $produit->getPrix();
        $stock       = $body['stock']       ?? $produit->getStock();
        $idCategorie = $body['id_categorie'] ?? $produit->getCategorieId();
        $image       = trim($body['image']   ?? $produit->getImage() ?? '');

        if (empty($nom)) {
            Response::error('nom requis', 400);
        }
        if (!is_numeric($prix) || (float) $prix <= 0) {
            Response::error('prix invalide (doit être > 0)', 400);
        }
        if (!is_numeric($stock) || (int) $stock < 0) {
            Response::error('stock invalide (doit être >= 0)', 400);
        }
        if (!is_numeric($idCategorie) || (int) $idCategorie <= 0) {
            Response::error('id_categorie invalide', 400);
        }

        $this->produitRepo->update(
            id:          $id,
            nom:         $nom,
            description: $description,
            prix:        (float) $prix,
            stock:       (int) $stock,
            categorieId: (int) $idCategorie,
            image:       $image ?: null
        );

        $produitMaj = $this->produitRepo->findById($id);

        Response::success(['produit' => $produitMaj->toArray(), 'message' => 'Produit mis à jour']);
    }

    /**
     * DELETE /api/admin/produits/{id}
     *
     * Attention : si des COMMANDE_PRODUIT référencent ce produit, la FK RESTRICT
     * empêchera la suppression → l'exception remonte et est catchée par index.php (HTTP 500).
     * En production, préférer un soft-delete (colonne `supprime`).
     */
    public function deleteProduit(array $params): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        if (!isset($params['id']) || !is_numeric($params['id'])) {
            Response::error('ID produit invalide', 400);
        }

        $id = (int) $params['id'];

        if ($this->produitRepo->findById($id) === null) {
            Response::notFound('Produit introuvable');
        }

        $this->produitRepo->delete($id);

        Response::success(['message' => 'Produit supprimé']);
    }

    // =========================================================================
    // COMMANDES — vue générale (administration)
    // =========================================================================

    /**
     * GET /api/admin/commandes
     *
     * Toutes les commandes triées par date DESC (RG-010 : historique indéfini).
     */
    public function getCommandes(): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        $commandes = $this->commandeRepo->findAll();
        Response::success(array_map(fn($c) => $c->toArray(), $commandes));
    }

    // =========================================================================
    // MENUS — accès public borne
    // =========================================================================

    /**
     * GET /api/menus  (sans authentification — borne client)
     *
     * Retourne uniquement les menus disponibles (disponible = 1).
     */
    public function getMenusPublic(): never
    {
        $menus = $this->menuRepo->findAll(disponibleSeulement: true);
        Response::success(array_map(fn($m) => $m->toArray(), $menus));
    }

    // =========================================================================
    // MENUS — gestion back-office (administration)
    // =========================================================================

    /**
     * GET /api/admin/menus
     *
     * Tous les menus (disponibles + désactivés) pour l'admin.
     */
    public function getMenus(): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        $menus = $this->menuRepo->findAll();
        Response::success(array_map(fn($m) => $m->toArray(), $menus));
    }

    /**
     * POST /api/admin/menus
     *
     * Body JSON : { nom, description, prix, image?, produits: [{id_produit, quantite}] }
     */
    public function createMenu(): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        $nom         = trim($body['nom']         ?? '');
        $description = trim($body['description'] ?? '');
        $prix        = $body['prix']     ?? null;
        $image       = trim($body['image'] ?? '') ?: null;
        $produits    = $body['produits'] ?? [];

        if (empty($nom)) {
            Response::error('nom requis', 400);
        }
        if (!is_numeric($prix) || (float) $prix <= 0) {
            Response::error('prix invalide (doit être > 0)', 400);
        }
        if (!is_array($produits)) {
            Response::error('produits doit être un tableau', 400);
        }

        $menu = $this->menuRepo->create(
            nom:         $nom,
            description: $description,
            prix:        (float) $prix,
            image:       $image,
            produits:    $produits
        );

        Response::success(['menu' => $menu->toArray(), 'message' => 'Menu créé']);
    }

    /**
     * PUT /api/admin/menus/{id}
     *
     * Body JSON : { nom, description, prix, image?, disponible, produits: [{id_produit, quantite}] }
     * La composition est entièrement remplacée (DELETE + re-INSERT côté repo).
     */
    public function updateMenu(array $params): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        if (!isset($params['id']) || !is_numeric($params['id'])) {
            Response::error('ID menu invalide', 400);
        }

        $id   = (int) $params['id'];
        $menu = $this->menuRepo->findById($id);

        if ($menu === null) {
            Response::notFound('Menu introuvable');
        }

        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        // Patch partiel : conserver les valeurs actuelles pour les champs absents
        $nom         = trim($body['nom']         ?? $menu->getNom());
        $description = trim($body['description'] ?? $menu->getDescription());
        $prix        = $body['prix']        ?? $menu->getPrix();
        $image       = array_key_exists('image', $body)
                       ? (trim($body['image']) ?: null)
                       : $menu->getImage();
        $disponible  = $body['disponible']  ?? $menu->isDisponible();
        $produits    = $body['produits']    ?? $menu->getProduits();

        if (empty($nom)) {
            Response::error('nom requis', 400);
        }
        if (!is_numeric($prix) || (float) $prix <= 0) {
            Response::error('prix invalide (doit être > 0)', 400);
        }
        if (!is_array($produits)) {
            Response::error('produits doit être un tableau', 400);
        }

        $this->menuRepo->update(
            id:          $id,
            nom:         $nom,
            description: $description,
            prix:        (float) $prix,
            image:       $image,
            disponible:  (bool) $disponible,
            produits:    $produits
        );

        $menuMaj = $this->menuRepo->findById($id);

        Response::success(['menu' => $menuMaj->toArray(), 'message' => 'Menu mis à jour']);
    }

    /**
     * DELETE /api/admin/menus/{id}
     *
     * La FK CASCADE sur MENU_PRODUIT supprime automatiquement la composition.
     */
    public function deleteMenu(array $params): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        if (!isset($params['id']) || !is_numeric($params['id'])) {
            Response::error('ID menu invalide', 400);
        }

        $id = (int) $params['id'];

        if ($this->menuRepo->findById($id) === null) {
            Response::notFound('Menu introuvable');
        }

        $this->menuRepo->delete($id);

        Response::success(['message' => 'Menu supprimé']);
    }

    // =========================================================================
    // UTILISATEURS INTERNES (administration uniquement)
    // =========================================================================

    /**
     * GET /api/admin/utilisateurs
     *
     * Liste tous les comptes admin (sans exposer les mots de passe).
     */
    public function getUtilisateurs(): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        $admins = $this->adminRepo->findAll();
        Response::success(array_map(fn($a) => $a->toArray(), $admins));
    }

    /**
     * POST /api/admin/utilisateurs
     *
     * Body JSON : { nom, email, mot_de_passe, role }
     * Le mot de passe est hashé avec bcrypt avant insertion.
     */
    public function createUtilisateur(): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        $nom        = trim($body['nom']          ?? '');
        $email      = trim($body['email']         ?? '');
        $motDePasse = $body['mot_de_passe']        ?? '';
        $role       = trim($body['role']           ?? '');

        if (empty($nom) || empty($email) || empty($motDePasse) || empty($role)) {
            Response::error('nom, email, mot_de_passe et role sont requis', 400);
        }

        $rolesValides = [Admin::ROLE_ADMINISTRATION, Admin::ROLE_PREPARATION, Admin::ROLE_ACCUEIL];
        if (!in_array($role, $rolesValides, true)) {
            Response::error('role invalide. Valeurs : administration, preparation, accueil', 400);
        }

        // Hash bcrypt — PASSWORD_BCRYPT = coût 10 par défaut
        $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

        $admin = $this->adminRepo->create(
            nom:            $nom,
            email:          $email,
            motDePasseHash: $hash,
            role:           $role
        );

        Response::success(['utilisateur' => $admin->toArray(), 'message' => 'Utilisateur créé']);
    }

    /**
     * PUT /api/admin/utilisateurs/{id}
     *
     * Body JSON : { nom?, email?, role?, mot_de_passe? }
     * Le mot de passe n'est modifié que s'il est fourni dans le body.
     */
    public function updateUtilisateur(array $params): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        if (!isset($params['id']) || !is_numeric($params['id'])) {
            Response::error('ID utilisateur invalide', 400);
        }

        $id    = (int) $params['id'];
        $admin = $this->adminRepo->findById($id);

        if ($admin === null) {
            Response::notFound('Utilisateur introuvable');
        }

        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        // Patch partiel
        $nom        = trim($body['nom']   ?? $admin->getNom());
        $email      = trim($body['email'] ?? $admin->getEmail());
        $role       = trim($body['role']  ?? $admin->getRole());
        $motDePasse = $body['mot_de_passe'] ?? null;

        if (empty($nom) || empty($email) || empty($role)) {
            Response::error('nom, email et role sont requis', 400);
        }

        $rolesValides = [Admin::ROLE_ADMINISTRATION, Admin::ROLE_PREPARATION, Admin::ROLE_ACCUEIL];
        if (!in_array($role, $rolesValides, true)) {
            Response::error('role invalide. Valeurs : administration, preparation, accueil', 400);
        }

        // Hash uniquement si un nouveau mot de passe est fourni
        $hash = ($motDePasse !== null && $motDePasse !== '')
                ? password_hash($motDePasse, PASSWORD_BCRYPT)
                : null;

        $this->adminRepo->update(
            id:             $id,
            nom:            $nom,
            email:          $email,
            role:           $role,
            motDePasseHash: $hash
        );

        $adminMaj = $this->adminRepo->findById($id);

        Response::success(['utilisateur' => $adminMaj->toArray(), 'message' => 'Utilisateur mis à jour']);
    }

    /**
     * DELETE /api/admin/utilisateurs/{id}
     *
     * Protection : un admin ne peut pas supprimer son propre compte.
     */
    public function deleteUtilisateur(array $params): never
    {
        $this->verifierRole(Admin::ROLE_ADMINISTRATION);

        if (!isset($params['id']) || !is_numeric($params['id'])) {
            Response::error('ID utilisateur invalide', 400);
        }

        $id = (int) $params['id'];

        // Empêcher l'auto-suppression
        if ($id === (int) ($_SESSION['admin_id'] ?? 0)) {
            Response::error('Impossible de supprimer son propre compte', 400);
        }

        if ($this->adminRepo->findById($id) === null) {
            Response::notFound('Utilisateur introuvable');
        }

        $this->adminRepo->delete($id);

        Response::success(['message' => 'Utilisateur supprimé']);
    }

    // =========================================================================
    // WORKFLOW COMMANDES (preparation / accueil / administration)
    // =========================================================================

    /**
     * GET /api/admin/commandes/preparation
     *
     * Commandes en statut 'en_attente', triées par heure de livraison ASC.
     * Utilisé par l'écran de préparation en cuisine.
     */
    public function getCommandesPreparation(): never
    {
        $this->verifierRole(Admin::ROLE_PREPARATION, Admin::ROLE_ADMINISTRATION);

        $commandes = $this->commandeRepo->findForPreparation();
        Response::success(array_map(fn($c) => $c->toArray(), $commandes));
    }

    /**
     * PUT /api/admin/commandes/{id}/preparer
     *
     * Transition : en_attente → preparee
     * Erreur 422 si la commande est dans un statut incompatible.
     */
    public function marquerPreparee(array $params): never
    {
        $this->verifierRole(Admin::ROLE_PREPARATION, Admin::ROLE_ADMINISTRATION);

        if (!isset($params['id']) || !is_numeric($params['id'])) {
            Response::error('ID commande invalide', 400);
        }

        $id = (int) $params['id'];

        try {
            $commande = $this->commandeAdminService->marquerPreparee($id);
        } catch (\RuntimeException $e) {
            // Commande introuvable
            Response::notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            // Transition de statut impossible (ex : déjà préparée ou livrée)
            Response::error($e->getMessage(), 422);
        }

        Response::success(['commande' => $commande->toArray(), 'message' => 'Commande marquée préparée']);
    }

    /**
     * PUT /api/admin/commandes/{id}/livrer
     *
     * Transition : preparee → livree
     * Erreur 422 si la commande est dans un statut incompatible.
     */
    public function marquerLivree(array $params): never
    {
        $this->verifierRole(Admin::ROLE_ACCUEIL, Admin::ROLE_ADMINISTRATION);

        if (!isset($params['id']) || !is_numeric($params['id'])) {
            Response::error('ID commande invalide', 400);
        }

        $id = (int) $params['id'];

        try {
            $commande = $this->commandeAdminService->marquerLivree($id);
        } catch (\RuntimeException $e) {
            Response::notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        }

        Response::success(['commande' => $commande->toArray(), 'message' => 'Commande marquée livrée']);
    }

    /**
     * POST /api/admin/commandes
     *
     * Saisie directe d'une commande par le rôle accueil (sans passer par le panier).
     * Utile pour les commandes téléphoniques ou les corrections manuelles.
     *
     * Body JSON :
     * {
     *   "type_commande"  : "sur_place" | "a_emporter",
     *   "mode_paiement"  : "cb" | "especes" | "ticket_restaurant",
     *   "numero_chevalet": 42,          (optionnel, défaut : aléatoire RG-004)
     *   "heure_livraison": "14:30:00",  (optionnel)
     *   "produits": [
     *     { "id_produit": 1, "quantite": 2, "prix_unitaire": 5.40 }
     *   ]
     * }
     */
    public function saisirCommande(): never
    {
        $this->verifierRole(Admin::ROLE_ACCUEIL, Admin::ROLE_ADMINISTRATION);

        $body = json_decode(file_get_contents('php://input'), true);

        if ($body === null) {
            Response::error('Body JSON invalide', 400);
        }

        $typeCommande   = trim($body['type_commande']  ?? '');
        $modePaiement   = trim($body['mode_paiement']  ?? '');
        $heureLivraison = $body['heure_livraison'] ?? null;
        $numeroChevalet = isset($body['numero_chevalet'])
                          ? (int) $body['numero_chevalet']
                          : random_int(1, 999); // RG-004
        $produits       = $body['produits'] ?? [];

        if (empty($typeCommande) || empty($modePaiement)) {
            Response::error('type_commande et mode_paiement sont requis', 400);
        }
        if (!is_array($produits) || empty($produits)) {
            Response::error('produits est requis et ne peut pas être vide', 400);
        }
        if ($numeroChevalet < 1 || $numeroChevalet > 999) {
            Response::error('numero_chevalet doit être entre 1 et 999 (RG-004)', 400);
        }

        // Calculer le montant total à partir des lignes saisies
        $montantTotal = 0.0;
        foreach ($produits as $ligne) {
            $qte  = (int)   ($ligne['quantite']     ?? 0);
            $prix = (float) ($ligne['prix_unitaire'] ?? 0.0);
            if ($qte <= 0 || $prix <= 0) {
                Response::error('Chaque produit doit avoir quantite > 0 et prix_unitaire > 0', 400);
            }
            $montantTotal += $qte * $prix;
        }

        // Générer un numéro de commande unique (même format que CommandeService)
        $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

        $commandeObj = new Commande(
            id:             0, // AUTO_INCREMENT
            numeroCommande: $numeroCommande,
            numeroChevalet: $numeroChevalet,
            typeCommande:   $typeCommande,
            modePaiement:   $modePaiement,
            montantTotal:   round($montantTotal, 2),
            dateCreation:   date('Y-m-d H:i:s'),
            clientId:       null, // saisie admin = pas de client (RG-009)
            statut:         Commande::STATUS_EN_ATTENTE,
            heureLivraison: $heureLivraison ?: null,
        );

        // Transaction : commande + lignes atomiques (RG-008)
        $this->pdo->beginTransaction();

        try {
            $commandeCreee = $this->commandeRepo->create($commandeObj);

            foreach ($produits as $ligne) {
                // Réutilisation de PanierLigne comme DTO de ligne (même structure)
                $panierLigne = new PanierLigne(
                    id:           0,
                    idPanier:     0, // ignoré côté CommandeProduitRepo (utilise id_commande)
                    idProduit:    (int)   $ligne['id_produit'],
                    quantite:     (int)   $ligne['quantite'],
                    prixUnitaire: (float) $ligne['prix_unitaire'],
                    details:      $ligne['details'] ?? null,
                );
                $this->commandeProduitRepo->addFromPanierLigne($commandeCreee->getId(), $panierLigne);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        Response::success([
            'commande' => $commandeCreee->toArray(),
            'message'  => 'Commande saisie',
        ]);
    }

    // =========================================================================
    // Méthode privée de contrôle d'accès
    // =========================================================================

    /**
     * Vérifie que l'admin est connecté et possède l'un des rôles demandés.
     *
     * Appelé en première ligne de chaque route protégée.
     * Sans argument → vérifie seulement que l'admin est connecté (tous rôles).
     * Avec argument(s) → accepte uniquement les rôles listés.
     *
     * Exemples :
     *   $this->verifierRole();                                          // connecté quel que soit le rôle
     *   $this->verifierRole(Admin::ROLE_ADMINISTRATION);               // administration seulement
     *   $this->verifierRole(Admin::ROLE_PREPARATION, Admin::ROLE_ADMINISTRATION); // les deux
     *
     * Termine la requête avec HTTP 401 ou 403 si la condition n'est pas remplie.
     */
    private function verifierRole(string ...$roles): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['admin_id'])) {
            Response::unauthorized('Non connecté');
        }

        // Si des rôles spécifiques sont requis, vérifier que le rôle en session correspond
        if (!empty($roles) && !in_array($_SESSION['admin_role'] ?? '', $roles, true)) {
            Response::error('Accès interdit pour ce rôle', 403);
        }
    }
}
