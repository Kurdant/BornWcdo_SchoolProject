<?php

declare(strict_types=1);

namespace WCDO\Entities;

class Commande
{
    public function __construct(
        private readonly int     $id,
        private readonly string  $numeroCommande,
        private readonly int     $numeroChevalet,
        private readonly string  $typeCommande,
        private readonly string  $modePaiement,
        private readonly float   $montantTotal,
        private readonly string  $dateCreation,
        private readonly ?int    $clientId,
    ) {}

    public function getId(): int               { return $this->id; }
    public function getNumeroCommande(): string { return $this->numeroCommande; }
    public function getNumeroChevalet(): int    { return $this->numeroChevalet; }
    public function getMontantTotal(): float    { return $this->montantTotal; }
    public function getClientId(): ?int         { return $this->clientId; }

    // RG-005 : 1€ dépensé = 1 point, arrondi à l'inférieur (jamais au supérieur)
    public function calculerPointsFidelite(): int
    {
        return (int) floor($this->montantTotal);
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'numero_commande' => $this->numeroCommande,
            'numero_chevalet' => $this->numeroChevalet,
            'type_commande'   => $this->typeCommande,
            'mode_paiement'   => $this->modePaiement,
            'montant_total'   => $this->montantTotal,
            'date_creation'   => $this->dateCreation,
            'client_id'       => $this->clientId,
        ];
    }
}
