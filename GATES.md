# Gates: fix-top3-2026-11-04

OWNS: src/Driver/Firebird/TransactionManager.php, tests/Test/Unit/Driver/TransactionManagerLockRetryTest.php, specs/003-php-firebird-v13-on-dbal4/spec.md

- [x] G1: v4.8.0 release CI concluded success on 8a0c1fb and 4d2b559 (incl. Nov 2-3 scheduled runs)
      EVIDENCE: #278 comment 2026-11-04 (run table, all completed/success)

- [x] G2: spec delta committed before production code (specs/003 lock-retry section, semantics unchanged)
      EVIDENCE: commit 090388a (before test/production commits f778994/3011e4a)

- [x] G3: RED observed - sleeper-injection tests fail against pre-fix code for the right reason (injection seam missing), transcript on #278
      EVIDENCE: #278 comment 2026-11-04 RED transcript (ReflectionException: $sleeper does not exist, commit 090388a code state)

- [x] G4: targeted suite green and deterministic
  CHECK: for i in 1 2 3 4 5; do ./vendor/bin/phpunit --filter "TransactionManagerLockRetry" >/dev/null || exit 1; done && echo LOCK_RETRY_DETERMINISTIC_5X
  EXPECT: LOCK_RETRY_DETERMINISTIC_5X
      EVIDENCE: exit=0; EXPECT=matched; gate-sig=ae03f54c13bae2ab; output-sha256=4b37f40f245bc1599d3cb61e77e4e6b65f342b25f0480cc5e766ee1e7cff6125

- [x] G5: static gates clean, zero new findings (baselines intact)
  CHECK: ./vendor/bin/phpstan analyse --no-progress >/dev/null 2>&1 && ./vendor/bin/psalm --no-cache >/dev/null 2>&1 && ./vendor/bin/phpcs -q >/dev/null 2>&1 && echo STATIC_GATES_CLEAN
  EXPECT: STATIC_GATES_CLEAN
      EVIDENCE: exit=0; EXPECT=matched; gate-sig=803e21176849d5fd; output-sha256=5ea0a6e1844261de61c3add26827a19b9f19ee2b5c607936f1d34d45551fd367

- [x] G6: PR open or merged for #275, required CI watched to concluded success (Windows CI + FB 3/4/5 matrix legs)
  CHECK: gh pr list --state all --search "275 in:title" --json number,state --jq 'map(select(.state == "OPEN" or .state == "MERGED")) | if length > 0 then "PR_FOR_275_OK" else "MISSING" end'
  EXPECT: PR_FOR_275_OK
      EVIDENCE: exit=0; EXPECT=matched; gate-sig=1e2ae148eceaeaf0; output-sha256=a3be56fac2368c91c1bfcc91122276b5af54c9af7ecf9f9eca76d12cdbf82e0c

- [x] G7: spec-kit drift port issue filed on this repo (dedup re-searched at filing time), referenced on #278
  CHECK: gh issue view 280 --json state,title --jq 'if .state == "OPEN" and (.title | test("satware-v1.2.6")) then "SPEC_KIT_DRIFT_FILED" else "MISSING" end'
  EXPECT: SPEC_KIT_DRIFT_FILED
      EVIDENCE: exit=0; EXPECT=matched; gate-sig=1915483730d5ffb5; output-sha256=c1808b6aee3774df86cb8d41c63c05e222bde0a79636a9edc62fa19a208aa320

- [x] G8: #275 closeable (fix merged or PR green, evidence linked)
      EVIDENCE: PR #279 "Fixes #275", all required CI success; merge pending human approval per quality-gate policy
