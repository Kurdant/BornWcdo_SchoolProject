<?php

declare(strict_types=1);

namespace WCDO\Services;

use InvalidArgumentException;
use WCDO\Entities\PanierLigne;
use WCDO\Repositories\PanierRepository;
use WCDO\Repositories\PanierProduitRepository;
use WCDO\Repositories\ProduitRepository;
use WCDO\Repositories\TailleBoissonRepository;

class PanierService
{
    private PanierRepository $panierRepo;
    private PanierProduitRepository $panierProduitRepo;
    private ProduitRepository $produitRepo;
    private TailleBoissonRepository $tailleBoissonRepo;

    public function __construct()
    {
        $this->panierRepo        = new PanierRepository();
        $this->panierProduitRepo = new PanierProduitRepository();
        $this->produitRepo       = new ProduitRepository();
        $this->tailleBoissonRepo = new TailleBoissonRepository();
    }

    public function getPanier(string $sessionId): array
    {
        $panier = $this->panierRepo->findBySessionId($sessionId);

        if ($panier === null) {
            return ['panier' => null, 'lignes' => [], 'total' => 0.0];
        }

        $lignes = $this->panierProduitRepo->findByPanierId($panier->getId());

        return [
            'panier' => $panier->toArray(),
            'lignes' => array_map(fn(PanierLigne $l) => $l->toArray(), $lignes),
            'total'  => $this->calculerTotal($lignes),
        ];
    }

    public function ajouter(
        string $sessionId,
        int $produitId,
        int $quantite,
        array $details = [],
        ?int $clientId = null
    ): PanierLigne {
        // RG-001 : le produit doit exister et avoir du stock (estDisponible = stock > 0)
        $produit = $this->produitRepo->findById($produitId);
        if ($produit === null || !$produit->estDisponible()) {
            throw new InvalidArgumentException('Produit indisponible ou introuvable.');
        }

        // RG-002 : maximum 2 sauces par menu — validé ici, pas en frontend
        if (isset($details['sauces']) && is_array($details['sauces'])) {
            if (count($details['sauces']) > 2) {
                throw new InvalidArgumentException('Maximum 2 sauces autorisées par menu.');
            }
        }

        // RG-003 : supplement_prix de la taille boisson s'ajoute au prix de base
        $prixUnitaire = $produit->getPrix();
        if (isset($details['taille_id'])) {
            $taille = $this->tailleBoissonRepo->findById((int) $details['taille_id']);
            if ($taille !== null) {
                $prixUnitaire += $taille->getSupplementPrix();
                // Stocker le supplément dans details pour traçabilité
                $details['supplement'] = $taille->getSupplementPrix();
            }
        }

        // RG-009 : clientId peut être null (visiteur anonyme) — panier créé sans compte
        $panier = $this->panierRepo->findOrCreateBySessionId($sessionId, $clientId);

        return $this->panierProduitRepo->add(
            panierId: $panier->getId(),
            produitId: $produitId,
            quantite: $quantite,
            prixUnitaire: $prixUnitaire,
            details: $details
        );
    }

    public function supprimerLigne(int $ligneId): bool
    {
        return $this->panierProduitRepo->deleteById($ligneId);
    }

    public function vider(string $sessionId): bool
    {
        $panier = $this->panierRepo->findBySessionId($sessionId);

        if ($panier === null) {
            return true; // panier inexistant = déjà vide
        }

        return $this->panierProduitRepo->deleteByPanierId($panier->getId());
    }

    // Somme des sous-totaux : prixUnitaire × quantite pour chaque ligne
    private function calculerTotal(array $lignes): float
    {
        return array_reduce(
            $lignes,
            fn(float $carry, PanierLigne $ligne) => $carry + $ligne->getSousTotal(),
            0.0
        );
    }
}
