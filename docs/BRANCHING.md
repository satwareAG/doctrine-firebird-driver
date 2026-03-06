# Branching Strategy — doctrine-firebird-driver

## Overview

This project uses a simplified trunk-based model with a single long-lived release branch.
There is no separate `main` or `develop` branch.

---

## Branches

| Branch | Role | Notes |
|--------|------|-------|
| `3.10.x` | Stable release branch | Compatible with DBAL 3.10.x. Protected. |
| `3.10.x-dev` | Primary development trunk | All PRs target this branch. Default branch. |
| `3.0.x` | Legacy maintenance | For older DBAL 3.x versions. |
| `feat/DESCRIPTION` | Feature branch | Short-lived, squash-merged into `3.10.x-dev`. |
| `fix/DESCRIPTION` | Bugfix branch | Short-lived, squash-merged into `3.10.x-dev`. |
| `4.0.x` *(future)* | DBAL 4.x migration | Not yet created. See [DBAL 4.x path](#dbal-4x-migration-path) below. |

---

## Branch Rules

### `3.10.x`

- **Protected** - no direct pushes; all changes via PR from `3.10.x-dev`.
- **Stable Trunk** - source of truth for stable releases (v3.10.z).
- **All CI checks must pass** before merge.

### `3.10.x-dev`

- **Default Branch** - primary integration point for new features and fixes.
- **Merge Target** - all PRs from feature/fix branches should target this branch.
- **All CI checks must pass** before merge.

### Feature / Fix Branches

- Naming: `feat/DESCRIPTION` or `feat/issue-N-DESCRIPTION`
- Naming: `fix/DESCRIPTION` or `fix/issue-N-DESCRIPTION`
- Branch from `3.10.x-dev`, target `3.10.x-dev`.
- Delete after squash-merge.
- Keep focused - one logical change per PR.

---

## Release Tagging

All releases are tagged on `3.10.x`.

```text
v3.10.PATCH
```

| Component | Description |
|-----------|-------------|
| `3.10` | Major.Minor (DBAL 3.10.x compatibility) |
| `PATCH` | Bug fixes, CI/infra, dependency updates |

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

[Conventional Commits](https://www.conventionalcommits.org/) format:

```text
<type>(<scope>): <subject>
```

| Type | Usage |
|------|-------|
| `feat` | New feature or middleware |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `chore` | CI, build, tooling, release |
| `test` | Test-only changes |
| `refactor` | Code refactor without feature/fix |
| `perf` | Performance improvement |

---

## PR Workflow

1. Branch from `3.10.x-dev` with `feat/` or `fix/` prefix.
2. Keep changes focused (one logical unit per PR).
3. Push to GitHub, open PR targeting `3.10.x-dev`.
4. Wait for all CI checks (8 GitHub Actions matrix jobs).
5. Squash-merge with a clean commit title.
6. Delete feature branch after merge.

---

## DBAL 4.x Migration Path

DBAL 4.x removes `Connection::getWrappedConnection()`, which is currently used for DBAL 3.x
middleware traversal. When DBAL 4.x support is planned:

1. Create a `4.0.x` branch from `3.10.x`.
2. Replace `getWrappedConnection()` traversal with DBAL 4.x-compatible approach.
3. Update CI matrix to add DBAL 4.x test runs.
4. Tag `v4.x.y` series from `4.0.x`.
5. Maintain `3.0.x` for Symfony 6.x / DBAL 3.x users.

**Key breaking change**: `getWrappedConnection()` is deprecated in DBAL 3.x and removed in DBAL 4.x.
The current workaround in `FunctionalTestCase::getFirebirdConnection()` must be rewritten.

---

## Stale Branch Cleanup

After merging a PR:

```bash
# Delete locally
git branch -d feat/my-feature

# Delete remotely
git push origin --delete feat/my-feature
```

GitHub branch protection can auto-delete branches after merge.

---

*Last updated: 2026-03-06*
