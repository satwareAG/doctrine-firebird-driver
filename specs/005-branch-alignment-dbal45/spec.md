# Spec: Branch layout alignment with upstream doctrine/dbal

## Status: Approved (council dfd-branch-005, 4/4 APPROVE-WITH-CHANGES, unanimous post-dissent-test)

**Branch**: 4.4.x (self-referential: the branch being renamed)
**Date**: 2026-10-31
**Rigor Level**: Spec-Anchored
**Origin**: operator instruction, 2026-10-31 (dialogue, IPADP inspection pass)
**Council ledger**: `council-ledger.md` (this directory)

## Context

Upstream doctrine/dbal renamed its default line: `4.5.x` is default
(`4.4.x` branch retired upstream), maintenance `3.10.x`, development
`4.6.x` (no release yet) and `5.0.x` (next major). This repo's default
branch is `4.4.x` - a name that mirrored a DBAL line upstream has since
retired. Our branch names track DBAL target lines, not our package
versions (releases on `4.4.x` are v4.7.x).

**Downstream coupling (the FOCUS)**: the private GitLab consumer
(alias `private-erp-consumer`, path redacted per IPADP privacy rule -
see `specs/metadata.json` downstream entry) consumes
`satag/doctrine-firebird-driver: ^4.6.2`
by version constraint. Composer resolution is tag-based and rename-proof;
the consumer's future work tracks our DEFAULT branch state. Release flow
(releaser-pleaser) and CI must keep working across the rename without a
gap where default is unprotected or unbuildable.

**Current inventory** (verified 2026-10-31):

| Our branch | Upstream counterpart | Disposition in this spec |
|------------|----------------------|--------------------------|
| `4.4.x` (default) | retired (was 4.4.x) | RENAME to `4.5.x` |
| `3.10.x` | `3.10.x` (active) | KEEP (already aligned) |
| `3.18.x` | none (never existed upstream) | KEEP (frozen legacy line, explicitly pinned in ruleset) |
| (none) | `4.6.x`, `5.0.x` dev lines | DO NOT create (YAGNI - no 4.6/5.0-target work exists; candidates for the follow-up review when DBAL 5.0 nears) |

**All references to `4.4.x`** (25 files, live vs frozen):

- LIVE (must update): `.github/workflows/ci.yml`, `codeql.yml`,
  `releaser-pleaser.yml` (branch filter + head-ref guard),
  `windows.yml`, `.github/ISSUE_TEMPLATE/*.yml` (branch dropdowns),
  `.github/PULL_REQUEST_TEMPLATE.md`, `AGENTS.md`, `README.md`,
  `llms.txt`, `docs/BRANCHING.md`, `.specify/memory/constitution.md`,
  `.specify/templates/*.md`
- FROZEN (do not touch): `specs/00[1-4]/*`, `docs/learnings/*`,
  `CHANGELOG*`, `NEXT_STEPS.md` (deprecated file, archive candidate)
- PROTECTION: ruleset "Release Branch Protection" targets
  `~DEFAULT_BRANCH` + `refs/heads/3.10.x` + `refs/heads/3.18.x` -
  default-branch rule follows the rename automatically (verified via API)
- DEPENDABOT: no `target-branch` set (follows default) - rename-safe

## Approach: three-phase additive-window rename

The chicken-and-egg problem: workflow `on.push.branches` filters are read
from the pushed ref, so a rename-then-push sequence has a window where CI
cannot run on the renamed default (old filters do not match `4.5.x`), and
the ruleset forbids direct pushes (requires PR + green checks).

1. **Phase A (pre-rename, on `4.4.x`)**: additive PR - workflows accept
   BOTH `4.4.x` and `4.5.x` in branch filters; releaser-pleaser guard
   accepts both `releaser-pleaser--branches--4.4.x` and `--4.5.x`;
   issue/PR templates list both; `docs/BRANCHING.md` documents the
   transition; AGENTS.md notes the pending rename. Merge green on `4.4.x`.
2. **Phase B (rename)**: GitHub branch rename `4.4.x` -> `4.5.x` via API
   (preserves history, PRs retarget automatically, redirect for old name).
   Immediately verify: `default_branch == 4.5.x`, ruleset still enforcing
   (`~DEFAULT_BRANCH` follows), CI green on `4.5.x` (filters already
   accept it), soak schedule continuity noted on #167 (cron runs default
   HEAD; SHAs are continuous across the rename).
3. **Phase C (post-rename cleanup, on `4.5.x`)**: subtractive PR - remove
   `4.4.x` from live workflow filters, releaser-pleaser guard narrows to
   `--4.5.x`, templates drop the old option, AGENTS.md/README/llms.txt/
   BRANCHING.md final wording. Zero non-frozen `4.4.x` references
   (grep gate).

Local clones: `git fetch -p; git branch -m 4.4.x 4.5.x; git branch -u
origin/4.5.x; git remote set-head origin -a`.

Consumer handover: handover comment on this repo's tracking issue (IPADP
boundary: no inter-repo writes) with the consumer-side checklist (nothing
to change unless they reference the branch by name; verify their CI).

## Requirements

- **FR-001**: Default branch MUST be named `4.5.x` after execution, with
  identical commit history (rename, not rebase).
- **FR-002**: At no point MUST the default branch be unprotected or
  unbuildable: ruleset enforcement verified immediately after rename.
- **FR-003**: Release flow (releaser-pleaser) MUST remain functional
  across the rename; guard accepts both names during the window.
