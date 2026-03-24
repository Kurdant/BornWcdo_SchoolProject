<?php

namespace WCDO\Repositories;

use PDO;
use WCDO\Config\Database;
use WCDO\Entities\Commande;

class CommandeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Crée une commande (RG-007 : créée UNIQUEMENT après paiement validé)
     * 
     * IMPORTANT : Cette méthode est appelée UNIQUEMENT depuis CommandeService::creer(),
     * après que le paiement soit validé et dans une transaction SQL pour garantir l'atomicité (RG-008).
     * Le Repository NE fait que l'insertion en BDD — toute la logique complexe (stock, fidélité) 
     * est dans le Service.
     * 
     * @param Commande $commande L'objet Commande avec tous les paramètres (numéro, chevalet, client, montant...)
     * @return Commande L'objet Commande avec l'ID généré par la BDD
     */
    public function create(Commande $commande): Commande
    {
        // Prépare la requête SQL avec des paramètres nommés (:clé) pour éviter les injections SQL
        $stmt = $this->pdo->prepare(
            'INSERT INTO COMMANDE 
             (numero_commande, numero_chevalet, type_commande, mode_paiement, montant_total, client_id)
             VALUES (:numero_commande, :numero_chevalet, :type_commande, :mode_paiement, :montant_total, :client_id)'
        );

        $stmt->execute([
            'numero_commande' => $commande->numeroCommande(),
            'numero_chevalet' => $commande->numeroChevalet(),
            'type_commande' => $commande->typeCommande(),
            'mode_paiement' => $commande->modePaiement(),
            'montant_total' => $commande->montantTotal(),
            'client_id' => $commande->clientId()  // NULL si client anonyme (RG-009)
        ]);

        // lastInsertId() récupère l'ID généré automatiquement par MariaDB (AUTO_INCREMENT)
        $id = (int) $this->pdo->lastInsertId();

        // Retourne un nouvel objet Commande hydraté avec l'ID et la date du serveur
        return new Commande(
            id: $id,
            numeroCommande: $commande->numeroCommande(),
            numeroChevalet: $commande->numeroChevalet(),
            typeCommande: $commande->typeCommande(),
            modePaiement: $commande->modePaiement(),
            montantTotal: $commande->montantTotal(),
            clientId: $commande->clientId(),
            dateCreation: new \DateTime()
        );
    }

    /**
     * Récupère une commande par son numéro unique
     * 
     * POURQUOI NULLABLE ? Si on cherche une commande inexistante, c'est NORMAL (pas une erreur).
     * Le Controller gère proprement : if ($commande === null) Response::notFound();
     * 
     * vs. POURQUOI PAS UNE EXCEPTION ? Une exception = erreur imprévisible (BDD crashée).
     * Une commande non trouvée = absence normale, pas une erreur.
     * 
     * @param string $numero Le numéro unique de la commande (ex: "cmd_6789")
     * @return ?Commande L'objet Commande trouvé, ou null s'il n'existe pas
     */
    public function findByNumero(string $numero): ?Commande
    {
        // Prépare avec paramètre nommé pour éviter les injections SQL
        $stmt = $this->pdo->prepare(
            'SELECT id, numero_commande, numero_chevalet, type_commande, mode_paiement, montant_total, date_creation, client_id
             FROM COMMANDE
             WHERE numero_commande = :numero_commande'
        );
        $stmt->execute(['numero_commande' => $numero]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);  // Retourne 1 seule ligne ou false

        // Si fetch() retourne false, retourne null (commande non trouvée)
        return $row ? $this->hydrateCommande($row) : null;
    }

    /**
     * Récupère toutes les commandes (RG-010 : historique conservé indéfiniment)
     * 
     * JAMAIS supprimer une commande ! C'est un historique légal/comptable.
     * Les commandes sont triées par date décroissante (plus récentes d'abord).
     * 
     * Cas d'usage : AdminController affiche le dashboard avec toutes les commandes du jour.
     * 
     * @return array Tableau d'objets Commande
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, numero_commande, numero_chevalet, type_commande, mode_paiement, montant_total, date_creation, client_id
             FROM COMMANDE
             ORDER BY date_creation DESC'  // Plus récentes d'abord
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);  // Retourne un tableau de lignes

        // Transforme chaque ligne en objet Commande via hydrateProduit()
        $commandes = [];
        foreach ($rows as $row) {
            $commandes[] = $this->hydrateCommande($row);
        }
        return $commandes;
    }

    /**
     * Récupère les commandes d'un client (historique client)
     * 
     * Cas d'usage : Un client connecté via AuthController clique sur "Mes commandes".
     * L'API retourne toutes ses anciennes commandes avec numéros pour pouvoir les suivre.
     * 
     * @param int $clientId L'ID du client
     * @return array Tableau d'objets Commande triées par date décroissante
     */
    public function findByClientId(int $clientId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, numero_commande, numero_chevalet, type_commande, mode_paiement, montant_total, date_creation, client_id
             FROM COMMANDE
             WHERE client_id = :client_id
             ORDER BY date_creation DESC'
        );
        $stmt->execute(['client_id' => $clientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $commandes = [];
        foreach ($rows as $row) {
            $commandes[] = $this->hydrateCommande($row);
        }
        return $commandes;
    }

    /**
     * Transforme une ligne BDD (tableau associatif) en objet Commande
     * 
     * POURQUOI CETTE MÉTHODE PRIVÉE ?
     * - DRY (Don't Repeat Yourself) : transformation réutilisée 3 fois (create, findByNumero, findAll, findByClientId)
     * - Typage strict : PDO retourne des strings, on doit caster en int/float
     * - Gestion du null : client_id peut être NULL (commande anonyme, RG-009)
     * 
     * @param array $row Ligne depuis PDO::FETCH_ASSOC
     * @return Commande Objet hydraté et typé
     */
    private function hydrateCommande(array $row): Commande
    {
        return new Commande(
            id: (int) $row['id'],  // Cast string → int
            numeroCommande: $row['numero_commande'],  // Reste string (ex: "cmd_abc123")
            numeroChevalet: (int) $row['numero_chevalet'],  // RG-004 : 1-999
            typeCommande: $row['type_commande'],  // ENUM BDD (ex: "emporter", "surplace")
            modePaiement: $row['mode_paiement'],  // ENUM BDD (ex: "carte", "especes")
            montantTotal: (float) $row['montant_total'],  // Cast string → float (ex: "12.50")
            clientId: $row['client_id'] ? (int) $row['client_id'] : null,  // Nullable (RG-009)
            dateCreation: new \DateTime($row['date_creation'])  // Chaîne SQL → objet DateTime
        );
    }
}
