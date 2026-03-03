# Feature Specification: [FEATURE NAME]

**Feature Branch**: `###-feature-name`
**Created**: [DATE]
**Status**: Draft | In Review | Approved
**Author**: Michael Wegener (mw@satware.com)

---

## Overview

[1-2 sentence description of WHAT this feature does and WHY it is needed.
Focus on business/user value, not implementation details.]

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - [Title] (Priority: P1)

**As a** [role/persona]  
**I want** [capability]  
**So that** [value/outcome]

**Why this priority**: [Explain the value and urgency]  
**Independent Test**: [How to verify this story standalone]

**Acceptance Scenarios**:

1. **Given** [initial state], **When** [action taken], **Then** [expected outcome]
2. **Given** [edge case state], **When** [action taken], **Then** [expected outcome]
3. **Given** [error state], **When** [action taken], **Then** [error handling outcome]

---

### User Story 2 - [Title] (Priority: P2)

**As a** [role/persona]  
**I want** [capability]  
**So that** [value/outcome]

**Why this priority**: [Explain the value and urgency]  
**Independent Test**: [How to verify this story standalone]

**Acceptance Scenarios**:

1. **Given** [initial state], **When** [action taken], **Then** [expected outcome]

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST [capability description]
- **FR-002**: System MUST [capability description]
- **FR-003**: System SHOULD [capability description]

### Non-Functional Requirements

- **NFR-001**: [Performance] [measurable target]
- **NFR-002**: [Compatibility] Firebird versions 2.5, 3.0, 4.0, 5.0
- **NFR-003**: [PHP] PHP 8.1+ compatibility

### Key Entities

- **[Entity Name]**: [Description, key attributes, lifecycle]
- **[Entity Name]**: [Description, key attributes, lifecycle]

### Out of Scope

- [Explicitly excluded capability 1]
- [Explicitly excluded capability 2]

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: [Technology-agnostic, measurable outcome]
- **SC-002**: All existing tests continue to pass (zero regressions)
- **SC-003**: PHPStan Level 8 passes with no new baseline entries
- **SC-004**: ≥80% code coverage for new code

### Definition of Done

- [ ] All acceptance scenarios pass
- [ ] Unit tests written and passing
- [ ] Functional tests written and passing (all Firebird versions)
- [ ] PHPStan Level 8 passes
- [ ] PSR-12 compliance verified
- [ ] README.md updated (if user-facing)
- [ ] CHANGELOG.md updated

---

## Constitution Check

- [ ] **Article I** (DBAL Compatibility): [How this complies]
- [ ] **Article II** (PHP Extension): [How this complies]
- [ ] **Article III** (Test-First): Tests written before implementation
- [ ] **Article IV** (Multi-Version): Tested on FB 2.5, 3.0, 4.0, 5.0
- [ ] **Article V** (Static Analysis): PHPStan L8 + Psalm pass
- [ ] **Article VI** (Security): [Security considerations addressed]
- [ ] **Article VII** (Simplicity): No unnecessary abstractions
- [ ] **Article VIII** (Performance): [Performance impact documented]
- [ ] **Article IX** (Documentation): Docs updated
- [ ] **Article X** (Docker Testing): Docker test environment used

---

## Clarifications

*[This section is populated during Phase 3: Clarify]*

| # | Question | Answer | Impact |
|---|----------|--------|--------|
| 1 | [Question] | [Answer] | [How it changed the spec] |

---

## Open Questions

*[Mark with [NEEDS CLARIFICATION] during drafting, resolve before planning]*

- [NEEDS CLARIFICATION]: [Question about ambiguous requirement]
