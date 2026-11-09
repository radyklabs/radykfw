
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

Release workflow (`>release`) is one command with an automated post-merge tag; never tag a commit on `dev`:
1. Require a clean, current `dev` branch; verify release readiness, tests/CI, version history, and that `vX.Y.Z` does not exist. Move `[Unreleased]` entries to `## [X.Y.Z] - YYYY-MM-DD`, add a fresh empty `[Unreleased]` section above it, and update the plugin `Version:` header.
2. Commit as `chore(release): X.Y.Z`, push `dev`, and create a PR from `dev` to `main` titled exactly `chore(release): X.Y.Z`. The single `>release` invocation ends after creating the PR; do not wait for a merge or create a tag locally.
3. The `.github/workflows/release-tag.yml` workflow runs when that release PR is merged. It verifies the PR source/title, release version and dated changelog section at the exact merge commit, confirms the commit is on `main`, and creates/pushes the annotated `vX.Y.Z` tag there. Any failed validation must fail without creating or replacing a tag; fix/re-run the workflow rather than rerunning `>release`.
4. The release-tag workflow must already be present on `main` before opening a release PR. Ensure GitHub Actions has `contents: write` permission and tag rules permit its `GITHUB_TOKEN` to create `v*` tags. Optionally publish a GitHub Release from the verified tag.

## Commit & Push Rule

- Commit completed changes immediately; never leave changes uncommitted or commits unpushed.
- Push to the current branch on `origin`.
- Before merging to `dev`, update the branch against `dev` and create a PR.
- Merge using a squash or merge commit, then delete the merged branch.

## Custom Commands

| Command | Action |
|---------|--------|
| `>branch <description>` | Choose a concise prefixed kebab-case branch name, verify it is unused, then create and switch to it. |
| `>release` | One invocation prepares changelog/version on `dev` and opens a `chore(release): X.Y.Z` PR to `main`; after merge, `release-tag.yml` validates the exact merge commit and automatically creates/pushes its `vX.Y.Z` tag. |
| `>push` | Inspect status and diff, add a per-file summary under `[Unreleased]` in the changelog, then stage, commit, and push the current branch. |
| `>sync` | Run `>push`, update against `dev`, create and merge a PR into `dev`, then delete the merged branch. |
| `>track` | Compare the plan against the code, update statuses and related docs, then commit and push. |