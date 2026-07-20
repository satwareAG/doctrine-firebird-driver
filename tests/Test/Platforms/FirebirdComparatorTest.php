<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Platforms;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdComparator;
use Satag\DoctrineFirebirdDriver\Test\Schema\AbstractComparatorTestCase;

/**
 * Adapts the 43 standardized comparator tests from DBAL 4.x's
 * {@see AbstractComparatorTestCase} to Firebird.
 *
 * The Firebird comparator ({@see FirebirdComparator}) extends the base
 * DBAL Comparator and adds charset/collation stripping, identifier case
 * normalisation, and default-value whitespace handling. These tests
 * exercise the core comparison logic inherited from the base class.
 *
 * Charset/collation-specific behaviour is covered by the custom unit
 * tests in {@see \Satag\DoctrineFirebirdDriver\Test\Unit\Schema\FirebirdComparatorTest}.
 */
class FirebirdComparatorTest extends AbstractComparatorTestCase
{
    protected function createComparator(ComparatorConfig $config): Comparator
    {
        return new FirebirdComparator(new FirebirdPlatform(), $config);
    }
}
