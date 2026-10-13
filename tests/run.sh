#!/usr/bin/env bash
# Runs the checks:  tests/run.sh   (unit, then HTTP against a temporary server, then an update end to end)
set -u
cd "$(dirname "$0")/.."
status=0
for t in unit http update; do
  out=$(php "tests/$t.php" 2>&1)
  ok=$(printf '%s\n' "$out" | grep -c '^ok')
  if printf '%s\n' "$out" | grep -q 'ALL PASSED'; then
    printf '  pass  %-6s %s checks\n' "$t" "$ok"
  else
    printf '  FAIL  %s\n' "$t"; printf '%s\n' "$out" | grep -v '^ok' | sed 's/^/        /' | head -20; status=1
  fi
done
exit $status
