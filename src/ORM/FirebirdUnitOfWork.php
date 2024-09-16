<?php
declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Persisters\Entity\EntityPersister;
use Doctrine\ORM\UnitOfWork;
use Satag\DoctrineFirebirdDriver\ORM\Persister\Entity\Firebird3EntityPersister;


class FirebirdUnitOfWork extends UnitOfWork
{
    private $customPersisters = [];


    public function __construct(private readonly EntityManager $em)
    {
        parent::__construct($em);
    }

    /**
     * Override the method responsible for getting the entity persisters.
     */
    public function getEntityPersister(string $entityName): EntityPersister
    {
        // Check if a custom persister has already been instantiated
        if (isset($this->customPersisters[$entityName])) {
            return $this->customPersisters[$entityName];
        }

        $classMetadata = $this->em->getClassMetadata($entityName);

        // Check if the entity uses an identity column
        if ($classMetadata->isIdGeneratorIdentity()) {
            // Instantiate the custom persister for Firebird and cache it
            $this->customPersisters[$entityName] = new Firebird3EntityPersister($this->em, $classMetadata);
            return $this->customPersisters[$entityName];
        }

        // Fallback to the default persister for non-identity columns
        return parent::getEntityPersister($entityName);
    }
}
