<?php

namespace Satag\DoctrineFirebirdDriver\ORM\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Satag\DoctrineFirebirdDriver\ORM\Persister\Entity\Firebird3EntityPersister;


class InsertReturningSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
    return [
        Events::prePersist,
        Events::preFlush,
        Events::postPersist,
        Events::onFlush,
    ];
}

    public function prePersist(PrePersistEventArgs $args)
    {

    }

    public function postPersist(PostPersistEventArgs $args)
    {

    }
    public function preFlush(PreFlushEventArgs $args)
    {

    }

    public function onFlush(OnFlushEventArgs $args)
    {



    }

    /**
     * Check if the entity has an identity/auto-increment column.
     */
    private function hasIdentityColumn(ClassMetadata $metadata): bool
    {
        // Check for auto-generated identifiers (IDENTITY columns)
        foreach ($metadata->getFieldNames() as $fieldName) {
            $fieldMapping = $metadata->getFieldMapping($fieldName);
            if (!empty($fieldMapping['id']) && $metadata->isIdGeneratorIdentity()) {
                return true;
            }
        }
        return false;
    }
}
