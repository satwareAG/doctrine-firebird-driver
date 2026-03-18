<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Tools;

use PHPUnit\Framework\TestCase;

use function exec;
use function implode;
use function sprintf;

class PhpunitScriptTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Skipping PhpunitScriptTest on Windows as it requires a Bash environment for Docker.');
        }

        if (file_exists('/.dockerenv')) {
            $this->markTestSkipped('Skipping PhpunitScriptTest inside Docker to avoid recursive execution.');
        }

        if (getenv('CI')) {
            $this->markTestSkipped('Skipping PhpunitScriptTest in CI to avoid nested Docker issues.');
        }

        $this->scriptPath = realpath(__DIR__ . '/../../../phpunit.sh');
    }

    /**
     * Test that the script returns 0 when all tests pass.
     */
    public function testScriptReturnsZeroOnSuccess(): void
    {
        // Create a temporary test file with a passing test
        $tmpTest = __DIR__ . '/PassingTest.php';
        file_put_contents($tmpTest, <<<'PHP'
<?php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Tools;
use PHPUnit\Framework\TestCase;
class PassingTest extends TestCase {
    public function testPassing(): void {
        $this->assertTrue(true);
    }
}
PHP
        );

        $output = [];
        $exitCode = -1;
        $relativePath = 'tests/Test/Unit/Tools/PassingTest.php';
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        exec(sprintf('export SKIP_COMPOSER_UPDATE=true && export PHP_VERSION=%s && %s -- %s 2>&1', $phpVersion, $this->scriptPath, $relativePath), $output, $exitCode);

        unlink($tmpTest);

        self::assertSame(0, $exitCode, 'Script should return 0 for passing tests. Output: ' . implode("\n", $output));
    }

    /**
     * Test that the script handles "OK, but there were issues!" (skipped tests) as success (exit 0).
     */
    public function testScriptReturnsZeroOnSkippedTests(): void
    {
        // Create a temporary test file with only a skipped test
        $tmpTest = __DIR__ . '/SkippedTest.php';
        file_put_contents($tmpTest, <<<'PHP'
<?php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Tools;
use PHPUnit\Framework\TestCase;
class SkippedTest extends TestCase {
    public function testSkipped(): void {
        $this->markTestSkipped('Verification of exit code 0');
    }
}
PHP
        );

        $output = [];
        $exitCode = -1;
        $relativePath = 'tests/Test/Unit/Tools/SkippedTest.php';
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        exec(sprintf('export SKIP_COMPOSER_UPDATE=true && export PHP_VERSION=%s && %s -- %s 2>&1', $phpVersion, $this->scriptPath, $relativePath), $output, $exitCode);

        unlink($tmpTest);

        self::assertSame(0, $exitCode, 'Script should return 0 for skipped tests. Output: ' . implode("\n", $output));
        self::assertStringContainsString('OK, but some tests were skipped!', implode("\n", $output));
    }

    /**
     * Test that the script returns non-zero when tests fail.
     */
    public function testScriptReturnsNonZeroOnFailure(): void
    {
        // Create a temporary test file with a failing test
        $tmpTest = __DIR__ . '/FailingTest.php';
        file_put_contents($tmpTest, <<<'PHP'
<?php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Tools;
use PHPUnit\Framework\TestCase;
class FailingTest extends TestCase {
    public function testFailing(): void {
        $this->fail('Intentional failure for verification');
    }
}
PHP
        );

        $output = [];
        $exitCode = -1;
        $relativePath = 'tests/Test/Unit/Tools/FailingTest.php';
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        exec(sprintf('export SKIP_COMPOSER_UPDATE=true && export PHP_VERSION=%s && %s -- %s 2>&1', $phpVersion, $this->scriptPath, $relativePath), $output, $exitCode);

        unlink($tmpTest);

        self::assertNotSame(0, $exitCode, 'Script should return non-zero for failing tests.');
        self::assertStringContainsString('FAILURES!', implode("\n", $output));
    }
}
