<?php

declare(strict_types=1);

namespace WCDO\Tests\Entities;

use PHPUnit\Framework\TestCase;
use WCDO\Entities\Produit;

class ProduitTest extends TestCase
{
    // ─── Helper ──────────────────────────────────────────────────────────────

    private function creerProduit(int $stock = 10, float $prix = 5.00): Produit
    {
        return new Produit(1, 'Test Burger', 'Description test', $prix, $stock, 1, null, '2026-01-01 00:00:00');
    }

    // ─── Tests disponibilité ─────────────────────────────────────────────────

    public function testProduitAvecStockPositifEstDisponible(): void
    {
        $produit = $this->creerProduit(stock: 5);

        $this->assertTrue($produit->estDisponible());
    }

    /** RG-001 : stock = 0 → le produit n'est plus disponible à la commande */
    public function testProduitAvecStockZeroEstIndisponible(): void
    {
        $produit = $this->creerProduit(stock: 0);

        $this->assertFalse($produit->estDisponible());
    }

    // ─── Tests validations constructeur ──────────────────────────────────────

    public function testPrixNegatifLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->creerProduit(prix: -1.00);
    }

    public function testPrixZeroLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->creerProduit(prix: 0.00);
    }

    public function testStockNegatifLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->creerProduit(stock: -1);
    }

    // ─── Tests accesseurs ────────────────────────────────────────────────────

    public function testProduitAvecStockPositifAUnPrix(): void
    {
        $produit = $this->creerProduit(stock: 3, prix: 7.50);

        $this->assertSame(7.50, $produit->getPrix());
    }
}
