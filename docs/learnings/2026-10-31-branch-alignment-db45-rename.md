# 2026-10-31: Branch rename playbook (4.4.x -> 4.5.x, spec 005)

Renaming a protected default branch that drives release automation. Full
record: `specs/005-branch-alignment-dbal45/` (spec, council ledger, gates).
This file is the portable playbook for the NEXT rename (the policy note in
`docs/BRANCHING.md` bounds renames to upstream retirements of the mirrored
line).

## What actually breaks in a default-branch rename (evidence-ordered)

1. **Trigger filters live in the pushed ref** (`on.push.branches` etc.).
   Chicken-and-egg: the rename makes the old filters stop matching, and the
   update that fixes them needs CI. Fix: **Phase A additive window** - merge
   a PR accepting BOTH names before renaming (push-triggered workflows read
   the workflow file from the pushed ref, so the renamed branch is
   CI-functional from the first push).
2. **Action inputs hard-code the branch name** - the draft spec missed
   `releaser-pleaser.yml` line 34 (`branch: 4.4.x`) entirely; two council
   seats found it independently. Audit class: grep EVERY `branch:`/`ref:`/
   head input in workflows, not just trigger filters. Replacement:
   `github.ref_name` (correct for push AND pull_request_target - on PR
   events ref_name is the base ref; cherry-pick-safe for other maintenance
   lines).
3. **The rename API emits a push-labeled event with a STALE payload**: our
   observed rename fired releaser-pleaser with `ref_name=4.5.x` but
   `repository.default_branch=4.4.x` (payload snapshot) - the new guard
   correctly skipped it. A naive `ref_name == '4.5.x'` guard would have RUN
   against a half-renamed state. Guard on `github.ref_name ==
   github.event.repository.default_branch`, not a literal.
4. **No usable CI run exists immediately after the rename** (the skipped
   event proves nothing): verify with explicit
   `gh workflow run ci.yml --ref <new>` (requires `workflow_dispatch:` in
   the workflow - add it in Phase A if missing).
5. **Actions caches are ref-scoped**: the renamed default starts cache-cold
   (slower CI, not blocking). Extension build caches re-warm on the first
   matrix run.
6. **Historical run `headBranch` values do not follow the rename**:
   branch-filtered evidence queries split at the cutover. Our soak log
   (#167) survived because it is SHA + run-id keyed. Query with
   `--event=schedule`, never `--branch <name>`, across a rename boundary.
7. **Rulesets on `~DEFAULT_BRANCH` follow the rename automatically**; the
   old branch name loses protection the moment it stops being default. A
   stale clone can recreate it via `git push` (git does NOT redirect
   pushes). Council disposition: temporary include rule on
   `refs/heads/<old>` in the EXISTING ruleset (fnmatch matches non-existent
   refs), removed after cleanup.
8. **Rollback is one API call only before the cleanup phase**: post-cleanup,
   the old name carries new-only workflow filters; restore dual-name
   automation via a green PR FIRST, then rename back.

## Sequence that worked

Phase A (PR on old name, additive) -> merge green -> freeze + zero old-name
release PRs + zero queued release-automation runs -> API rename
(`gh api -X POST repos/{o}/{r}/branches/<old>/rename -f new_name=<new>`) ->
temporary ruleset deny on old name -> dispatch verification runs -> Phase C
(PR on new name, subtractive) -> remove deny -> consumer handover with
cutover SHA.

## Non-goals honored

No dev branches created to mirror upstream (YAGNI); composer constraint
unchanged (rename is organizational, not a release); frozen historical docs
(specs 001-004, learnings, changelogs) left with old names deliberately.

## Cost note

Council (4 paid seats x 3 rounds) spent its effort almost entirely on
mechanics the draft spec missed - the premise ("follow upstream layout")
was cheap; the execution surface was not. The next rename reuses this file
plus the GATES.md oracle pattern and should cost one focused session, not a
council.
