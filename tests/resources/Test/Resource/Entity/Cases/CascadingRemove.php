<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Resource\Entity\Cases;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'CASES_CASCADINGREMOVE')]
#[ORM\Entity]
class CascadingRemove
{
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    private ?int $id = 1;

    public function __construct(#[ORM\OneToOne(targetEntity: \Kafoso\DoctrineFirebirdDriver\Test\Resource\Entity\Cases\CascadingRemove\Subclass::class, cascade: ['remove'])]
    private CascadingRemove\Subclass $subclass)
    {
    }

    /**
     * @return null|int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return CascadingRemove\Subclass
     */
    public function getSubclass()
    {
        return $this->subclass;
    }
}
