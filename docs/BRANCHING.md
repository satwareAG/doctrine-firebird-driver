# Branching Strategy - doctrine-firebird-driver

## Overview

This project uses a single active long-lived branch plus one dormant future-major branch.
There is no separate `main`, `develop`, or `-dev` integration branch.

Routine maintenance, bug fixes, dependency cleanup, and contributor PRs target `3.10.x`.
The `4.4.x` branch exists for the future DBAL 4 line, but it is not an active day-to-day
integration branch yet.

---

## Branches

| Branch | Role | Notes |
|--------|------|-------|
| `3.10.x` | Active default branch | Protected. Source of truth for DBAL 3.10-compatible development and releases. |
| `4.4.x` | Future major branch | Protected. Holds early DBAL 4 compatibility work, but is currently dormant. |
| `feat/DESCRIPTION` | Feature branch | Short-lived branch from `3.10.x`, opened back into `3.10.x`. |
| `fix/DESCRIPTION` | Bugfix branch | Short-lived branch from `3.10.x`, opened back into `3.10.x`. |
| `docs/DESCRIPTION` | Documentation branch | Short-lived branch from `3.10.x`, opened back into `3.10.x`. |
| `chore/DESCRIPTION` | Maintenance branch | Short-lived branch from `3.10.x`, opened back into `3.10.x`. |

---

## Branch Rules

### `3.10.x`

- **Default branch** - all routine PRs target this branch.
- **Protected** - no direct pushes.
- **Stable development trunk** - used for both ongoing maintenance and releases.
- **Required checks** - CI and quality workflows must pass before merge.
- **Preferred merge style** - squash merge with a conventional commit title.

Recommended GitHub ruleset for `3.10.x`:

- require pull requests
- require required status checks
- require conversation resolution
- require linear history
- enable auto-merge
- auto-delete merged branches

Merge queue is optional and should stay disabled until concurrent PR volume justifies it.

### `4.4.x`

- **Protected** - no direct pushes.
- **Dormant by default** - do not mirror routine cleanup or dependency churn here while the
  branch is inactive.
- **Reactivation rule** - before active DBAL 4 work resumes, sync `4.4.x` from the latest
  `3.10.x` first, then continue with DBAL 4-specific commits on top.

### Short-lived branches

- Branch from `3.10.x`.
- Use one branch per logical change.
- Open the PR back to `3.10.x`.
- Delete the branch after squash-merge.

---

## Release Tagging

All DBAL 3 releases are tagged from `3.10.x`.

```text
v3.10.PATCH
```

| Component | Description |
|-----------|-------------|
| `3.10` | DBAL 3.10 compatibility line |
| `PATCH` | Bug fixes, CI improvements, documentation, and dependency maintenance |

### Tag Procedure

```bash
git checkout 3.10.x
git pull origin 3.10.x
# Update CHANGELOG.md and version references
git tag -a vX.Y.Z -m "chore(release): vX.Y.Z"
git push origin vX.Y.Z
```

---

## Commit Convention

Use [Conventional Commits](https://www.conventionalcommits.org/):

```text
<type>(<scope>): <subject>
```

| Type | Usage |
|------|-------|
| `feat` | New feature or user-visible enhancement |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `chore` | CI, build, tooling, release, dependency housekeeping |
| `test` | Test-only changes |
| `refactor` | Refactor without behavior change |
| `perf` | Performance improvement |

---

## PR Workflow

1. Branch from `3.10.x` with `feat/`, `fix/`, `docs/`, or `chore/`.
2. Keep the PR focused on one logical change.
3. Open the PR against `3.10.x`.
4. Wait for all required CI and quality checks to pass.
5. Squash-merge with a clean conventional commit title.
6. Auto-delete the branch after merge.

---

## Forward-port Policy for `4.4.x`

While `4.4.x` is dormant, do not manually duplicate every cleanup change.

Instead:

1. Merge routine cleanup and maintenance into `3.10.x` only.
2. If a change should later land on `4.4.x`, mark it in the PR description and, if available,
   label it `forward-port:4.4.x`.
3. When `4.4.x` becomes active again, sync it once from the latest `3.10.x`.
4. Reapply only the DBAL 4-specific branch deltas that still matter.

Prefer merge-forward or rebase-forward during branch reactivation. Use cherry-picks only for
small isolated fixes when the branches have meaningfully diverged.

---

## DBAL 4.x Migration Path

The `4.4.x` branch already exists and contains initial DBAL 4 compatibility work. Before active
development resumes there:

1. Sync `4.4.x` from the latest `3.10.x`.
2. Re-validate the documented blockers in `docs/learnings/2026-03-18-dbal4-initial-migration-blockers.md`.
3. Re-run CI with DBAL 4 constraints.
4. Resume DBAL 4-specific implementation on top of the synchronized branch.

This reduces manual forward-port churn and keeps contributor guidance focused on the active line.

---

## Stale Branch Cleanup

After merging a PR:

```bash
git branch -d feat/my-feature
git push origin --delete feat/my-feature
```

GitHub should also be configured to auto-delete merged branches.

---

*Last updated: 2026-04-03*
