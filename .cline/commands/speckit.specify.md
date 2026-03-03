# /speckit.specify

Transform natural language intent into a structured feature specification.

## Instructions

1. Read `.specify/memory/constitution.md` to understand project principles
2. Parse the user's feature description
3. Determine the next feature number by listing `.specify/specs/` directories
4. Run `.specify/scripts/create-new-feature.sh <number> <slug>` to scaffold
5. Fill in `spec.md` using the template, focusing on WHAT and WHY (not HOW)
6. Mark ambiguities with `[NEEDS CLARIFICATION]`
7. Prioritize user stories as P1 (must-have), P2 (should-have), P3 (nice-to-have)

## Spec Quality Rules

- Each user story MUST be independently testable
- Acceptance scenarios MUST use Given/When/Then format
- Success criteria MUST be technology-agnostic and measurable
- Maximum 3 `[NEEDS CLARIFICATION]` markers before running clarify
- NO implementation details (no class names, no SQL, no PHP code)

## Output

- `.specify/specs/###-feature-name/spec.md` — Feature specification
- Git branch `###-feature-name` created: `git checkout -b ###-feature-name`

## Validation

- [ ] User stories prioritized (P1, P2, P3)
- [ ] Each story independently testable
- [ ] Acceptance scenarios use Given/When/Then
- [ ] Success criteria are technology-agnostic
- [ ] Constitution Check section completed
- [ ] Max 3 [NEEDS CLARIFICATION] markers
