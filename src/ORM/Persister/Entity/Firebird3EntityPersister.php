<?php
declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\ORM\Persister\Entity;


class Firebird3EntityPersister extends \Doctrine\ORM\Persisters\Entity\BasicEntityPersister
{
    /**
     * Override insert to add RETURNING clause for Firebird3.
     */
    protected function _getInsertSQL()
    {
        $insertSQL = parent::getInsertSQL();
        $identityField = $this->class->getIdentifierColumnNames()[0];

        // Append RETURNING clause to the insert SQL for Firebird3
        return $insertSQL . ' RETURNING ' . $this->platform->quoteIdentifier($identityField);
    }

    /**
     * Override executeInserts to handle the RETURNING clause and get the last inserted ID.
     */
    public function executeInserts(): void
    {
        if (! $this->queuedInserts) {
            return;
        }

        $uow            = $this->em->getUnitOfWork();
        $idGenerator    = $this->class->idGenerator;
        $isPostInsertId = $idGenerator->isPostInsertGenerator();

        $stmt      = $this->conn->prepare($this->getInsertSQL());
        $tableName = $this->class->getTableName();

        foreach ($this->queuedInserts as $key => $entity) {
            $insertData = $this->prepareInsertData($entity);

            if (isset($insertData[$tableName])) {
                $paramIndex = 1;

                foreach ($insertData[$tableName] as $column => $value) {
                    $stmt->bindValue($paramIndex++, $value, $this->columnTypes[$column]);
                }
            }

            $lastId = $stmt->executeStatement();

            if ($isPostInsertId) {
                $generatedId = $idGenerator->generateId($this->em, $entity);
                $id          = [$this->class->identifier[0] => $generatedId];

                $uow->assignPostInsertId($entity, $generatedId);
            } else {
                $id = $this->class->getIdentifierValues($entity);
            }

            if ($this->class->requiresFetchAfterChange) {
                $this->assignDefaultVersionAndUpsertableValues($entity, $id);
            }

            // Unset this queued insert, so that the prepareUpdateData() method knows right away
            // (for the next entity already) that the current entity has been written to the database
            // and no extra updates need to be scheduled to refer to it.
            //
            // In \Doctrine\ORM\UnitOfWork::executeInserts(), the UoW already removed entities
            // from its own list (\Doctrine\ORM\UnitOfWork::$entityInsertions) right after they
            // were given to our addInsert() method.
            unset($this->queuedInserts[$key]);
        }
    }
}
