# Gates: fix-top3-2026-11-04

OWNS: src/Driver/Firebird/TransactionManager.php, tests/Test/Unit/Driver/TransactionManagerLockRetryTest.php, specs/003-php-firebird-v13-on-dbal4/spec.md

- [x] G1: v4.8.0 release CI concluded success on 8a0c1fb and 4d2b559 (incl. Nov 2-3 scheduled runs)
      EVIDENCE: #278 comment 2026-11-04 (run table, all completed/success)

- [ ] G2: spec delta committed before production code (specs/003 lock-retry section, semantics unchanged)
      EVIDENCE: pending

- [ ] G3: RED observed - sleeper-injection tests fail against pre-fix code for the right reason (injection seam missing), transcript on #278
      EVIDENCE: pending

- [ ] G4: targeted suite green and deterministic
  CHECK: for i in 1 2 3 4 5; do ./vendor/bin/phpunit --filter "TransactionManagerLockRetry" >/dev/null || exit 1; done && echo LOCK_RETRY_DETERMINISTIC_5X
  EXPECT: LOCK_RETRY_DETERMINISTIC_5X
  EVIDENCE: pending

- [ ] G5: static gates clean, zero new findings (baselines intact)
  CHECK: ./vendor/bin/phpstan analyse --no-progress >/dev/null 2>&1 && ./vendor/bin/psalm --no-cache >/dev/null 2>&1 && ./vendor/bin/phpcs -q >/dev/null 2>&1 && echo STATIC_GATES_CLEAN
  EXPECT: STATIC_GATES_CLEAN
  EVIDENCE: pending

- [ ] G6: PR open or merged for #275, required CI watched to concluded success (Windows CI + FB 3/4/5 matrix legs)
  CHECK: gh pr list --state all --search "275 in:title" --json number,state --jq 'map(select(.state == "OPEN" or .state == "MERGED")) | if length > 0 then "PR_FOR_275_OK" else "MISSING" end'
  EXPECT: PR_FOR_275_OK
  EVIDENCE: pending

- [ ] G7: spec-kit drift port issue filed on this repo (dedup re-searched at filing time), referenced on #278
  CHECK: gh issue list --state open --search "spec-kit satware-v1.2.6 in:title,body" --json number --jq 'if length > 0 then "SPEC_KIT_DRIFT_FILED" else "MISSING" end'
  EXPECT: SPEC_KIT_DRIFT_FILED
  EVIDENCE: pending

- [ ] G8: #275 closeable (fix merged or PR green, evidence linked)
      EVIDENCE: pending
