# Next Steps — doctrine-firebird-driver

**Last session:** 2026-03-18 (Final DBAL 3 release prepared, v3.12.2 tagged)
**Branch:** `3.10.x` | **Status:** Maintenance Mode

---

## ✅ DBAL 3 series (3.10.x branch) - php-firebird v8 UPGRADE - COMPLETE

All goals for the v8 upgrade and modernization have been fulfilled:
- ✅ **php-firebird v8.0.0 Requirement** — Updated `composer.json` and removed all legacy guards.
- ✅ **Test Suite Modernized** — Removed redundant version checks, deleted SQLite-only tests, and adapted cross-platform tests for Firebird.
- ✅ **Upstream Collaboration** — Created 7 GitHub issues (#119-#125) to fix regressions and improve the extension API.

---

## 🚀 Future: DBAL 4.x Migration (4.4.x branch)

The project now transitions to the `4.4.x` branch for active development.

### Roadmap for 4.4.x:
1. **Branch Setup**: Create `4.4.x` from `3.10.x`.
2. **Dependency Update**: Require `doctrine/dbal: ^4.1`.
3. **API Refactoring**:
   - Replace all `getWrappedConnection()` calls in tests with the unwrapping logic researched in `docs/research/dbal4-migration.md`.
   - Update `lastInsertId()` return types to match DBAL 4 interface.
   - Remove `ServerInfoAwareConnection` and other deprecated interfaces.
4. **CI Matrix**: Update CI to include PHP 8.4/8.5 and Firebird 4/5 as primary targets.

---

## 📦 Maintenance (3.10.x branch)

The `3.10.x` branch will receive only critical security fixes and major bug fixes. All new features will be targeted at the `4.4.x` branch.

### Final Verification for v3.12.2:
- Tag created: `v3.12.2`
- Branch merged: `3.10.x-dev` → `3.10.x`
- Issues/Milestones closed in GitHub.
