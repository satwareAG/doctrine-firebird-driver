# Implementation Plan: Fix GitHub Actions CI Matrix

[Overview]
Implement a working PHP × Firebird test matrix for the doctrine-firebird-driver CI pipeline by adapting the proven IBSurgeon-based approach from the php-firebird repository.

The current GitHub Actions workflows fail because they use `shivammathur/setup-php` with `extensions: interbase`, but the interbase extension is not available in the standard PHP extension registry. The working solution from satwareAG/php-firebird uses container-based execution with IBSurgeon scripts to install Firebird natively and build the PHP extension from source.

**Matrix Configuration:**
- PHP Versions: 8.1, 8.2, 8.3, 8.4, 8.5 (5 versions)
- Firebird Versions: 2.5, 3.0, 4.0, 5.0 (4 versions)
- Total Combinations: 5 × 4 = 20 parallel jobs
- Coverage Collection: PHP 8.1 / Firebird 3.0 (production baseline)

**Key Differences from Current Approach:**
1. Use `php:X.Y-cli-bookworm` container images (not `shivammathur/setup-php`)
2. Install Firebird via IBSurgeon scripts (not Docker service containers)
3. Build interbase extension from source (not extension registry)
4. Run tests directly in container (not Docker-in-Docker)

[Types]
No new types or interfaces need to be created. This is purely a CI/CD workflow change.

The workflow will use GitHub Actions matrix variables mapped to configuration values:
- `matrix.php-version`: String ('8.1', '8.2', '8.3', '8.4', '8.5')
- `matrix.firebird-version`: String ('2.5', '3.0', '4.0', '5.0')
- `matrix.firebird-script-suffix`: String ('25', '30', '40', '50')
- `matrix.phpunit-config`: String (phpunit XML filename)

[Files]
Single sentence: Create one new workflow file and optionally remove obsolete ones.

**New Files:**
1. `.github/workflows/ci.yml` - Main CI workflow with PHP × Firebird matrix
   - Replaces all existing broken workflows
   - Uses IBSurgeon-based Firebird installation
   - Builds interbase extension from source
   - Runs PHPUnit tests per matrix combination
   - Collects coverage for PHP 8.1 / Firebird 3.0

**Files to Delete (optional, per user preference):**
- `.github/workflows/ci-multi-version.yml` - Broken (uses unavailable interbase extension)
- `.github/workflows/ci-multi-version-test.yml` - Broken (same issue)
- `.github/workflows/ci.yml` (old) - Broken (service container networking issues)

**Files to Keep:**
- `.github/dependabot.yml` - Dependency updates (unrelated)
- `.github/workflows/ci-matrix-design.md` - Design documentation (reference)

[Functions]
Single sentence: No application code changes required; this is a CI workflow update only.

The workflow implements the following logical steps (as shell scripts within GitHub Actions):

1. **System Dependencies Installation** (`Install system dependencies`)
   - apt-get packages: autoconf, build-essential, libicu-dev, netcat-openbsd, wget, curl, etc.
   - Required for building PHP extension and Firebird client libraries

2. **Firebird Server Installation** (`Install Firebird server via IBSurgeon script`)
   - Downloads IBSurgeon firebirdlinuxinstall script based on matrix.firebird-version
   - Script URL pattern: `https://raw.githubusercontent.com/IBSurgeon/firebirdlinuxinstall/refs/heads/main/fb_vanilla-${SUFFIX}.sh`
   - Runs installer non-interactively
   - Starts Firebird server via init script or direct binary execution
   - Verifies server listening on localhost:3050

3. **Firebird Configuration** (`Configure Firebird for CI`)
   - Sets `DatabaseAccess = Full` in firebird.conf (required for /tmp test databases)
   - Restarts Firebird server
   - Creates test database directory with proper permissions
   - Verifies database creation works via isql

4. **PHP Extension Build** (`Build PHP interbase extension`)
   - Clones satwareAG/php-firebird repository (v6.2.0 tag)
   - Runs phpize, configure with --with-interbase=/opt/firebird
   - Compiles extension with make
   - Installs to PHP extension directory
   - Enables extension in php.ini

5. **Composer Dependencies** (`Install Composer dependencies`)
   - Installs Composer via official installer script
   - Runs `composer install --prefer-dist --no-progress`

6. **PHPUnit Test Execution** (`Run PHPUnit tests`)
   - Executes vendor/bin/phpunit with matrix-specific configuration
   - Configuration file determined by matrix.phpunit-config
   - Database host set to localhost (Firebird in same container)
   - Coverage generation for designated combination only

[Classes]
Single sentence: No class modifications required; this is infrastructure-only.

[Dependencies]
Single sentence: No new application dependencies; CI uses IBSurgeon scripts and php-firebird extension source.

**CI Infrastructure Dependencies:**
1. **IBSurgeon firebirdlinuxinstall** - Shell scripts for Firebird installation
   - Repository: https://github.com/IBSurgeon/firebirdlinuxinstall
   - License: BSD (permissive)
   - Versions: fb_vanilla-25.sh, fb_vanilla-30.sh, fb_vanilla-40.sh, fb_vanilla-50.sh

2. **satwareAG/php-firebird** - PHP interbase extension source
   - Repository: https://github.com/satwareAG/php-firebird
   - Tag: v6.2.0 (stable release)
   - Required because PECL interbase is abandoned

3. **Container Images:**
   - `php:8.1-cli-bookworm`
   - `php:8.2-cli-bookworm`
   - `php:8.3-cli-bookworm`
   - `php:8.4-cli-bookworm`
   - `php:8.5-cli-bookworm` (RC/nightly)

