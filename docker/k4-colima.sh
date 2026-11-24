#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
compose=(docker --context colima compose --project-name k4-parser -f compose.yaml -f compose.colima.yaml)

case ${1:-} in
    start) exec "${compose[@]}" up -d --no-build --pull never ;;
    stop) exec "${compose[@]}" stop ;;
    status) exec "${compose[@]}" ps --all ;;
    *)
        printf 'Usage: bash %s {start|stop|status}\n' "$0" >&2
        exit 2
        ;;
esac
