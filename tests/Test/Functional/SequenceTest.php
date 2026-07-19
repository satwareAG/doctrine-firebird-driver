<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Schema\Sequence;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

class SequenceTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDatabasePlatform()->supportsSequences()) {
            // Firebird does not support DROP SEQUENCE IF EXISTS natively, so we
            // use the FunctionalTestCase helper which suppresses "does not
            // exist" errors. This clears any leftover sequence from prior runs
            // so createSequence() in the test does not fail with "already
            // exists".
            $this->dropSequenceIfExists('next_value_test_seq');

            return;
        }

        self::markTestSkipped('The platform does not support sequences.');
    }

    public function testNextValue(): void
    {
        $this->connection->createSchemaManager()->createSequence(
            Sequence::editor()
                ->setUnquotedName('next_value_test_seq')
                ->create(),
        );

        $sql = $this->connection->getDatabasePlatform()
            ->getSequenceNextValSQL('next_value_test_seq');

        $first  = (int) $this->connection->fetchOne($sql);
        $second = (int) $this->connection->fetchOne($sql);

        self::assertGreaterThan($first, $second);
    }
}
