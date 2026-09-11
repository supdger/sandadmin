#!/usr/bin/env bash
# behavior-test-gate: static-rule
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
violations=()

for forbidden_path in \
  plugins/sand-ai \
  plugins/sand-iam \
  plugins/sandworkflow \
  server/plugin/sand-ai \
  server/plugin/sand-iam \
  server/plugin/sandworkflow \
  sandadmin-artd/src/views/plugin/sand-ai \
  sandadmin-artd/src/views/plugin/sand-iam \
  sandadmin-artd/src/views/plugin/sandworkflow \
  server/database/sand-ai.pgsql \
  server/database/sand_ai_schema \
  server/tests/SandAi \
  server/sand-ai-acceptance.env.example
do
  [[ ! -e "$repo_root/$forbidden_path" ]] || violations+=("$forbidden_path")
done

if find "$repo_root/server/app" "$repo_root/server/config" \
    -type f -not -path '*/vendor/*' -print0 2>/dev/null \
    | xargs -0 grep -IlE 'SandAi|sand_ai|sand-ai|SandIam|sand_iam|sand-iam|sandworkflow|sand_workflow|sand-workflow' \
    | grep -q .; then
  violations+=("business-plugin references under server/app or server/config")
fi

if ((${#violations[@]})); then
  printf 'SandAdmin clean-host check failed:\n' >&2
  printf ' - %s\n' "${violations[@]}" >&2
  exit 1
fi

echo 'SandAdmin clean-host check passed: zero business-plugin source/runtime copies found.'
