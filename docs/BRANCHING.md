# Branching Strategy - doctrine-firebird-driver

## Overview

This project maintains two release lines, each tied to a DBAL major version.
There is no separate `main`, `develop`, or `-dev` integration branch.

The **`4.4.x`** branch is the active release line (DBAL 4, current: v4.5.1).
Routine maintenance, bug fixes, dependency cleanup, and contributor PRs target `4.4.x`.
The **`3.10.x`** branch is in maintenance mode (DBAL 3, last release: v3.19.0) and
receives only critical bug fixes.

---

## Branches

| Branch | Role | Notes |
|--------|------|-------|
| `4.4.x` | Active release branch | Protected. Source of truth for DBAL 4.4-compatible development and releases. Current: v4.5.1. |
| `3.10.x` | Maintenance branch | Protected. DBAL 3.10-compatible line. Last release: v3.19.0. Critical fixes only. |
| `feat/DESCRIPTION` | Feature branch | Short-lived branch from `4.4.x`, opened back into `4.4.x`. |
| `fix/DESCRIPTION` | Bugfix branch | Short-lived branch from `4.4.x`, opened back into `4.4.x`. |
| `docs/DESCRIPTION` | Documentation branch | Short-lived branch from `4.4.x`, opened back into `4.4.x`. |
| `chore/DESCRIPTION` | Maintenance branch | Short-lived branch from `4.4.x`, opened back into `4.4.x`. |

---

## Branch Rules

### `4.4.x`

- **Default branch** - all routine PRs target this branch.
- **Protected** - no direct pushes.
- **Active release trunk** - used for both ongoing development and releases.
- **Required checks** - CI and quality workflows must pass before merge.
- **Preferred merge style** - squash merge with a conventional commit title.

Recommended GitHub ruleset for `4.4.x`:

- require pull requests
- require required status checks
- require conversation resolution
- require linear history
- enable auto-merge
- auto-delete merged branches

Merge queue is optional and should stay disabled until concurrent PR volume justifies it.

### `3.10.x`

- **Protected** - no direct pushes.
- **Maintenance mode** - critical bug fixes only. No new features.
- **Last release** - v3.19.0 (2026-07-19). No further releases planned unless critical bugs are found.
- **Backport rule** - critical fixes from `4.4.x` may be cherry-picked to `3.10.x` if they apply to DBAL 3.x.

### Short-lived branches

- Branch from `3.10.x`.
- Use one branch per logical change.
- Open the PR back to `3.10.x`.
- Delete the branch after squash-merge.

---

## Release Tagging

All DBAL 4 releases are tagged from `4.4.x`. DBAL 3 releases are tagged from `3.10.x`.

```text
v4.5.PATCH    # DBAL 4.4 line (active)
v3.19.PATCH   # DBAL 3.10 line (maintenance)
```

| Component | Description |
|-----------|-------------|
| `4.5` | DBAL 4.4 compatibility line (active) |
| `3.19` | DBAL 3.10 compatibility line (maintenance) |
| `PATCH` | Bug fixes, CI improvements, documentation, and dependency maintenance |

### Tag Procedure

```bash
git checkout 4.4.x
git pull origin 4.4.x
# Update CHANGELOG.md and version references
git tag -a vX.Y.Z -m "chore(release): vX.Y.Z"
git push origin vX.Y.Z
```

## No-Retag Policy

**Tags MUST NOT be force-pushed or deleted after publication.**

Packagist enforces [version immutability](https://packagist.org/about/version-immutability):
once a stable version is crawled, its source reference is locked. Retagging causes
Packagist to block the update, leaving downstream consumers with a stale commit.

If a tagged release has a regression, **publish a new version** (e.g., `v1.2.4`
after `v1.2.3`) instead of retagging. GitHub tag protection rules prevent
accidental force-pushes.

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

1. Branch from `4.4.x` with `feat/`, `fix/`, `docs/`, or `chore/`.
2. Keep the PR focused on one logical change.
3. Open the PR against `4.4.x`.
4. Wait for all required CI and quality checks to pass.
5. Squash-merge with a clean conventional commit title.
6. Auto-delete the branch after merge.

---

## Backport Policy for `3.10.x`

While `3.10.x` is in maintenance mode, do not manually duplicate every change.

Instead:

1. Merge routine cleanup and maintenance into `4.4.x` only.
2. If a change should later land on `3.10.x` (critical bug fix), mark it in the PR
   description and, if available, label it `backport:3.10.x`.
3. Cherry-pick the fix to `3.10.x` if it applies to DBAL 3.x.
4. Tag a new `v3.19.PATCH` release if the fix warrants a release.

Prefer cherry-picks for small isolated fixes. Do not attempt full merges between
the two branches - they have meaningfully diverged (DBAL 3 vs DBAL 4 API).

---

## DBAL 4.x Migration - COMPLETE

The `4.4.x` branch has been fully migrated to DBAL 4.4.x. The migration is
complete and the branch is the active release line (v4.5.1, 2026-07-20).

Key milestones:
- v4.4.0 (2026-07-09): Initial DBAL 4 compatibility
- v4.5.0 (2026-07-19): php-firebird v13.0 integration, full test suite passing
- v4.5.1 (2026-07-20): Test coverage expansion, bug fixes, CI green

See `docs/learnings/2026-03-18-dbal4-initial-migration-blockers.md` for the
historical migration record.

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
