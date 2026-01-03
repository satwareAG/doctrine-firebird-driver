<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatformConfiguration;

/**
 * Tests for FirebirdPlatform configuration integration
 */
class FirebirdPlatformIntegrationTest extends TestCase
{
    public function testPlatformInitializesWithDefaultConfiguration(): void
    {
        $platform = new Firebird3Platform();

        $config = $platform->getConfiguration();

        self::assertInstanceOf(FirebirdPlatformConfiguration::class, $config);
        self::assertSame(255, $config->getLikeCastLength());
    }

    public function testSetConfigurationReturnsFluentInterface(): void
    {
        $platform = new Firebird3Platform();
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => 500]);

        $result = $platform->setConfiguration($config);

        self::assertSame($platform, $result);
    }

    public function testGetConfigurationReturnsSetConfiguration(): void
    {
        $platform = new Firebird3Platform();
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => 1000]);

        $platform->setConfiguration($config);

        self::assertSame($config, $platform->getConfiguration());
    }

    public function testGetLikeCastLengthDelegatesToConfiguration(): void
    {
        $platform = new Firebird3Platform();
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => 500]);

        $platform->setConfiguration($config);

        self::assertSame(500, $platform->getLikeCastLength());
    }

    public function testGetLikeCastLengthWithDefaultConfiguration(): void
    {
        $platform = new Firebird3Platform();

        self::assertSame(255, $platform->getLikeCastLength());
    }

    public function testGetVarcharMaxCastLengthDelegatesToConfiguration(): void
    {
        $platform = new Firebird3Platform();
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => 1000]);

        $platform->setConfiguration($config);

        // getVarcharMaxCastLength is protected, but we can test via getLengthExpression
        $lengthExpr = $platform->getLengthExpression('?');

        self::assertStringContainsString('VARCHAR(1000)', $lengthExpr);
    }

    public function testGetBinaryDefaultLengthUsesConfiguration(): void
    {
        $platform = new Firebird3Platform();
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => 500]);

        $platform->setConfiguration($config);

        self::assertSame(500, $platform->getBinaryDefaultLength());
    }

    public function testConfigurationPersistsAcrossMultipleCalls(): void
    {
        $platform = new Firebird3Platform();
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => 750]);

        $platform->setConfiguration($config);

        self::assertSame(750, $platform->getLikeCastLength());
        self::assertSame(750, $platform->getLikeCastLength());
        self::assertSame(750, $platform->getBinaryDefaultLength());
    }

    public function testConfigurationCanBeChanged(): void
    {
        $platform = new Firebird3Platform();

        $config1 = new FirebirdPlatformConfiguration(['like_cast_length' => 100]);
        $platform->setConfiguration($config1);
        self::assertSame(100, $platform->getLikeCastLength());

        $config2 = new FirebirdPlatformConfiguration(['like_cast_length' => 200]);
        $platform->setConfiguration($config2);
        self::assertSame(200, $platform->getLikeCastLength());
    }

    public function testMultiplePlatformInstancesHaveIndependentConfigurations(): void
    {
        $platform1 = new Firebird3Platform();
        $platform2 = new Firebird3Platform();

        $config1 = new FirebirdPlatformConfiguration(['like_cast_length' => 100]);
        $config2 = new FirebirdPlatformConfiguration(['like_cast_length' => 200]);

        $platform1->setConfiguration($config1);
        $platform2->setConfiguration($config2);

        self::assertSame(100, $platform1->getLikeCastLength());
        self::assertSame(200, $platform2->getLikeCastLength());
    }
}
