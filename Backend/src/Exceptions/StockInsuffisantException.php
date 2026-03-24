<?php

declare(strict_types=1);

namespace WCDO\Exceptions;

use Exception;

class StockInsuffisantException extends Exception
{
    public function __construct(
        private readonly int $produitId,
        private readonly int $stockDemande,
        private readonly int $stockDisponible
    ) {
        parent::__construct(
            "Stock insuffisant pour le produit {$produitId}. " .
            "Demandé: {$stockDemande}, disponible: {$stockDisponible}"
        );
    }

    public function getProduitId(): int       { return $this->produitId; }
    public function getStockDemande(): int    { return $this->stockDemande; }
    public function getStockDisponible(): int { return $this->stockDisponible; }
}
