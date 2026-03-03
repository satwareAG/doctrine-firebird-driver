# /speckit.checklist

Validate requirements quality for a specific domain. "Unit tests for English."

## Instructions

1. Read the current `spec.md`
2. Apply the domain-specific checklist below
3. Report pass/fail for each item
4. Suggest improvements for failed items

## Available Domains

Specify domain after command: `/speckit.checklist [domain]`

### Domain: `api` (default for this driver)

- [ ] Are all public PHP method signatures specified?
- [ ] Are all configuration parameters documented with types and valid ranges?
- [ ] Are error/exception types specified for each failure mode?
- [ ] Are return types specified for all methods?
- [ ] Are nullable parameters explicitly marked?
- [ ] Is backward compatibility impact documented?

### Domain: `firebird`

- [ ] Are Firebird version differences documented (2.5 vs 3.0 vs 4.0 vs 5.0)?
- [ ] Are Firebird-specific data type mappings specified?
- [ ] Are transaction behavior requirements specified?
- [ ] Are NULL handling edge cases documented?
- [ ] Are BLOB handling requirements specified?
- [ ] Are character set/encoding requirements specified?

### Domain: `testing`

- [ ] Does every functional requirement have at least one acceptance scenario?
- [ ] Are negative/error scenarios specified?
- [ ] Are Firebird version-specific test cases identified?
- [ ] Are performance test criteria specified (if applicable)?
- [ ] Are integration test boundaries defined?
- [ ] Is the test data setup/teardown strategy specified?

### Domain: `security`

- [ ] Are SQL injection prevention requirements specified?
- [ ] Are credential handling requirements specified?
- [ ] Are error message sanitization requirements specified?
- [ ] Are input validation requirements specified?
- [ ] Are transaction isolation requirements specified?

### Domain: `performance`

- [ ] Are query performance targets specified (if applicable)?
- [ ] Are index usage requirements documented?
- [ ] Are connection pooling requirements specified?
- [ ] Are BLOB streaming vs loading requirements specified?
- [ ] Are LIKE CAST performance implications documented?

### Domain: `compatibility`

- [ ] Is the minimum PHP version specified?
- [ ] Is the minimum DBAL version specified?
- [ ] Is the minimum ext-firebird version specified?
- [ ] Are all supported Firebird server versions listed?
- [ ] Are breaking changes from previous versions documented?

## Output Format

```text
## Checklist Report: [domain] — ###-feature-name

| Item | Status | Notes |
|------|--------|-------|
| [Requirement] | ✅ Pass / ❌ Fail | [Improvement suggestion] |

### Summary: X/Y passed

### Required Actions
- [Failed item]: [Specific improvement needed]
```
