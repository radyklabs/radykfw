
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

Release workflow (`>release`) is deliberately two-stage; never tag a commit on `dev`:
1. **Prepare on `dev`:** require a clean worktree and a current `dev` branch; verify release readiness, tests/CI, version history, and that `vX.Y.Z` does not already exist. Move `[Unreleased]` entries to `## [X.Y.Z] - YYYY-MM-DD`, add a fresh empty `[Unreleased]` section above it, and update the plugin `Version:` header.
2. Commit the release preparation as `chore(release): X.Y.Z`, push `dev`, and create a pull request from `dev` to `main`. Do not tag or publish yet. Stop and wait for the PR to pass required review/checks and be merged.
3. **Finalize only after merge:** on a subsequent `>release` invocation, verify that the release PR is merged, `main` contains the expected version/changelog, and `vX.Y.Z` is still unused. Tag the exact merge commit on `main` (`git tag vX.Y.Z <merge-commit>`), then push the tag. Never tag `dev` or move/overwrite an existing release tag.
4. Optionally create a GitHub Release from the verified tag.

## Commit & Push Rule

- Commit completed changes immediately; never leave changes uncommitted or commits unpushed.
- Push to the current branch on `origin`.
- Before merging to `dev`, update the branch against `dev` and create a PR.
- Merge using a squash or merge commit, then delete the merged branch.

## Custom Commands

| Command | Action |
|---------|--------|
| `>branch <description>` | Choose a concise prefixed kebab-case branch name, verify it is unused, then create and switch to it. |
| `>release` | Two-stage release: prepare changelog/version on `dev`, push and open a PR to `main`, then stop; only after that PR is merged, a later invocation verifies the merge and tags/pushes the `main` merge commit. |
| `>push` | Inspect status and diff, add a per-file summary under `[Unreleased]` in the changelog, then stage, commit, and push the current branch. |
| `>sync` | Run `>push`, update against `dev`, create and merge a PR into `dev`, then delete the merged branch. |
| `>track` | Compare the plan against the code, update statuses and related docs, then commit and push. |