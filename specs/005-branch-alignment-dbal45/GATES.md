# Gates: branch layout alignment with upstream doctrine/dbal (spec 005)

OWNS: .github/workflows/**, .github/ISSUE_TEMPLATE/**, .github/PULL_REQUEST_TEMPLATE.md, AGENTS.md, docs/BRANCHING.md, README.md, llms.txt, specs/005-branch-alignment-dbal45/**

- [ ] G1: council synthesis recorded in repo (ledger copied into spec dir)
  CHECK: test -f "$(git rev-parse --show-toplevel)/specs/005-branch-alignment-dbal45/council-ledger.md" && grep -q "APPROVE-WITH-CHANGES 4/4" "$(git rev-parse --show-toplevel)/specs/005-branch-alignment-dbal45/council-ledger.md" && echo "council-ledger-recorded"
  EXPECT: council-ledger-recorded
  EVIDENCE: pending

- [ ] G2: Phase A additive-window PR merged green (filters dual-name, line-34 fix, codeql dispatch, templates, docs notes)
  CHECK: cd "$(git rev-parse --show-toplevel)" && git log --oneline -20 | grep -qiE "phase a" && grep -q "'4.5.x'" .github/workflows/ci.yml && grep -q "github.ref_name" .github/workflows/releaser-pleaser.yml && grep -c "workflow_dispatch" .github/workflows/codeql.yml | grep -qv "^0$" && echo "phase-a-landed"
  EXPECT: phase-a-landed
  EVIDENCE: pending

- [ ] G3: pre-rename conditions hold (no open release PRs, no queued releaser-pleaser runs, freeze recorded)
  CHECK: cd "$(git rev-parse --show-toplevel)" && test -z "$(gh pr list --state open --json number --jq '.[] | select(.headRefName | startswith("releaser-pleaser--branches--")) | .number')" && test -z "$(gh run list -w releaser-pleaser.yml --status queued --json databaseId --jq '.[].databaseId')" && test -z "$(gh run list -w releaser-pleaser.yml --status in_progress --json databaseId --jq '.[].databaseId')" && echo "pre-rename-clean"
  EXPECT: pre-rename-clean
  EVIDENCE: pending

- [ ] G4: rename executed, default is 4.5.x, ruleset active incl. temporary 4.4.x deny
  CHECK: cd "$(git rev-parse --show-toplevel)" && test "$(gh api repos/satwareAG/doctrine-firebird-driver --jq .default_branch)" = "4.5.x" && gh api repos/satwareAG/doctrine-firebird-driver/rulesets --jq '.[] | select(.name == "Release Branch Protection") | .enforcement' | grep -qx active && echo "rename-verified"
  EXPECT: rename-verified
  EVIDENCE: pending

- [ ] G5: dispatched CI run green on renamed default (no push event fires on rename)
  CHECK: cd "$(git rev-parse --show-toplevel)" && gh run list -w ci.yml --branch 4.5.x --limit 3 --json conclusion,event,headBranch --jq '[.[] | select(.event == "workflow_dispatch")] | first | .conclusion' | grep -qx success && echo "dispatch-green"
  EXPECT: dispatch-green
  EVIDENCE: pending

- [ ] G6: Phase C cleanup PR merged; zero non-frozen 4.4.x references (grep gate)
  CHECK: cd "$(git rev-parse --show-toplevel)" && test -z "$(grep -rl '4\.4\.x' --include='*.yml' --include='*.md' --include='*.json' --include='*.txt' . 2>/dev/null | grep -vE 'vendor/|archive/|CHANGELOG|\.git/|docs/learnings/|docs/research/|docs/eod/|docs/plans/|specs/00[1-4]-|NEXT_STEPS.md|specs/005-branch-alignment-dbal45/|docs/BRANCHING.md|specs/metadata.json')" && echo "phase-c-clean"
  EXPECT: phase-c-clean
  EVIDENCE: pending
  NOTE: exclusions are frozen/historical records (dated docs, specs 001-004, changelogs, NEXT_STEPS deprecated file) plus two designated provenance locations: docs/BRANCHING.md (the branch-name policy note records the rename once, canonically) and specs/metadata.json (the dbal relationship describes upstream's retired 4.4.x branch as upstream fact). Gate refinement during execution, council change-set item 1 context, recorded here per unlazy ABANDON/NOTE discipline.

- [ ] G7: handover + soak bookkeeping posted (cutover SHA, release-continuity evidence, #167 note)
  CHECK: cd "$(git rev-parse --show-toplevel)" && gh issue view 258 --json comments --jq '[.comments[].body] | join("\n")' | grep -q "cutover SHA" && gh issue view 167 --json comments --jq '[.comments[].body] | join("\n")' | grep -q "4\.5\.x" && echo "handover-recorded"
  EXPECT: handover-recorded
  EVIDENCE: pending

- [ ] G8: local clone migrated (branch renamed, tracking origin/4.5.x)
  CHECK: cd "$(git rev-parse --show-toplevel)" && test "$(git branch --show-current)" = "4.5.x" && test "$(git rev-parse --abbrev-ref '@{upstream}')" = "origin/4.5.x" && echo "local-clone-migrated"
  EXPECT: local-clone-migrated
  EVIDENCE: pending

- [ ] G9: full release-publish path proven on 4.5.x (MANUAL, deferred - no CHECK oracle; proven only when the next natural release ships via releaser-pleaser; manufacturing a release to satisfy the gate is forbidden per council R3 S2/S4; if no release ships before 2026-12-31 re-evaluate with the operator)
  EVIDENCE: pending
