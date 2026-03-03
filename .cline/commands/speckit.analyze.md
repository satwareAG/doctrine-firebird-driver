# /speckit.analyze

Cross-artifact consistency check across spec, plan, and tasks.

## Instructions

1. Read all artifacts for the current feature:
   - `spec.md`, `plan.md`, `tasks.md`, `data-model.md`, `research.md`
2. Run all 5 checks below
3. Report findings with severity (🔴 Critical / 🟡 Warning / 🟢 OK)
4. Suggest fixes for all Critical and Warning findings

## Check 1: Duplication Detection

Scan for requirements stated in multiple places with different wording.
- Same requirement in spec AND plan → consolidate
- Same entity defined differently in spec vs data-model → align

## Check 2: Ambiguity Detection

Scan for vague language:
- "should work", "handle properly", "as needed" → require specific criteria
- Missing error handling specifications
- Undefined Firebird version behavior

## Check 3: Coverage Gaps

Verify every spec requirement has:
- A corresponding plan section
- At least one task in tasks.md
- At least one test task

## Check 4: Constitution Alignment

Verify against `.specify/memory/constitution.md`:
- Article III (Test-First): Every implementation task has a preceding test task
- Article IV (Multi-Version): FB 2.5/3.0/4.0/5.0 test tasks exist
- Article V (Static Analysis): PHPStan/Psalm tasks in Phase 4
- Article IX (Documentation): README/CHANGELOG tasks in Phase 4

## Check 5: Inconsistency Detection

- Spec says X, plan implements Y → flag
- Task references non-existent file path → flag
- Plan uses DBAL 4.x API but spec targets 3.x branch → flag

## Output Format

```text
## Analysis Report: ###-feature-name

### 🔴 Critical (must fix before implement)
- [Finding]: [Location] → [Suggested fix]

### 🟡 Warning (should fix)
- [Finding]: [Location] → [Suggested fix]

### 🟢 OK
- [Check]: Passed

### Recommendation
[Proceed / Fix criticals first / Major revision needed]
```
