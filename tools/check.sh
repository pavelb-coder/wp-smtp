#!/usr/bin/env bash
# Локальная проверка перед отправкой в репозиторий.
#   bash tools/check.sh
set -euo pipefail
cd "$(dirname "$0")/.."

echo "== версия x.y.z =="
python3 tools/version.py check

echo "== синтаксис PHP =="
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l

echo "== smoke-тест настроек =="
php tests/test-options.php

echo "== git =="
git status --short

echo "ОК. Далее: /home/user/sync-wp-smtp.sh \"сообщение коммита\""
