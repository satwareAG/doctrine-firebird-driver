# Implementation Plan: [FEATURE NAME]

**Spec**: `specs/###-feature-name/spec.md`
**Created**: [DATE]
**Status**: Draft | Approved
**Branch**: `###-feature-name`

---

## Technical Context

| Item | Value |
|------|-------|
| **PHP Version** | 8.1+ (target: 8.3+) |
| **DBAL Version** | ^3.10 (3.0.x branch) / ^4.1 (4.0.x branch) |
| **ext-firebird** | ^7.0.0-rc.47 |
| **Test Framework** | PHPUnit 10.5 |
| **Static Analysis** | PHPStan L8 + Psalm |
| **Code Style** | PSR-12 via PHP_CodeSniffer |

---

## Constitution Check

- [ ] **Article I** (DBAL Compatibility): [Validation]
- [ ] **Article II** (PHP Extension): [Validation]
- [ ] **Article III** (Test-First): Tests defined before implementation tasks
- [ ] **Article IV** (Multi-Version): FB 2.5/3.0/4.0/5.0 test matrix defined
- [ ] **Article V** (Static Analysis): PHPStan L8 gates in task list
- [ ] **Article VI** (Security): [Security approach]
- [ ] **Article VII** (Simplicity): [Complexity justification if needed]
- [ ] **Article VIII** (Performance): [Performance impact]
- [ ] **Article IX** (Documentation): Docs tasks included
- [ ] **Article X** (Docker Testing): Docker test tasks included

---

## Architecture

### Affected Components

| Component | File | Change Type |
|-----------|------|-------------|
| [Component] | `src/[Path].php` | New / Modify / Delete |

### Design Decisions

**Decision 1**: [Title]
- **Option A**: [Description] — Pros: [...] Cons: [...]
- **Option B**: [Description] — Pros: [...] Cons: [...]
- **Chosen**: Option [X] because [rationale]

---

## Data Model

*See `data-model.md` for entity definitions.*

Key entities involved:
- **[Entity]**: [Role in this feature]

---

## Contracts

*See `contracts/` directory for interface specifications.*

| Contract | File | Purpose |
|----------|------|---------|
| [Interface/Method] | `contracts/[name].md` | [What it defines] |

---

## Research Findings

*See `research.md` for detailed technical decisions.*

| Topic | Finding | Source |
|-------|---------|--------|
| [Topic] | [Key finding] | [Firebird docs / DBAL docs / etc.] |

---

## Test Strategy

### Unit Tests

| Test Class | File | Coverage Target |
|------------|------|-----------------|
| `[TestClass]` | `tests/Test/Unit/[Path]Test.php` | [What it tests] |

### Functional Tests

| Test Class | File | Firebird Versions |
|------------|------|-------------------|
| `[TestClass]` | `tests/Test/Functional/[Path]Test.php` | 2.5, 3.0, 4.0, 5.0 |

### Integration Tests

| Test Class | File | Purpose |
|------------|------|---------|
| `[TestClass]` | `tests/Test/Integration/[Path]Test.php` | [End-to-end scenario] |

---

## Implementation Phases

### Phase 1: Setup
- Project structure, dependencies, configuration

### Phase 2: Foundational
- Core interfaces, base classes, shared utilities

### Phase 3: User Stories
- P1 stories first, then P2, P3...

### Phase 4: Polish
- Documentation, static analysis fixes, performance tuning

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| [Risk] | Low/Med/High | Low/Med/High | [Mitigation strategy] |

---

## Dependencies

| Dependency | Type | Notes |
|------------|------|-------|
| [Task/Feature] | Blocking | Must complete before this |
| [External] | External | [Firebird version / DBAL version] |
