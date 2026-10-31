run: dfd-branch-005 | date: 2026-10-31 | topic: branch layout alignment with upstream doctrine/dbal (spec 005 evaluation)
seats: 4 | labs: 4 (anthropic, openai, google, deepseek via openrouter) | rounds: 3 (DEBATE)
S1 | openrouter/~anthropic/claude-opus-latest | anthropic | est $0.20
S2 | openrouter/~openai/gpt-astra-latest | openai | est $0.15
S3 | openrouter/~google/gemini-pro-latest | google | est $0.15
S4 | openrouter/~deepseek/deepseek-pro-latest | deepseek | est $0.08
cap: $5.00 (operator, in-session 2026-10-31: "Use a paid openrouter foundation model council for best results Gapped at 5$")
GO: ceiling=$3.00 seats=4 round=0 operator=operator-in-session ts=2026-10-31T06:20Z (instruction: "Use a paid openrouter foundation model council for best results Gapped at 5$")
probes: S1 ALIVE, S2 ALIVE, S3 ALIVE, S4 ALIVE (4 tiny spawns, 2026-10-31T06:20Z)


briefs (snapshots, referenced as snap=S<n>):
S1 snap=s1 | Role: release engineering (branch renames, releaser-pleaser, tags/protected refs) | Stance: three-phase additive-window rename is correct; sequencing is the whole game | Push on: any step that leaves default unprotected/unbuildable; guard-expression gaps | Decision: (filled R1) | Must-reject: rename without pre-merged additive filters
S2 snap=s2 | Role: downstream consumer advocate (composer semantics, private-consumer CI coupling) | Stance: consumer is tag-coupled so rename is low-risk, but default-branch continuity is the only thing that matters downstream | Push on: assumptions about consumer tooling referencing branch names; handover completeness | Decision: (filled R1) | Must-reject: any plan with a window where releases could not be cut from default
S3 snap=s3 | Role: CI/scheduled-workload continuity (Actions triggers, soak evidence chain #167, dependabot) | Stance: scheduled and push/PR trigger semantics differ; the workflow-file-evaluation order is where renames die | Push on: trigger-filter gaps, cron continuity, cache-key/rule drift during the window | Decision: (filled R1) | Must-reject: single-phase rename with same-commit filter edits
S4 snap=s4 | Role: adversarial premise auditor | Stance: renaming to mirror upstream may be cargo-cult - branch names are local policy, upstream layout is not a contract | Push on: the premise itself; cost/benefit vs do-nothing; 3.18.x inconsistency; scope creep (dev branches) | Decision: (filled R1) | Must-reject: creating 4.6.x/5.0.x dev branches now

rounds: R1 sealed independent | R2 claim-targeted | R3 dissent test + convergence

R1 (sealed, indep=yes, 4/4 cast):
S1 | claude-opus | LIVE | APPROVE-WITH-CHANGES | releaser-pleaser.yml:34 hardcoded branch: 4.4.x input (VERIFIED line 34); git push does NOT redirect old name -> stale clone can recreate unprotected 4.4.x; precondition zero open release PRs; rollback valid only pre-Phase-C; no composer branch-alias (dev-4.4.x -> dev-4.5.x only)
S2 | gpt-astra | LIVE | APPROVE-WITH-CHANGES | release-continuity gate before Phase C (old-prefix release PRs must complete or be replaced); green CI != release readiness; handover must record cutover commit + release-continuity evidence
S3 | gemini-pro | LIVE | APPROVE-WITH-CHANGES | independently confirms line 34; rename API emits NO push event -> Phase B CI-green gate needs explicit workflow_dispatch; Actions cache ref-scoped (cold start on renamed default); run-history headBranch splits soak/CodeQL audit chains
S4 | deepseek-pro | LIVE | REJECT | premise attack: rename is cosmetic with treadmill (4.6 ships -> rename again); rollback overstated post-Phase-C; 3.18.x precedent cuts both ways; do-nothing + BRANCHING.md note is strictly lower-risk

R3 (dissent test, all HOLD): S1 pending-run window REAL (queued runs pre-rename fire post-rename with old-file snapshot; freeze + zero-queued-runs check + post-rename recheck); S2 gate rewording accepted (docs-PR proves trigger+guard-execution+targeting; release-PR creation only if releasable commits exist; no-release success recorded as such, never claimed as full-path proof); S3 concedes to S4 on #167 (SHA+numeric-run-id evidence rename-proof; only --branch-filtered queries split; query with --event=schedule); S4 ruleset proportionate (zombie release-line vector from recreated branch re-running old workflow) but fold INTO existing "Release Branch Protection" as temporary deny on refs/heads/4.4.x (removed Phase C), block creation/updates, allow PRs targeting.

SYNTHESIS (computed from ledger):
- Final: APPROVE-WITH-CHANGES 4/4 (cast=4, live=4). UNANIMOUS post-dissent-test; S4 R1 REJECT -> R2 flip under settled operator mandate (dissent-resolved, herd-suspect cleared by R2 dispute exchange).
- Earned-cost: verdict label unchanged from R1 majority (3:1 AWC) - debate changed the EXECUTION substance, not the label: line-34 action-input gap (S1,S3,S4), no-push-event + dispatch verification (S3,S1,S2,S4), ref-scoped caches (S3), headBranch-evidence split + SHA-keyed continuity (S3,S4), composite Phase-C gate + reworded proof surface (S2,S1,S4), pending-run window (S1,S2), ruleset fold-in (S4,S1,S3), rollback honesty (all four), BRANCHING.md policy rule (S4,S1).
- Conclusions (all multi-seat supported; none single-source):
  1. Execute three-phase rename per amended spec (seats: S1,S2,S3,S4)
  2. Phase A must fix line-34 action input + add workflow_dispatch to codeql.yml + audit all hardcoded branch inputs (seats: S1,S3,S4)
  3. Phase B entry: freeze + zero old-prefix PRs + zero queued releaser-pleaser runs + temporary deny rule in existing ruleset (seats: S1,S2,S3,S4)
  4. Phase B verification: explicit workflow_dispatch on renamed default (rename emits no push) (seats: S1,S2,S3)
  5. Phase C gate: trigger+guard-execution+targeting proof via docs-PR; full publish proof deferred to natural release (seats: S2,S1,S4)
  6. #167 soak continuity: log cutover SHA; use --event=schedule queries (seats: S3,S4)
  7. BRANCHING.md: local policy; rename only on upstream retirement of mirrored line (seats: S1,S4,S3)


