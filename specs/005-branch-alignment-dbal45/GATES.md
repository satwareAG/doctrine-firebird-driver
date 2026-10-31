# Gates: branch layout alignment with upstream doctrine/dbal (spec 005)

OWNS: .github/workflows/**, .github/ISSUE_TEMPLATE/**, .github/PULL_REQUEST_TEMPLATE.md, AGENTS.md, docs/BRANCHING.md, README.md, llms.txt, specs/005-branch-alignment-dbal45/**

- [x] G1: council synthesis recorded in repo (ledger copied into spec dir)
  CHECK: test -f "$(git rev-parse --show-toplevel)/specs/005-branch-alignment-dbal45/council-ledger.md" && grep -q "APPROVE-WITH-CHANGES 4/4" "$(git rev-parse --show-toplevel)/specs/005-branch-alignment-dbal45/council-ledger.md" && echo "council-ledger-recorded"
  EXPECT: council-ledger-recorded
  EVIDENCE: exit=0; EXPECT=matched; gate-sig=6ed0c848775a949e; output-sha256=68a6c0fa0a7e3052d973efab9b7e131fcb228f76e291984d48e929d5f3119bfe

- [x] G2: Phase A additive-window PR merged green (filters dual-name, line-34 fix, codeql dispatch, templates, docs notes)
  CHECK: cd "$(git rev-parse --show-toplevel)" && git log --oneline -20 | grep -qiE "phase a" && grep -q "'4.5.x'" .github/workflows/ci.yml && grep -q "github.ref_name" .github/workflows/releaser-pleaser.yml && grep -c "workflow_dispatch" .github/workflows/codeql.yml | grep -qv "^0$" && echo "phase-a-landed"
  EXPECT: phase-a-landed
  EVIDENCE: exit=0; EXPECT=matched; gate-sig=be061153cbd0477a; output-sha256=e8873cb6d93b388a9dcdeeb8949c62ab146b4d8f54112d649cbc8f3cce612594

- [x] G3: pre-rename conditions hold (no open release PRs, no queued releaser-pleaser runs, freeze recorded)
  CHECK: cd "$(git rev-parse --show-toplevel)" && test -z "$(gh pr list --state open --json number --jq '.[] | select(.headRefName | startswith("releaser-pleaser--branches--")) | .number')" && test -z "$(gh run list -w releaser-pleaser.yml --status queued --json databaseId --jq '.[].databaseId')" && test -z "$(gh run list -w releaser-pleaser.yml --status in_progress --json databaseId --jq '.[].databaseId')" && echo "pre-rename-clean"
  EXPECT: pre-rename-clean
  EVIDENCE: exit=0; EXPECT=matched; gate-sig=1cd00287118631d3; output-sha256=a2960cafa698efe72c7cee5b6ffe0aab9ea5b28140cd9e0bc27c8bb446935c83

- [x] G4: rename executed, default is 4.5.x, ruleset active incl. temporary 4.4.x deny
  CHECK: cd "$(git rev-parse --show-toplevel)" && test "$(gh api repos/satwareAG/doctrine-firebird-driver --jq .default_branch)" = "4.5.x" && gh api repos/satwareAG/doctrine-firebird-driver/rulesets --jq '.[] | select(.name == "Release Branch Protection") | .enforcement' | grep -qx active && echo "rename-verified"
  EXPECT: rename-verified
  EVIDENCE: exit=0; EXPECT=matched; gate-sig=d0902142e428c3ee; output-sha256=2277724911785e70e5acf8f5b1c837522b161ce1bc26319445b399c5b7718c11

- [x] G5: dispatched CI run green on renamed default (no push event fires on rename)
  CHECK: cd "$(git rev-parse --show-toplevel)" && gh run list -w ci.yml --branch 4.5.x --limit 3 --json conclusion,event,headBranch --jq '[.[] | select(.event == "workflow_dispatch")] | first | .conclusion' | grep -qx success && echo "dispatch-green"
  EXPECT: dispatch-green
  EVIDENCE: exit=0; EXPECT=matched; gate-sig=ab830b9202847de0; output-sha256=d400860e8c419b2271b9c65a8c4a459d6b9ccfbb57bbef2b9f46b9eaf33aa136

- [x] G6: Phase C cleanup PR merged; zero non-frozen 4.4.x references (grep gate)
  CHECK: cd "$(git rev-parse --show-toplevel)" && test -z "$(grep -rl '4\.4\.x' --include='*.yml' --include='*.md' --include='*.json' --include='*.txt' . 2>/dev/null | grep -vE 'vendor/|archive/|CHANGELOG|\.git/|docs/learnings/|docs/research/|docs/eod/|docs/plans/|specs/00[1-4]-|NEXT_STEPS.md|specs/005-branch-alignment-dbal45/|docs/BRANCHING.md|specs/metadata.json')" && echo "phase-c-clean"
  EXPECT: phase-c-clean
  EVIDENCE: exit=0; EXPECT=matched; gate-sig=29b1ff239eea0a6a; output-sha256=e4656818099209c7b15ef75a9bcc8c226080260774054de6cb0ea2bcafa31702
  NOTE: exclusions are frozen/historical records (dated docs, specs 001-004, changelogs, NEXT_STEPS deprecated file) plus two designated provenance locations: docs/BRANCHING.md (the branch-name policy note records the rename once, canonically) and specs/metadata.json (the dbal relationship describes upstream's retired 4.4.x branch as upstream fact). Gate refinement during execution, council change-set item 1 context, recorded here per unlazy ABANDON/NOTE discipline.

- [x] G7: handover + soak bookkeeping posted (cutover SHA, release-continuity evidence, #167 note)
  CHECK: cd "$(git rev-parse --show-toplevel)" && gh issue view 258 --json comments --jq '[.comments[].body] | join("\n")' | grep -qi "cutover sha" && gh issue view 167 --json comments --jq '[.comments[].body] | join("\n")' | grep -q "4\.5\.x" && echo "handover-recorded"
  EXPECT: handover-recorded
  EVIDENCE: exit=0; EXPECT=matched; gate-sig=a8fa0f4eb239b2ed; output-sha256=eecdfecde274fce8ff18eb5704c523a9b314145810aa0cd5bbad1123080ee10a

- [x] G8: local clone migrated (branch renamed, tracking origin/4.5.x)
  CHECK: cd "$(git rev-parse --show-toplevel)" && test "$(git branch --show-current)" = "4.5.x" && test "$(git rev-parse --abbrev-ref '@{upstream}')" = "origin/4.5.x" && echo "local-clone-migrated"
  EXPECT: local-clone-migrated
  EVIDENCE: exit=0; EXPECT=matched; gate-sig=9f9ce7f8104982e2; output-sha256=d55a2f9a96944f092e88bf6f70664df6bb437503a7fd883af119262f4c2e2a4f

- [ ] G9: full release-publish path proven on 4.5.x (MANUAL, deferred - no CHECK oracle; proven only when the next natural release ships via releaser-pleaser; manufacturing a release to satisfy the gate is forbidden per council R3 S2/S4; if no release ships before 2026-12-31 re-evaluate with the operator)
  EVIDENCE: pending
