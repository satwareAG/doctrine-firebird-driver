<?php
namespace Satag\DoctrineFirebirdDriver\Test\Resource\Entity\Cases\CascadingRemove;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'CASES_CASCADINGREMOVE_SUBCLASS')]
#[ORM\Entity]
class Subclass
{
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    private ?int $id = 1;

    /**
     * @return null|int
     */
    public function getId()
    {
        return $this->id;
    }
}
