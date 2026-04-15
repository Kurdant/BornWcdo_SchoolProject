<?php
declare(strict_types=1);
namespace WCDO\Entities;

class Menu
{
    public function __construct(
        private readonly int     $id,
        private readonly string  $nom,
        private readonly string  $description,
        private readonly float   $prix,
        private readonly ?string $image,
        private readonly bool    $disponible,
        private readonly string  $dateCreation,
        private readonly array   $produits = [],   // [['id_produit' => int, 'quantite' => int], ...]
    ) {
        if ($prix <= 0) {
            throw new \InvalidArgumentException("Le prix d'un menu doit être supérieur à 0.");
        }
    }

    public function getId(): int              { return $this->id; }
    public function getNom(): string          { return $this->nom; }
    public function getDescription(): string  { return $this->description; }
    public function getPrix(): float          { return $this->prix; }
    public function getImage(): ?string       { return $this->image; }
    public function isDisponible(): bool      { return $this->disponible; }
    public function getDateCreation(): string { return $this->dateCreation; }
    public function getProduits(): array      { return $this->produits; }

    /** Alias lisible pour les vérifications de stock côté service */
    public function estDisponible(): bool
    {
        return $this->disponible;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'nom'           => $this->nom,
            'description'   => $this->description,
            'prix'          => $this->prix,
            'image'         => $this->image,
            'disponible'    => $this->disponible,
            'date_creation' => $this->dateCreation,
            'produits'      => $this->produits,
        ];
    }
}
