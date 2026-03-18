# Task Breakdown: [FEATURE NAME]

**Plan**: `specs/###-feature-name/plan.md`
**Created**: [DATE]
**Status**: In Progress
**Branch**: `###-feature-name`

---

## Progress Summary

| Phase | Total | Done | Remaining |
|-------|-------|------|-----------|
| 1. Setup | 0 | 0 | 0 |
| 2. Foundational | 0 | 0 | 0 |
| 3. User Stories | 0 | 0 | 0 |
| 4. Polish | 0 | 0 | 0 |
| **Total** | **0** | **0** | **0** |

---

## Phase 1: Setup

- [ ] T001 Create feature branch `###-feature-name` from `4.4.x`
- [ ] T002 [P] Verify Docker test environment starts: `cd tests && docker compose up -d`
- [ ] T003 [P] Verify PHPStan baseline: `vendor/bin/phpstan analyse src/ --level=8`

---

## Phase 2: Foundational

*Prerequisites: Phase 1 complete*

- [ ] T010 [US1] Write failing unit test for [core component]: `tests/Test/Unit/[Path]Test.php`
- [ ] T011 [US1] Write failing functional test for [core component]: `tests/Test/Functional/[Path]Test.php`
- [ ] T012 Create [core class/interface]: `src/[Path].php`

---

## Phase 3: User Stories

### Story 1 - [Title] (P1)

*Prerequisites: Phase 2 complete*

- [ ] T020 [US1] Write failing unit test: `tests/Test/Unit/[Path]Test.php`
- [ ] T021 [US1] Implement [feature]: `src/[Path].php`
- [ ] T022 [US1] [P] Verify Firebird 2.5 compatibility
- [ ] T023 [US1] [P] Verify Firebird 3.0 compatibility
- [ ] T024 [US1] [P] Verify Firebird 4.0 compatibility
- [ ] T025 [US1] [P] Verify Firebird 5.0 compatibility

### Story 2 - [Title] (P2)

*Prerequisites: Story 1 complete*

- [ ] T030 [US2] Write failing unit test: `tests/Test/Unit/[Path]Test.php`
- [ ] T031 [US2] Implement [feature]: `src/[Path].php`

---

## Phase 4: Polish

*Prerequisites: All user stories complete*

- [ ] T090 [P] Run PHPStan Level 8: `vendor/bin/phpstan analyse src/ --level=8`
- [ ] T091 [P] Run Psalm: `vendor/bin/psalm`
- [ ] T092 [P] Run PHP_CodeSniffer: `vendor/bin/phpcs --standard=phpcs.xml.dist src/`
- [ ] T093 [P] Run Rector dry-run: `vendor/bin/rector process src/ --dry-run`
- [ ] T094 Update `README.md` with new configuration/usage
- [ ] T095 Update `CHANGELOG.md` with feature entry
- [ ] T096 [P] Run full test suite: `cd tests && ./phpunit.sh`
- [ ] T097 Final PHPStan check — zero new baseline entries

---

## Task Legend

| Marker | Meaning |
|--------|---------|
| `[P]` | Parallelizable — can run concurrently with other `[P]` tasks |
| `[US1]` | Belongs to User Story 1 |
| `[US2]` | Belongs to User Story 2 |
| `- [x]` | Completed |
| `- [ ]` | Pending |
