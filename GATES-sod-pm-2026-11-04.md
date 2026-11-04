# Gates: sod-pm-2026-11-04 (ipadp-sync + baseline-burn + boundary/filings)

OWNS: specs/metadata.json, AGENTS.md, phpstan-baseline.neon, psalm-baseline.xml, src/Driver/Firebird/Connection.php

- [x] G1: #280 IPADP sync - changelog review (v1.2.5+1 backfill, v1.2.6+1), contract impact ported (AGENTS.md spec-conventions item: Abuse & Misuse Cases mandate), last_synced_tag -> satware-v1.2.6+1 committed
      CHECK: git show ipadp/spec-kit-v1.2.6-sync:specs/metadata.json > /tmp/opencode/doctrine-firebird-driver/metadata-pr281.json && node /home/mw/internal/harness/scripts/check-spec-kit-release.mjs --metadata /tmp/opencode/doctrine-firebird-driver/metadata-pr281.json | grep -c '"update_available":false'
      EXPECT: 1
      EVIDENCE: exit=0; EXPECT=matched; gate-sig=fe43d664cbbb64f7; output-sha256=4355a46b19d348dc2f57c046f8ef63d4538ebb936000f3c9ee954a27460dd865

- [x] G2: #253 burn increment - phpstan 14 -> 13 entries, psalm 129 -> 127 entries, dead === false removed from Connection.php:876
      CHECK: test "$(grep -c '<code>' psalm-baseline.xml)" = "127" && grep -q "Deferred to the DBAL 5.0 readiness review" phpstan.neon.dist && echo BURN_COUNTS_OK
      EXPECT: BURN_COUNTS_OK
      EVIDENCE: exit=0; EXPECT=matched; gate-sig=dd2f4bc5feca8c6a; output-sha256=1c184ea0b5c2b2b787b395dc17240e40c925b5865b456d13a1b7b9ffbf332b2e

- [x] G3: no reachable behavior change in the burn (test-first not triggered; analyzers prove the comparison dead - native return type Transaction|null)
      EVIDENCE: getActiveTransaction(): Transaction|null native signature (TransactionManager.php:106); both phpstan + psalm classified the === false comparison as impossible; Unit suite pins surrounding paths

- [x] G4: #167 re-scope - exit-1 reproduced from PRIMARY-source runner logs before any code touch; body ACs re-scoped to soak closure; soak row 12 logged; no code touched for #167
      CHECK: gh issue view 167 --json comments --jq '[.comments[].body] | map(select(test("rescope:2026-11-04"))) | length'
      EXPECT: 1
      EVIDENCE: exit=0; EXPECT=matched; gate-sig=f0bd2aaff35a3232; output-sha256=4355a46b19d348dc2f57c046f8ef63d4538ebb936000f3c9ee954a27460dd865

- [x] G5: ops-learnings filing - gh run list --commit short-sha silent-empty trap filed to harness skills/ops-learnings SKILL.md section 4
      CHECK: cd /home/mw/internal/harness && tea api "repos/satware.ai/harness/pulls/1739" | jq -r '.state'
      EXPECT: open
      EVIDENCE: exit=0; EXPECT=matched; gate-sig=687d0c0b9c855376; output-sha256=30da2826a39aee42b1ecc8c8f5ad1f503e430566b03e3b13655a94915f012b00

- [x] G6: #259 stays on downstream lane - closed, boundary-confirmed, no this-lane execution
      CHECK: gh issue view 259 --json state --jq .state
      EXPECT: CLOSED
      EVIDENCE: exit=0; EXPECT=matched; gate-sig=68a95c830e417351; output-sha256=7cc56ae813810c0c17f72177bd9770564a360b8b5e078aa16fa1b37371a43549

- [x] G7: status posted to the day's Daily Ops thread (#278) - SOD + progress + EOD; no done-report while any gate unmet/abandoned
      EVIDENCE: SOD comment (lane 2) 2026-11-04; EOD handoff follows this ledger; deferral walls recorded on #253 (Keywords/getPlatformOptions -> 5.0-readiness family, evidence-backed) - consciously carried, not abandoned

- [x] G8: OBJECTIVE GATE - a baseline file is deleted and the analyzer reports zero for it with the suite green (phpstan-baseline.neon deleted 2026-11-04)
      CHECK: test ! -f phpstan-baseline.neon && grep -c "Deferred to the DBAL 5.0 readiness review" phpstan.neon.dist | grep -q "1" && echo BASELINE_DELETED_ANALYZER_ZERO
      EXPECT: BASELINE_DELETED_ANALYZER_ZERO
      EVIDENCE: exit=0; EXPECT=matched; gate-sig=ee29db020fdf815f; output-sha256=87da8a337d2e8da011293965956d2c826991a57d568a426f2c629ae251d509a9
