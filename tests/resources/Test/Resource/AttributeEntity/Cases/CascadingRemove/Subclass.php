<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Resource\AttributeEntity\Cases\CascadingRemove;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'CASES_CASCADINGREMOVE_SUBCLASS')]
#[ORM\Entity]
class Subclass
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    private $id = 1;

    /**
     * @return null|int
     */
    public function getId()
    {
        return $this->id;
    }
}
