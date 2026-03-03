# /speckit.tasks

Break down the implementation plan into an ordered, executable task list.

## Instructions

1. Read `spec.md` and `plan.md` for the current feature
2. Generate tasks in dependency order (Phase 1 → 2 → 3 → 4)
3. Write all tasks to `tasks.md` using the required format
4. Mark parallelizable tasks with `[P]`
5. Label user story tasks with `[US1]`, `[US2]`, etc.
6. Include exact file paths for every task

## Required Task Format

```text
- [ ] T### [P?] [US?] Description — `path/to/file.php`
```

## Phase Structure (MANDATORY ORDER)

### Phase 1: Setup
- Git branch creation
- Docker environment verification
- PHPStan baseline verification

### Phase 2: Foundational (Test-First — Article III)
- Write failing unit tests FIRST
- Write failing functional tests FIRST
- Then create implementation stubs

### Phase 3: User Stories (P1 → P2 → P3)
For each story:
1. Write failing test(s)
2. Implement feature
3. Verify FB 2.5/3.0/4.0/5.0 compatibility [P]

### Phase 4: Polish
- PHPStan Level 8 [P]
- Psalm [P]
- PHP_CodeSniffer [P]
- Rector dry-run [P]
- README.md update
- CHANGELOG.md update
- Full test suite run

## Constraints

- Every task must be atomic (single intent, <200 LOC)
- Test tasks MUST precede implementation tasks
- Static analysis tasks are always parallelizable [P]
- Multi-version verification tasks are always parallelizable [P]

## Output

- `tasks.md` — Complete task breakdown with dependency ordering

## Validation

- [ ] All tasks follow `- [ ] T### [markers] Description — path` format
- [ ] Test tasks precede implementation tasks (Article III)
- [ ] Phase 4 includes all static analysis tools
- [ ] File paths specified for every task
- [ ] Progress summary table updated
