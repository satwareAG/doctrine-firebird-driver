# /speckit.implement

Execute all tasks from tasks.md in dependency order.

## Instructions

1. Read `tasks.md` for the current feature
2. Find the first incomplete task (`- [ ]`)
3. Execute it following Baby Steps™ (one task at a time)
4. Mark completed: change `- [ ]` to `- [x]`
5. Run validation after each task
6. Report progress and proceed to next task
7. Halt on non-parallelizable task failure

## Execution Rules

### Test-First (Article III — NON-NEGOTIABLE)
- Write test → Verify it FAILS → Implement → Verify it PASSES
- Never implement before the test exists

### Baby Steps™
- One task at a time
- Validate before proceeding
- <200 LOC per commit

### PHP-Specific Execution

```bash
# After each implementation task:
vendor/bin/phpstan analyse src/ --level=8 --no-progress
vendor/bin/phpcs --standard=phpcs.xml.dist src/ --report=summary

# After test tasks:
php vendor/bin/phpunit tests/Test/Unit/ --no-coverage
```

### Multi-Version Verification
```bash
# Run in Docker for each Firebird version:
cd tests && docker compose -f docker-compose.yml run --rm app-fb25 vendor/bin/phpunit
cd tests && docker compose -f docker-compose.yml run --rm app-fb30 vendor/bin/phpunit
cd tests && docker compose -f docker-compose.yml run --rm app-fb40 vendor/bin/phpunit
cd tests && docker compose -f docker-compose.yml run --rm app-fb50 vendor/bin/phpunit
```

## Progress Reporting

After each task:
```text
✅ T### completed: [description]
📊 Progress: X/Y tasks done (Z%)
⏭️  Next: T### [description]
```

## Failure Handling

- Non-parallel task fails → HALT, report error, ask for guidance
- Parallel task fails → Continue others, report all failures at end
- PHPStan regression → HALT, fix before proceeding

## Output

- Updated `tasks.md` with completed tasks marked `[x]`
- Implementation code files
- Test files
- Updated `README.md` and `CHANGELOG.md` (Phase 4)