- **FR-004**: CI MUST validate the DBAL `^4.4` floor line on the new
  default (floor leg from #263 carries over).
- **FR-005**: `3.10.x` and `3.18.x` lines MUST be untouched by the
  rename (ruleset explicit pins stay valid).
- **FR-006**: Historical specs and learning docs MUST NOT be rewritten
  (frozen records; future readers see the names as they were).

### Observed Surface (consumer-visible FRs)

- FR-001 observable at `gh api repos/{owner}/{repo} --jq .default_branch`
  (exact output `4.5.x`) and via `git ls-remote --symref origin HEAD`.
- FR-003 observable: the guard expression in
  `.github/workflows/releaser-pleaser.yml` on the renamed default matches
  the `releaser-pleaser--branches--4.5.x` prefix (grep gate) + releaser
  dry-trigger note on the tracking issue.
- Boundary-crossing: this rename crosses >=2 build lanes (GitHub Actions
  push/PR/schedule triggers) - invariant gates are the ruleset API check
  (FR-002) and the post-rename CI run (FR-004).

### Acceptance scenarios

- **Given** the phase-A PR is merged, **When** branch `4.4.x` is renamed
  to `4.5.x`, **Then** `default_branch == "4.5.x"`, open PRs re-target,
  the next push/PR to `4.5.x` triggers the full matrix incl. the dbal
  floor leg, and the ruleset query shows "Release Branch Protection"
  active with `~DEFAULT_BRANCH`.
- **Given** a consumer CI references `origin/4.4.x` by name, **When** the
  rename lands, **Then** GitHub serves a redirect for old-name fetches
  and the handover comment documents the one-line fix.

## Key entities

- **Branch-line contract**: branch name == DBAL target line; package
  versioning (v4.7.x) is orthogonal (tags carry releases).
- **Transition window**: phase A merge -> phase C merge; both names
  accepted inside the window, only `4.5.x` outside.

## Assumptions & handoffs

- Open PRs #262/#263 merge before phase B (they target `4.4.x`; GitHub
  retargets on rename either way - cleaner to merge first). MERGED 2026-10-31.
- Rollback: GitHub rename is reversible in one API call; phase-A additive
  filters make the renamed branch CI-functional from minute zero, so
  rollback risk is the PR-redirect window only.
- Council evaluation gates this spec before execution (see GATES.md,
  generated post-council).
- Next steps: council (paid openrouter, cap $5) -> unlazy execution ->
  consumer handover -> #167 soak bookkeeping note.

## Council amendments (2026-10-31, ledger dfd-branch-005)

All four seats APPROVE-WITH-CHANGES (R3 unanimous, HOLD). Execution change-set
(these override/supersede the draft text above where they differ):

1. **releaser-pleaser.yml line 34 is a spec gap the draft missed** (found
   independently by S1+S3, verified): the action input `branch: 4.4.x` is
   hard-coded beyond the event filters. Phase A sets
   `branch: ${{ github.ref_name }}` (correct for push AND pull_request_target
   - on PR events ref_name is the base ref; cherry-pick-safe for 3.10.x
   maintenance releases, S3) plus job guard
   `github.ref_name == github.event.repository.default_branch` (S1).
2. **`workflow_dispatch:` added to codeql.yml in Phase A** (S3 verified it
   is missing; ci.yml and windows.yml already have it). The rename API emits
   NO push event, so Phase B verification MUST use explicit dispatch runs
   (S3,S1,S2,S4).
3. **Phase B entry conditions** (composite, S1+S2+S4): merge freeze; zero
   open `releaser-pleaser--branches--4.4.x` PRs; zero queued/in-progress
   releaser-pleaser runs (a queued run started pre-rename fires post-rename
   with the old file+SHA snapshot - S1 verified the window exists); after
   cutover re-check and delete any stray old-prefix branch. Temporary deny
   rule on `refs/heads/4.4.x` folded INTO the existing "Release Branch
   Protection" ruleset (not a standalone ruleset, S4): blocks creation and
   updates (a recreated 4.4.x would re-run the OLD workflow from the pushed
   ref - zombie release-line vector), allows PRs targeting it; removed in
   Phase C.
4. **Phase B verification**: dispatch `ci.yml` (+ `codeql.yml`) on `4.5.x`;
   Actions caches are ref-scoped (cold start on renamed default - slower CI,
   not blocking, S2); soak note on #167: the #167 evidence chain survives the
   rename (SHA + numeric run-id URLs are rename-proof, S3 conceding to S4) -
   only `--branch`-filtered queries split; use `--event=schedule` and log the
   cutover SHA (S4,S3).
5. **Phase C gate (reworded, S2)**: a docs PR merged to `4.5.x` must trigger
   releaser-pleaser with the guarded job EXECUTING (not skipping) and
   targeting `4.5.x`; if releasable commits exist it must produce a
   `releaser-pleaser--branches--4.5.x` release PR; a no-release success is
   recorded as such - never claimed as full publish-path proof. The full
   publish path is proven only when a release actually ships (S4: manufacturing
   a release to satisfy the gate is forbidden). No unresolved old-prefix PRs.
6. **Rollback honesty (all four)**: one-API-call rollback is valid only
   pre-Phase-C; after Phase C a rollback requires restoring dual-name
   automation via a green PR FIRST (S2's counterexample: restored `4.4.x`
   would carry 4.5.x-only workflow filters).
7. **BRANCHING.md policy rule (S4+S1)**: branch names are local policy, not
   upstream contracts; rename only when upstream retires the line our default
   mirrors (kills the rename treadmill - bounded at constraint-line advances).

Conformity notes: composer constraint stays `^4.4 || ^4.5` (no branch-alias in
composer.json - only `dev-4.4.x` -> `dev-4.5.x` changes for dev installs,
S1); consumer tag constraint `^4.6.2` unaffected; `3.10.x`/`3.18.x` untouched;
no dev branches created (S4 must-reject honored).
