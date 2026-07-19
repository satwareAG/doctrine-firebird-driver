# Contributing to Doctrine Firebird Driver

Thank you for your interest in contributing to the Doctrine Firebird Driver!

## Quick Start for Contributors

### Prerequisites

1. **PHP 8.2+** with the `ext-firebird` extension available for local non-Docker work
2. **Docker & Docker Compose** for the supported multi-version test workflow
3. **Composer** for dependency management
4. **Git** for version control

### Setup Development Environment

```bash
# Clone the repository
git clone https://github.com/satwareAG/doctrine-firebird-driver.git
cd doctrine-firebird-driver

# Install dependencies
composer install

# Run the default Firebird 3 test flow to verify setup
cd tests && ./phpunit.sh
```

## Running Tests

**Quick test run:**
```bash
cd tests && ./phpunit.sh
```

**All supported CI Firebird versions:**
```bash
cd tests && ./phpunit.sh -v all
```

**For comprehensive testing documentation, see [TESTING.md](TESTING.md)**

The testing guide covers:
- Project test structure
- PHPUnit configuration files
- Writing new tests
- Namespace conventions
- Troubleshooting
- Multi-version testing
- CI/CD integration

## Pull Request Requirements

Before submitting a pull request, ensure your changes meet the following requirements:

### 1. Code Quality

- [ ] **PHP 8.2+ compatibility** - Use modern PHP features supported by the active branch
- [ ] **Type declarations** - All function parameters and return types declared
- [ ] **Strict types** - `declare(strict_types=1);` at top of all files
- [ ] **PSR-12 coding standard** - Run `vendor/bin/phpcs` before committing
- [ ] **PHPStan Level 8** - No static analysis errors (`vendor/bin/phpstan analyse src/ --level=8 --no-progress --memory-limit=1G`)
- [ ] **No deprecations** - Code must not trigger deprecation warnings

### 2. Testing Requirements

