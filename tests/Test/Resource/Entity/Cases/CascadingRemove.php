<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Resource\Entity\Cases;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity\Cases\CascadingRemove\Subclass;

#[ORM\Table(name: 'CASES_CASCADINGREMOVE')]
#[ORM\Entity]
class CascadingRemove
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    private int|null $id = 1;

    public function __construct(#[ORM\OneToOne(targetEntity: Subclass::class, cascade: ['remove'])]
    private Subclass|null $subclass,)
    {
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getSubclass(): CascadingRemove\Subclass
    {
        return $this->subclass;
    }
}
