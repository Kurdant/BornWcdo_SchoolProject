<?php

declare(strict_types=1);

namespace WCDO\Entities;

class Commande
{
    public const STATUS_EN_ATTENTE = 'en_attente';
    public const STATUS_PREPAREE   = 'preparee';
    public const STATUS_LIVREE     = 'livree';

    public function __construct(
        private readonly int     $id,
        private readonly string  $numeroCommande,
        private readonly int     $numeroChevalet,
        private readonly string  $typeCommande,
        private readonly string  $modePaiement,
        private readonly float   $montantTotal,
        private readonly string  $dateCreation,
        private readonly ?int    $clientId,
        private readonly string  $statut         = self::STATUS_EN_ATTENTE,
        private readonly ?string $heureLivraison = null,
    ) {}

    public function getId(): int               { return $this->id; }
    public function getNumeroCommande(): string { return $this->numeroCommande; }
    public function getNumeroChevalet(): int    { return $this->numeroChevalet; }
    public function getTypeCommande(): string   { return $this->typeCommande; }
    public function getModePaiement(): string   { return $this->modePaiement; }
    public function getMontantTotal(): float    { return $this->montantTotal; }
    public function getDateCreation(): string   { return $this->dateCreation; }
    public function getClientId(): ?int         { return $this->clientId; }
    public function getStatut(): string         { return $this->statut; }
    public function getHeureLivraison(): ?string { return $this->heureLivraison; }

    public function estEnAttente(): bool { return $this->statut === self::STATUS_EN_ATTENTE; }
    public function estPreparee(): bool  { return $this->statut === self::STATUS_PREPAREE; }
    public function estLivree(): bool    { return $this->statut === self::STATUS_LIVREE; }

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
            'statut'          => $this->statut,
            'heure_livraison' => $this->heureLivraison,
        ];
    }
}