**Why Bookworm (Debian 12):**
- IBSurgeon scripts explicitly fail on Debian 13 (trixie)
- Bookworm is current stable Debian
- All PHP versions have official `-cli-bookworm` images

[Testing]
Single sentence: Existing PHPUnit test suite runs unchanged; CI validates all PHP × Firebird combinations.

**Test Execution Strategy:**
- Unit tests: Run on all 20 combinations
- Integration tests: Run on all 20 combinations (database required)
- Functional tests: Run on all 20 combinations (database required)

**PHPUnit Configuration Mapping:**
| Firebird Version | PHPUnit Config | Database Host |
|------------------|----------------|---------------|
| 2.5 | phpunit-firebird25.xml | localhost/3050 |
| 3.0 | phpunit.xml | localhost/3050 |
| 4.0 | phpunit-firebird4.xml | localhost/3050 |
| 5.0 | phpunit-firebird5.xml | localhost/3050 |

**Coverage Configuration:**
- Coverage collected for: PHP 8.1 / Firebird 3.0 (production baseline)
- Coverage format: Clover XML (coverage.xml)
- Upload to: Codecov (using codecov-action@v4)
- JUnit results: junit.xml (also uploaded to Codecov)

**Test Matrix Validation:**
- `fail-fast: false` ensures all 20 combinations run independently
- Each combination reports success/failure separately
- Overall CI succeeds only if ALL combinations pass
- Summary job aggregates results for clear dashboard view

[Implementation Order]
Single sentence: Single workflow file creation with staged validation approach.

**Step 1: Create New Workflow File** (Primary Deliverable)
- Create `.github/workflows/ci.yml` with complete matrix implementation
- Include all 5 PHP versions × 4 Firebird versions
- Include coverage collection for PHP 8.1 / Firebird 3.0
- Include summary job for aggregated status

**Step 2: Verify PHPUnit Configuration Compatibility**
- Review `tests/phpunit.xml` and variant files
- Ensure `db_host` can be overridden or update hardcoded values
- Database path `/firebird/data/` → `/tmp/fb_tests/` for CI

**Step 3: Test Workflow (Manual Trigger)**
- Push to branch, verify GitHub Actions runs
- Check one combination succeeds (e.g., PHP 8.4 / Firebird 5.0)
- Debug any extension build or connection issues

**Step 4: Validate Full Matrix**
- Confirm all 20 combinations execute
- Review job timings (target: < 15 minutes total)
- Verify coverage upload succeeds

**Step 5: Cleanup Old Workflows**
- Once validated, delete or archive broken workflow files
- Update README.md with new CI status badge

---

## Detailed Workflow Implementation

```yaml
name: CI (PHP × Firebird Matrix)

on:
  push:
    branches: ['3.0.*', '3.10-dev', 'main']
  pull_request:
    branches: ['3.0.*', '3.10-dev', 'main']
  schedule:
    - cron: '0 2 * * 1'  # Weekly Monday 2 AM

jobs:
  test:
    name: PHP ${{ matrix.php-version }} / Firebird ${{ matrix.firebird-version }}
    runs-on: ubuntu-latest
    container:
      image: php:${{ matrix.php-version }}-cli-bookworm

    strategy:
      fail-fast: false
      matrix:
        php-version: ['8.1', '8.2', '8.3', '8.4', '8.5']
        firebird-version: ['2.5', '3.0', '4.0', '5.0']
        include:
          - firebird-version: '2.5'
            firebird-script-suffix: '25'
            phpunit-config: 'phpunit-firebird25.xml'
          - firebird-version: '3.0'
            firebird-script-suffix: '30'
            phpunit-config: 'phpunit.xml'
          - firebird-version: '4.0'
            firebird-script-suffix: '40'
            phpunit-config: 'phpunit-firebird4.xml'
          - firebird-version: '5.0'
            firebird-script-suffix: '50'
            phpunit-config: 'phpunit-firebird5.xml'

    env:
      ISC_USER: SYSDBA
      ISC_PASSWORD: masterkey

    steps:
      # ... (implementation steps as documented in [Functions] section)
```

## PHPUnit Configuration Updates Required

The current PHPUnit configs use Docker service hostnames (firebird3, firebird4, etc.) with path `/firebird/data/`. For the container-based approach:

**Change Required:**
- `db_host`: `firebird3/3050` → `localhost/3050`
- `db_dbname`: `/firebird/data/...` → `/tmp/fb_tests/...` (or create symlink)

**Options:**
1. **Modify configs inline** - sed replacement in workflow
2. **Environment variable override** - PHPUnit can read from env
3. **Create CI-specific configs** - Duplicate configs with CI paths

**Recommended:** Use sed to modify configs at runtime:
```bash
sed -i 's|firebird[0-9]*/3050|localhost/3050|g' tests/phpunit*.xml
sed -i 's|/firebird/data/|/tmp/fb_tests/|g' tests/phpunit*.xml
```

## Risk Assessment

**Low Risk:**
- IBSurgeon scripts are actively maintained and tested
- php-firebird extension is owned by satwareAG (full control)
- Bookworm containers are stable LTS

**Medium Risk:**
- PHP 8.5 is pre-release (may have compatibility issues)
- Firebird 2.5 is legacy (may have fewer testers)

**Mitigations:**
- `fail-fast: false` isolates failures
- Can quickly exclude problematic combinations via matrix exclude
