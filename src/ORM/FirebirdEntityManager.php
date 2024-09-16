<?php
declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\ORM;

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Configuration;

class FirebirdEntityManager extends EntityManager
{
    private ?FirebirdUnitOfWork $unitOfWork = null;

    /**
     * Override the default UnitOfWork with the custom FirebirdUnitOfWork.
     */
    public function __construct($conn, Configuration $config, $eventManager)
    {

        parent::__construct($conn, $config, $eventManager);

    }

    public function getUnitOfWork(): FirebirdUnitOfWork
    {
        if (!$this->unitOfWork) {
            $this->unitOfWork = new FirebirdUnitOfWork($this);
        }

        return $this->unitOfWork;
    }
    // Optional: Add a static factory method for convenience
    public static function create($conn, Configuration $config, EventManager $eventManager)
    {
        return new FirebirdEntityManager($conn, $config, $eventManager);
    }
}
