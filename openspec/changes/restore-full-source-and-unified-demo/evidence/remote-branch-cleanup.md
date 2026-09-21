# Remote branch cleanup evidence

Verified against `github/main` after a fresh fetch on 2026-09-22:

- `github/codex/plugin-catalog-preview` appeared in `git branch -r --merged github/main`.
- `git log github/main..github/codex/plugin-catalog-preview` returned no commits.
- `git cherry github/main github/codex/sandadmin-rename` returned
  `- e7cdd1112d349338c5d46c75ecb29ddf9f1e2b92`, proving the patch was already
  represented in `main`.
- The two absorbed branches were deleted from `github`.
- A subsequent fetch with pruning listed only `github/main` (plus its `HEAD`
  symbolic reference).
