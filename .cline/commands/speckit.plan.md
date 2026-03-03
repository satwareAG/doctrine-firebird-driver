# /speckit.plan

Generate a technical implementation plan from an approved feature specification.

## Instructions

1. Read `.specify/memory/constitution.md`
2. Read the current `spec.md` — verify no `[NEEDS CLARIFICATION]` markers remain
3. Research technical unknowns (Firebird docs, DBAL interfaces, ext-firebird API)
4. Fill in `plan.md` with architecture decisions
5. Create `data-model.md` with entity definitions
6. Create `research.md` with technical findings
7. Create `quickstart.md` with validation scenarios
8. Create `contracts/` files for key interfaces
9. Complete the Constitution Check section

## Research Phase

For each technical unknown:
- Check Firebird version-specific SQL syntax differences
- Check DBAL interface requirements (`doctrine/dbal ^3.10`)
- Check `ext-firebird` function signatures (fbird_* aliases)
- Check existing driver patterns in `src/` for consistency

## Architecture Rules (from Constitution)

- **Article I**: Plan must specify DBAL 3.x vs 4.x compatibility approach
- **Article II**: Use `fbird_*` functions only, no PDO
- **Article IV**: Define test matrix for FB 2.5, 3.0, 4.0, 5.0
- **Article VII**: Justify every new class — no unnecessary abstractions
- **Article VIII**: Document performance implications

## Output

- `plan.md` — Implementation plan with architecture decisions
- `research.md` — Technical decisions and findings
- `data-model.md` — Entity definitions
- `quickstart.md` — Validation scenarios
- `contracts/` — Interface specifications

## Validation

- [ ] Constitution Check section completed (all 10 articles)
- [ ] All gates passed or exceptions documented
- [ ] Tech stack matches project constraints (PHP 8.1+, DBAL ^3.10)
- [ ] Test matrix defined (FB 2.5/3.0/4.0/5.0)
- [ ] No implementation details that contradict the spec
