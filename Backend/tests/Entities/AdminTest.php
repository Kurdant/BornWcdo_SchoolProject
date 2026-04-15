<?php

declare(strict_types=1);

namespace WCDO\Tests\Entities;

use PHPUnit\Framework\TestCase;
use WCDO\Entities\Admin;

class AdminTest extends TestCase
{
    // ─── Helper ──────────────────────────────────────────────────────────────

    private function creerAdmin(string $role = Admin::ROLE_ADMINISTRATION): Admin
    {
        return new Admin(1, 'Jean Dupont', 'jean@wcdo.fr', password_hash('secret', PASSWORD_BCRYPT), $role);
    }

    // ─── Tests validation rôle ───────────────────────────────────────────────

    public function testRoleAdministrationEstValide(): void
    {
        // Ne doit pas lancer d'exception
        $admin = $this->creerAdmin(Admin::ROLE_ADMINISTRATION);

        $this->assertSame(Admin::ROLE_ADMINISTRATION, $admin->getRole());
    }

    public function testRolePreparationEstValide(): void
    {
        $admin = $this->creerAdmin(Admin::ROLE_PREPARATION);

        $this->assertSame(Admin::ROLE_PREPARATION, $admin->getRole());
    }

    public function testRoleAccueilEstValide(): void
    {
        $admin = $this->creerAdmin(Admin::ROLE_ACCUEIL);

        $this->assertSame(Admin::ROLE_ACCUEIL, $admin->getRole());
    }

    public function testRoleInvalideLeveException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Admin(1, 'Jean', 'jean@wcdo.fr', password_hash('secret', PASSWORD_BCRYPT), 'superadmin');
    }

    // ─── Tests hasRole ───────────────────────────────────────────────────────

    public function testHasRoleRetourneTrueSiRoleCorrespond(): void
    {
        $admin = $this->creerAdmin(Admin::ROLE_ADMINISTRATION);

        $this->assertTrue($admin->hasRole('administration'));
    }

    public function testHasRoleRetourneFalseSiRoleDifferent(): void
    {
        $admin = $this->creerAdmin(Admin::ROLE_PREPARATION);

        $this->assertFalse($admin->hasRole('accueil'));
    }

    /** hasRole() est variadic : on peut passer plusieurs rôles acceptés */
    public function testHasRoleVariadicAcceptePlusieursRoles(): void
    {
        $admin = $this->creerAdmin(Admin::ROLE_PREPARATION);

        $this->assertTrue($admin->hasRole('preparation', 'accueil'));
    }

    // ─── Tests mot de passe ──────────────────────────────────────────────────

    public function testVerifierMotDePasseCorrect(): void
    {
        $hash  = password_hash('monMotDePasse', PASSWORD_BCRYPT);
        $admin = new Admin(1, 'Jean', 'jean@wcdo.fr', $hash, Admin::ROLE_ADMINISTRATION);

        $this->assertTrue($admin->verifierMotDePasse('monMotDePasse'));
    }

    public function testVerifierMotDePasseIncorrect(): void
    {
        $hash  = password_hash('monMotDePasse', PASSWORD_BCRYPT);
        $admin = new Admin(1, 'Jean', 'jean@wcdo.fr', $hash, Admin::ROLE_ADMINISTRATION);

        $this->assertFalse($admin->verifierMotDePasse('mauvaisMotDePasse'));
    }

    // ─── Tests sécurité toArray ──────────────────────────────────────────────

    /** Le hash bcrypt ne doit jamais être exposé dans la réponse JSON */
    public function testToArrayNExposePasLeMotDePasse(): void
    {
        $admin = $this->creerAdmin();

        $this->assertArrayNotHasKey('mot_de_passe', $admin->toArray());
    }
}