- [ ] **All new features have tests** - Unit tests for logic, functional tests for DB features
- [ ] **All tests pass** - Run `cd tests && ./phpunit.sh -v all`
- [ ] **Test coverage maintained** - New code should have ≥80% coverage
- [ ] **Namespace matches directory** - See [TESTING.md](TESTING.md#namespace-and-directory-mapping)
- [ ] **Tests extend proper base class** - `TestCase` for unit, `FunctionalTestCase` for functional

### 3. Documentation

- [ ] **Code comments** - Complex logic explained with inline comments
- [ ] **PHPDoc blocks** - All public methods have proper documentation
- [ ] **CHANGELOG.md updated** - Add entry describing the change
- [ ] **README.md updated** - If adding new features or changing usage

### 4. Commit Messages

Follow **Conventional Commits** format:

```
feat: Add configurable LIKE CAST length

Implements configuration option to set VARCHAR length for LIKE
parameter casting. Defaults to 255 for backward compatibility.

Fixes #16
```

**Commit types:**
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `test:` - Test additions or modifications
- `refactor:` - Code refactoring
- `style:` - Code style changes (formatting, etc.)
- `chore:` - Build process or tooling changes

### 5. Multi-Version Compatibility

The active release line is validated against **Firebird 3.0, 4.0, and 5.0** in CI. Ensure your
changes:

- [ ] Work on all supported Firebird versions
- [ ] Use version checks if feature is version-specific
- [ ] Update version-specific tests if needed

```php
// Example: Version-specific feature
if (version_compare($connection->getServerVersion(), '3.0', '>=')) {
    // Use Firebird 3+ feature
} else {
    // Fallback for older or unsupported server behavior
}
```

> Note: legacy Firebird 2.5 local-only artifacts may still exist in the repository for historical
> debugging, but routine contributor work should target the supported 3.0, 4.0, and 5.0 matrix.

## Development Workflow

### 1. Create a Feature Branch

```bash
git checkout -b feat/configurable-like-cast-length
```

Use descriptive branch names:
- `feat/feature-name` - New features
- `fix/bug-description` - Bug fixes
- `docs/documentation-topic` - Documentation updates
- `chore/maintenance-topic` - CI, dependency, and housekeeping work

Routine pull requests should target **`3.10.x`**. The `4.4.x` branch is reserved for future DBAL 4
work and should only receive changes when that line is actively being resumed.

### 2. Write Tests First (TDD)

```php
// tests/Test/Unit/Platforms/NewFeatureTest.php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use PHPUnit\Framework\TestCase;

final class NewFeatureTest extends TestCase
{
    public function testNewFeatureBehavior(): void
    {
        // Write failing test first
        self::fail('Test not implemented yet');
    }
}
```

### 3. Implement the Feature

```php
// src/Platforms/NewFeature.php
namespace Satag\DoctrineFirebirdDriver\Platforms;

final class NewFeature
{
    // Implementation
}
```

### 4. Run Quality Checks

```bash
# Recommended all-in-one Docker pipeline
cd tests && ./docker-cqc.sh

# Or run the core checks individually from project root
vendor/bin/phpcs

# Static analysis
vendor/bin/phpstan analyse src/ --level=8 --no-progress --memory-limit=1G
vendor/bin/psalm --no-cache

# Run supported Firebird versions
cd tests && ./phpunit.sh -v all

# Back to project root
cd ..
```

### 5. Update Documentation

- Add entry to `CHANGELOG.md`
- Update `README.md` if user-facing changes
- Add inline code documentation

### 6. Commit and Push

```bash
git add .
git commit -m "feat: Add new feature

Detailed description of what was added and why.

Fixes #123"

git push origin feat/feature-name
```

### 7. Open Pull Request

- Provide clear description of changes
- Reference related issues (`Fixes #123`)
- Ensure all CI checks pass
- Request review from maintainers
- Note whether the change should later be forward-ported to `4.4.x`

## Code Style Guidelines

### PHP Coding Standards

The project follows **PSR-12** with additional rules:

```php
<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms;

use Exception\InvalidArgumentException;

/**
 * Example class demonstrating code style.
 */
final class ExampleClass
{
    private const DEFAULT_VALUE = 255;

    public function __construct(
        private readonly int $value = self::DEFAULT_VALUE,
    ) {
        if ($value < 1) {
            throw new InvalidArgumentException('Value must be positive');
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
```

**Key style points:**
- `declare(strict_types=1);` at top of every file
- Final classes by default (unless designed for inheritance)
- Readonly properties where applicable (PHP 8.2+)
- Constructor property promotion
- Type hints for all parameters and return values
- Early returns instead of nested conditionals

### Running Code Style Fixes

```bash
# Auto-fix code style issues
composer cs-fix

# Check code style without fixing
composer cs-check
```

## Testing Guidelines

### Test Structure

```php
<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Iterator;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\MyClass;

final class MyClassTest extends TestCase
{
    public function testMethodWithValidInput(): void
    {
        // Arrange
        $instance = new MyClass();
        
        // Act
        $result = $instance->method('valid');
        
        // Assert
        self::assertSame('expected', $result);
    }
    
    /** @dataProvider provideInvalidInputs */
    public function testMethodThrowsExceptionForInvalidInput(mixed $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        $instance = new MyClass();
        $instance->method($input);
    }
    
    public function provideInvalidInputs(): Iterator
    {
        yield 'null value' => [null];
        yield 'empty string' => [''];
        yield 'negative number' => [-1];
    }
}
```

### Test Categories

| Directory | Purpose | Extends | Database |
|-----------|---------|---------|----------|
| `tests/Test/Unit/` | Isolated component tests | `TestCase` | No |
| `tests/Test/Functional/` | Feature tests with DB | `FunctionalTestCase` | Yes |
| `tests/Test/Integration/` | Full stack integration | varies | Yes |

**See [TESTING.md](TESTING.md) for complete testing guide**

## Common Issues and Solutions

### Namespace Errors

**Problem:** `Class not found` error

**Solution:** Ensure namespace matches directory structure exactly:

```php
// File: tests/Test/Unit/Platforms/MyTest.php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;
//                                      ^^^^ Must match directory
```

### Test Discovery Issues

**Problem:** PHPUnit doesn't find tests

**Solution:** 
1. Run from correct directory: `cd tests`
2. Test methods must be `public` and named `test*`
3. Test class must extend `TestCase` or `FunctionalTestCase`

### Docker Connection Issues

**Problem:** Can't connect to Firebird

**Solution:**
```bash
cd tests
docker compose up -d
sleep 5  # Wait for Firebird to start
./phpunit.sh
```

## Reporting Issues

When reporting bugs or requesting features:

1. **Search existing issues** first to avoid duplicates
2. **Provide clear description** of the problem or feature
3. **Include reproduction steps** for bugs
4. **Specify Firebird version** and PHP version
5. **Attach relevant error messages** and stack traces

**Issue template:**

```markdown
## Description
Clear description of the issue or feature request.

## Environment
- PHP version: 8.2.x or newer
- Firebird version: 3.0.x
- Driver version: x.x.x
- Operating system: Linux/Windows/macOS

## Steps to Reproduce (for bugs)
1. Step one
2. Step two
3. Expected result
4. Actual result

## Code Example
```php
// Minimal code to reproduce the issue
```

## Error Message
```
Full error message and stack trace
```
```

## Getting Help

- **Documentation:** [README.md](../README.md) and [TESTING.md](TESTING.md)
- **Issues:** [GitHub Issues](https://github.com/satwareAG/doctrine-firebird-driver/issues)
- **Discussions:** Check existing issues and pull requests

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Firebird Documentation](https://firebirdsql.org/en/documentation/)
- [Doctrine DBAL Documentation](https://www.doctrine-project.org/projects/dbal.html)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)

## License

By contributing to this project, you agree that your contributions will be licensed under the MIT License.

---

**Thank you for contributing to the Doctrine Firebird Driver!**
