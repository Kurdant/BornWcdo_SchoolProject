<?php

declare(strict_types=1);

namespace WCDO\Entities;

class PanierLigne
{
    public function __construct(
        private readonly int    $id,
        private readonly int    $idPanier,
        private readonly int    $idProduit,
        private readonly int    $quantite,
        private readonly float  $prixUnitaire,
        private readonly ?array $details,
    ) {}

    public function getId(): int             { return $this->id; }
    public function getIdPanier(): int       { return $this->idPanier; }
    public function getIdProduit(): int      { return $this->idProduit; }
    public function getQuantite(): int       { return $this->quantite; }
    public function getPrixUnitaire(): float { return $this->prixUnitaire; }
    public function getDetails(): ?array     { return $this->details; }

    public function getSousTotal(): float
    {
        return $this->prixUnitaire * $this->quantite;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'id_panier'     => $this->idPanier,
            'id_produit'    => $this->idProduit,
            'quantite'      => $this->quantite,
            'prix_unitaire' => $this->prixUnitaire,
            'sous_total'    => $this->getSousTotal(),
            // Sauces choisies, taille boisson, etc. (RG-002, RG-003)
            'details'       => $this->details,
        ];
    }
}
