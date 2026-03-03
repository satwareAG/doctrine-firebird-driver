# /speckit.clarify

Resolve ambiguities in the current feature specification before technical planning.

## Instructions

1. Read the current `spec.md` (detect from git branch or ask user)
2. Scan for `[NEEDS CLARIFICATION]` markers
3. Scan all sections using the taxonomy below
4. Select the TOP 5 highest-impact questions (Impact × Uncertainty)
5. Present questions with multiple-choice options (A-E) or short answer (≤5 words)
6. ALWAYS provide a recommended option with reasoning
7. After user answers, update `spec.md` with clarifications integrated

## Taxonomy Scan

| Category | Scan For |
|----------|----------|
| Functional Scope | Goals, out-of-scope, roles |
| Domain & Data | Firebird data types, version-specific behavior |
| Interaction & UX | API surface, configuration options |
| Non-Functional | Performance targets, Firebird version support |
| Integration | DBAL version compatibility, ext-firebird version |
| Edge Cases | Error handling, transaction behavior, NULL handling |
| Constraints | PHP version floor, backward compatibility |

## Question Format

```text
**Q1**: [Question]

A) [Option] — [brief rationale]
B) [Option] — [brief rationale]
C) [Option] — [brief rationale]

**Recommended**: B — [why this is the best default]
```

## Constraints

- Maximum 5 questions per session
- Multiple choice preferred over open-ended
- Always provide recommended option

## Output

- Updated `spec.md` with Clarifications section populated
- All `[NEEDS CLARIFICATION]` markers resolved

## Validation

- [ ] No remaining [NEEDS CLARIFICATION] markers
- [ ] Clarifications integrated into appropriate sections
- [ ] No contradictory statements remain
- [ ] Spec is ready for planning
