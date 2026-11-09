
## Project Rules
Always read `docs/RULES.md` before working on this repository and follow its conventions.
Read `docs/CONFIG.md` for local PHP paths and WordPress test credentials. Use `docs/CONFIG.sample.md` as the template for other platforms; never commit `docs/CONFIG.md`.

**Files tracked for sync:** `docs/PLAN.md`, `docs/PRD.md`, `docs/REPORT.md`, `docs/CHANGELOG.md`.

## Decision Framework

For logic or major changes:
1. Verify the current code state by reading the actual files.
2. Cross-reference the four project documents.
3. If a decision is needed, ask the user to choose between clear options before proceeding.
4. Record the decision in PLAN, REPORT, and CHANGELOG.
5. Do not execute code or implement the change until the user confirms.

## Versioning & Release Process

Use Semantic Versioning (MAJOR.MINOR.PATCH):
- **MAJOR** — breaking changes (API, config structure, DB schema)
- **MINOR** — new backward-compatible features
- **PATCH** — bug fixes, security patches, documentation-only changes

Keep changes under `[Unreleased]` during normal development. Promote it only when the release scope is agreed and frozen, changes are complete and validated, and the release version/date are known. Determine the version from all changes since the latest release, verifying impact against the actual code, docs, tests, and release history.

When a planned phase is complete and merged into `dev`, review release readiness and report the recommended version and blockers. Do not promote `[Unreleased]`, update the plugin version, tag, or publish unless the release is ready and the user invokes `>release`.

Release workflow (`>release`) prepares, merges, and tags in one invocation; never tag a commit on `dev`:
1. Require a clean, current `dev` branch; verify release readiness, tests/CI, version history, and that `vX.Y.Z` does not exist. Review the complete `dev` → `main` diff and stop if it includes unrelated or unintended changes. Move `[Unreleased]` entries to `## [X.Y.Z] - YYYY-MM-DD`, add a fresh empty `[Unreleased]` section above it, and update the plugin `Version:` header.
2. Commit as `chore(release): X.Y.Z`, push `dev`, and create a PR from `dev` to `main` titled exactly `chore(release): X.Y.Z`.
3. If the PR is mergeable and GitHub rules allow it, merge it with `gh pr merge --merge`; do not bypass rules or wait for a separate manual merge. If a required approval, check, or other rule blocks the merge, stop and report the blocker.
4. Wait for `.github/workflows/release-tag.yml` to validate the merged PR and create/push the annotated `vX.Y.Z` tag. Verify the tag points to the exact merge commit before reporting success. The workflow must already be present on `main`, and GitHub Actions must have `contents: write` permission with tag rules permitting its `GITHUB_TOKEN` to create `v*` tags. Optionally publish a GitHub Release from the verified tag.
5. If tagging fails, do not repeat the release or create a local tag. Fix the cause and use the workflow's manual recovery input with the verified release merge SHA; it independently verifies the merged release PR and metadata before tagging.

## Commit & Push Rule

- Commit completed changes immediately; never leave changes uncommitted or commits unpushed.
- Push to the current branch on `origin`.
- Before merging to `dev`, update the branch against `dev` and create a PR.
- Merge using a squash or merge commit, then delete the merged branch.

## Custom Commands

| Command | Action |
|---------|--------|
| `>branch <description>` | Choose a concise prefixed kebab-case branch name, verify it is unused, then create and switch to it. |
| `>release` | One invocation prepares changelog/version, opens and merges a `chore(release): X.Y.Z` PR to `main` when rules permit, then waits for `release-tag.yml` to validate and create/push the tag. |
| `>push` | Inspect status and diff, add a per-file summary under `[Unreleased]` in the changelog, then stage, commit, and push the current branch. |
| `>sync` | Run `>push`, update against `dev`, create and merge a PR into `dev`, then delete the merged branch. |
| `>track` | Compare the plan against the code, update statuses and related docs, then commit and push. |