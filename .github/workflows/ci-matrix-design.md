# CI Matrix Design for Multi-Version Testing

## Current State Analysis

### Existing Configuration Issues
- **GitHub Actions (.github/workflows/ci.yml)**: 
  - ❌ All 4 Firebird services start simultaneously per job (resource waste)
  - ❌ Tests run sequentially in single Docker container
  - ❌ Network isolation prevents container accessing services
  - ❌ No per-version test result reporting
  - ❌ All tests must pass or all fail (no independence)

### phpunit Configuration Mapping
| Config File | Firebird Service | Version |
|-------------|------------------|---------|
| phpunit.xml | firebird3 | 3.0 (default) |
| phpunit-firebird25.xml | firebird25 | 2.5 |
| phpunit-firebird4.xml | firebird4 | 4.0 |
| phpunit-firebird5.xml | firebird5 | 5.0 |

## Proposed Solution: 2D Matrix Strategy

### Matrix Dimensions
- **PHP Versions**: 8.1, 8.2, 8.3, 8.4
- **Firebird Versions**: 2.5, 3.0, 4.0, 5.0
- **Total Combinations**: 4 × 4 = 16 jobs

### Matrix Configuration

```yaml
strategy:
  fail-fast: false  # Allow all combinations to complete
  matrix:
    php-version: ['8.1', '8.2', '8.3', '8.4']
    firebird-version: ['2.5', '3.0', '4.0', '5.0']
    include:
      - firebird-version: '2.5'
        firebird-image: 'jacobalberty/firebird:2.5-sc'
        phpunit-config: 'phpunit-firebird25.xml'
        service-name: 'firebird25'
      - firebird-version: '3.0'
        firebird-image: 'jacobalberty/firebird:v3'
        phpunit-config: 'phpunit.xml'
        service-name: 'firebird3'
      - firebird-version: '4.0'
        firebird-image: 'jacobalberty/firebird:v4'
        phpunit-config: 'phpunit-firebird4.xml'
        service-name: 'firebird4'
      - firebird-version: '5.0'
        firebird-image: 'jacobalberty/firebird:v5'
        phpunit-config: 'phpunit-firebird5.xml'
        service-name: 'firebird5'
```

### Service Configuration Strategy

Each job will start **ONLY ONE** Firebird service matching its matrix combination:

```yaml
services:
  firebird:
    image: ${{ matrix.firebird-image }}
    env:
      ISC_PASSWORD: masterkey
      TZ: Europe/Berlin
```

### Docker Networking Strategy

Use GitHub Actions service containers with proper networking:
- Service containers automatically joined to bridge network
- PHP container can access via service hostname
- No custom Docker network creation needed

### Job Naming

```yaml
name: PHP ${{ matrix.php-version }} / Firebird ${{ matrix.firebird-version }}
```

Results in clear CI dashboard:
- PHP 8.1 / Firebird 2.5
- PHP 8.1 / Firebird 3.0
- PHP 8.4 / Firebird 5.0
- etc.

### Test Execution

```bash
# Run appropriate phpunit config for Firebird version
vendor/bin/phpunit --configuration tests/${{ matrix.phpunit-config }}
```

### Independent Failure Handling

- `fail-fast: false` ensures all combinations run
- Each combination reports independently
- Overall job fails if ANY combination fails
- Clear dashboard shows which specific combo failed

## Performance Optimization

### Parallel Execution
- All 16 combinations run in parallel
- GitHub Actions free tier: 20 concurrent jobs
- Estimated time per job: ~5-7 minutes
- Total wall-clock time: ~7 minutes (limited by slowest job)

### Build Time Targets
- ✅ Target: <15 minutes total
- ✅ Expected: ~7 minutes actual

### Resource Optimization
- Only 1 Firebird service per job (vs 4 currently)
- Reduced memory footprint per job
- Faster service startup

## Benefits

1. **Clear Reporting**: Each PHP×Firebird combo shows separately
2. **Independent Failures**: One combo failing doesn't block others
3. **Resource Efficient**: Only necessary services per job
4. **Fast Feedback**: Parallel execution, <10 minutes total
5. **Comprehensive Coverage**: All supported combinations validated

## Implementation Steps

1. Create new workflow file or update existing
2. Test with limited matrix first (e.g., PHP 8.4 only)
3. Expand to full matrix after validation
4. Archive old workflow as backup
