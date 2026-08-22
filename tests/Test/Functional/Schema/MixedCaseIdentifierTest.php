<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

/**
 * Regression coverage for issue #155: identifiers created as case-sensitive
 * (double-quoted) in Firebird must be introspected with their QUOTED-ness
 * preserved, so downstream rendering does not let the server fold them.
 *
 * Firebird stores the exact case for quoted identifiers ("probeMixedView")
 * and folds unquoted ones (my_view -> MY_VIEW). The introspection layer can
 * use the presence of lowercase characters as the heuristic signal: a name
 * containing lowercase characters was necessarily created quoted.
 */
final class MixedCaseIdentifierTest extends FunctionalTestCase
{
    private const VIEW_NAME  = 'probeMixedView';
    private const SEQ_NAME   = 'probeMixedSeq';
    private const TABLE_NAME = 'mixed_case_src';

    public function testIntrospectedQuotedViewRendersAsQuoted(): void
    {
        $sm = $this->connection->createSchemaManager();

        $this->dropTableIfExists(self::TABLE_NAME);
        $this->connection->executeStatement('CREATE TABLE ' . self::TABLE_NAME . ' (id INTEGER)');
        $this->createdTables[] = self::TABLE_NAME;

        $this->connection->executeStatement(
            'CREATE VIEW "' . self::VIEW_NAME . '" AS SELECT id FROM ' . self::TABLE_NAME,
        );

        try {
            $view = null;
            foreach ($sm->listViews() as $candidate) {
                if ($candidate->getName() === self::VIEW_NAME) {
                    $view = $candidate;
                    break;
                }
            }

            self::assertNotNull($view, 'View must be found under its exact stored case');

            // Issue #155 regression: the rendered identifier must carry quotes,
            // otherwise executing it lets Firebird fold to PROBEMIXEDVIEW and
            // reference a different (non-existent) object.
            self::assertSame(
                '"' . self::VIEW_NAME . '"',
                $view->getObjectName()->toString(),
                'Introspected quoted mixed-case view must render as a quoted identifier',
            );

            // End-to-end proof: the rendered identifier resolves against the
            // original object.
            $this->connection->executeStatement(
                $this->connection->getDatabasePlatform()->getDropViewSQL($view->getObjectName()->toString()),
            );
        } finally {
            try {
                $this->connection->executeStatement('DROP VIEW "' . self::VIEW_NAME . '"');
            } catch (Throwable) {
            }
        }
    }

    public function testIntrospectedQuotedSequenceRendersAsQuoted(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSequences()) {
            self::markTestSkipped('The platform does not support sequences.');
        }

        $sm = $this->connection->createSchemaManager();

        $this->dropSequenceIfExists(self::SEQ_NAME);
        $this->connection->executeStatement('CREATE SEQUENCE "' . self::SEQ_NAME . '"');

        try {
            $sequence = null;
            foreach ($sm->listSequences() as $candidate) {
                if ($candidate->getName() === self::SEQ_NAME) {
                    $sequence = $candidate;
                    break;
                }
            }

            self::assertNotNull($sequence, 'Sequence must be found under its exact stored case');

            self::assertSame(
                '"' . self::SEQ_NAME . '"',
                $sequence->getObjectName()->toString(),
                'Introspected quoted mixed-case sequence must render as a quoted identifier',
            );

            // End-to-end proof via the rendered identifier.
            $this->connection->executeStatement('DROP SEQUENCE ' . $sequence->getObjectName()->toString());
        } finally {
            try {
                $this->connection->executeStatement('DROP SEQUENCE "' . self::SEQ_NAME . '"');
            } catch (Throwable) {
            }
        }
    }
}
