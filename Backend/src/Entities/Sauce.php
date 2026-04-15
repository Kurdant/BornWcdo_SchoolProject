<?php

declare(strict_types=1);

namespace WCDO\Entities;

class Sauce
{
    public function __construct(
        private readonly int     $id,
        private readonly string  $nom,
        private readonly ?string $image = null,
    ) {}

    public function getId(): int        { return $this->id; }
    public function getNom(): string    { return $this->nom; }
    public function getImage(): ?string { return $this->image; }

    public function toArray(): array
    {
        return [
            'id'    => $this->id,
            'nom'   => $this->nom,
            'image' => $this->image,
        ];
    }
}
