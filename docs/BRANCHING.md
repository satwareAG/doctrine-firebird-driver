# Branching Strategy — doctrine-firebird-driver

## Overview

This project uses a simplified trunk-based model with a single long-lived release branch.
There is no separate `main` or `develop` branch.

---

## Branches

| Branch | Role | Notes |
|--------|------|-------|
| `3.0.x` | Primary development + release trunk | All PRs target this branch. Tags live here. |
| `feat/DESCRIPTION` | Feature branch | Short-lived, squash-merged into `3.0.x`. |
| `fix/DESCRIPTION` | Bugfix branch | Short-lived, squash-merged into `3.0.x`. |
| `4.0.x` *(future)* | DBAL 4.x migration | Not yet created. See [DBAL 4.x path](#dbal-4x-migration-path) below. |

---

## Branch Rules

### `3.0.x`

- **Protected** - no direct pushes; all changes via PR.
- **Squash-merge only** - every PR results in one clean commit on `3.0.x`.
- **All CI checks must pass** before merge (8 matrix jobs + static analysis + CodeQL).
- Source of truth for all releases and tags.

### Feature / Fix Branches

- Naming: `feat/DESCRIPTION` or `feat/issue-N-DESCRIPTION`
- Naming: `fix/DESCRIPTION` or `fix/issue-N-DESCRIPTION`
- Branch from `3.0.x`, target `3.0.x`.
- Delete after squash-merge.
- Keep focused - one logical change per PR.

---

## Release Tagging

All releases are tagged on `3.0.x`.

```text
v3.MINOR.PATCH
```

| Component | Description |
|-----------|-------------|
| `3` | Major (DBAL 3.x compatibility) |
| `MINOR` | New features, middleware, platform support |
| `PATCH` | Bug fixes, CI/infra, dependency updates |

### Tag Procedure

```bash
git checkout 3.0.x
git pull origin 3.0.x
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

1. Branch from `3.0.x` with `feat/` or `fix/` prefix.
2. Keep changes focused (one logical unit per PR).
3. Push to GitHub, open PR targeting `3.0.x`.
4. Wait for all CI checks (8 GitHub Actions matrix jobs).
5. Squash-merge with a clean commit title.
6. Delete feature branch after merge.

---

## DBAL 4.x Migration Path

DBAL 4.x removes `Connection::getWrappedConnection()`, which is currently used for DBAL 3.x
middleware traversal. When DBAL 4.x support is planned:

1. Create a `4.0.x` branch from `3.0.x`.
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

*Last updated: 2026-03-05*
