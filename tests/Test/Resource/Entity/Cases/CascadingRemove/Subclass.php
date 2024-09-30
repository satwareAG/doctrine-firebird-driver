<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Resource\Entity\Cases\CascadingRemove;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'CASES_CASCADINGREMOVE_SUBCLASS')]
#[ORM\Entity]
class Subclass
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    private int|null $id = 1;

    public function getId(): int|null
    {
        return $this->id;
    }
}
