# CI Local Validation Report (act)

**Date:** 2025-12-24  
**Status:** ✅ Complete  
**Tool:** act v0.2.83

## Executive Summary

Local CI validation using `act` is now supported with specific workarounds for service container port conflicts. Static analysis has **1:1 parity** with GitHub Actions. Matrix test jobs require serialization due to port binding limitations.

## Jobs Validated

### 1. Static Analysis Job ✅ PASSED

```bash
# Direct command (1:1 parity with GitHub Actions)
act -j static-analysis -P ubuntu-latest=catthehacker/ubuntu:act-latest

# Or using local test script
./tests/act-local-test.sh static
```

**Results:**
- PHPStan Level 8: ✅ No errors
- Composer dependencies: ✅ Installed correctly
- Baseline file: ✅ Applied for extension stubs

### 2. Test Jobs (Matrix) ⚠️ REQUIRES WORKAROUND

**Issue:** All matrix jobs attempt to bind to port 3050 simultaneously, causing port conflicts.

**Root Cause:** `act` starts matrix jobs in parallel, but each Firebird service container uses `ports: - 3050:3050`, creating conflicts.

**Solution:** Use the local test script which manages Firebird containers externally:

```bash
# Test against Firebird 5.0 (default)
./tests/act-local-test.sh test 5

# Test against Firebird 4.0
./tests/act-local-test.sh test 4

# Test against Firebird 3.0
./tests/act-local-test.sh test 3
```

## Files Created

| File | Purpose |
|------|---------|
| `.actrc` | Default act configuration (runner image, concurrency limit) |
| `tests/act-local-test.sh` | Local CI test script with 1:1 parity |

## act Limitations Discovered

### 1. Port Binding Conflicts
- Matrix jobs with service containers all bind to same port
- `--concurrent-jobs 1` doesn't prevent parallel matrix job initialization
- **Workaround:** Use local test script that manages containers externally

### 2. Missing Commands in Runner Image
- `nc` (netcat) not available in `catthehacker/ubuntu:act-latest`
- Health check step fails if using `nc -z localhost 3050`
- **Solution:** CI workflow uses `fbsvcmgr` which is available in Firebird container

### 3. Service Container Networking
- act uses isolated Docker networks per job
- From main container, service is accessible via hostname `firebird` (not `localhost`)
- Port mapping (`-p 3050:3050`) still required for host access

## Recommended Local CI Workflow

### Quick Validation (Static Analysis)
```bash
# For fast feedback on code quality
./tests/act-local-test.sh static
```

### Full Testing (Any Firebird Version)
```bash
# Test with specific Firebird version
./tests/act-local-test.sh test 5

# Or use existing docker-compose testing
./tests/docker-cqc.sh
```

### Pre-Push Checklist
```bash
# 1. Static analysis
./tests/act-local-test.sh static

# 2. Quick test with primary Firebird version
./tests/act-local-test.sh test 5

# 3. Push to GitHub and let full matrix run remotely
git push
```

## Comparison: act vs GitHub Actions

| Feature | act (Local) | GitHub Actions |
|---------|-------------|----------------|
| Static Analysis | ✅ 1:1 parity | ✅ |
| Matrix Jobs | ⚠️ Sequential only | ✅ Parallel |
| Service Containers | ⚠️ Port conflicts | ✅ Isolated |
| Runner Image | catthehacker/ubuntu | ubuntu-latest |
| PHP Setup Action | ✅ Works | ✅ |
| Artifact Cache | ✅ Works | ✅ |
| Codecov Upload | ⚠️ Skipped locally | ✅ |

## Conclusion

Local CI testing with `act` is viable for:
1. **Static analysis** - Full 1:1 parity
2. **Individual test runs** - Via local test script

For full matrix coverage across all PHP × Firebird combinations, rely on GitHub Actions runners where parallel execution and isolated networking work correctly.

## Commands Reference

```bash
# List available jobs
act -l

# Run static analysis
act -j static-analysis

# Dry run (see what would happen)
act -n

# Run with verbose output
act -v

# Local test script
./tests/act-local-test.sh help
```
