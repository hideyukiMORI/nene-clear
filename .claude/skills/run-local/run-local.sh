#!/usr/bin/env bash
# run-local.sh — NeNe Clear のローカル開発スタックを冪等に起動する「単一の真実」。
#
# ローカル DEV 構成（README quickstart / .env 準拠）:
#   backend  = php -S localhost:8384 -t public_html/   (SQLite: DB_ADAPTER=sqlite)
#   frontend = npm run dev                              (Vite 5383, /admin・/health を 8384 へ proxy)
#   mail     = docker compose up -d mailpit             (任意: 督促メール確認。SMTP 127.0.0.1:1383)
#
# なぜ repo 固有 hook が必要か:
#   グローバル個人スキル /dev-up の汎用検出は compose を見つけると `docker compose up -d` を叩く。
#   だが本 repo の docker-compose.yml は APP_ENV=production の app コンテナ
#   （イメージビルド + MySQL。#180 の「お試し用ワンコマンド」）を立ち上げるもので、
#   ローカル開発フロー（php -S + SQLite、ホットに叩ける軽量バックエンド）とは別物。
#   dev の backend は php -S であり、汎用スキルでは起動できない。よって起動手順を本スクリプトに
#   閉じ込め、/dev-up からは委譲させる（~/.claude/skills/dev-up が本ファイルを検出して丸投げ）。
#
# ポートは CLAUDE.md のポート表に固定（backend 8384 / frontend 5383 / Mailpit SMTP 1383・UI 8383）。
# 既定値（8080/1025/8025/3306）には戻さない。
set -uo pipefail

# --- repo ルート（このスクリプトは .claude/skills/run-local/ に置かれている） ---
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE" && git rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$ROOT" ] || ROOT="$(cd "$HERE/../../.." && pwd)"
cd "$ROOT" || { printf '❌ repo ルートに移動できません: %s\n' "$ROOT" >&2; exit 1; }

say() { printf '%s\n' "$*"; }
# curl prints `000` itself when it can't connect, so do NOT add `|| echo 000`
# (that would emit `000000` and make is_up treat a dead port as up).
http_code() { local c; c="$(curl -s -o /dev/null -w '%{http_code}' --max-time 4 "$1" 2>/dev/null)"; printf '%s' "${c:-000}"; }
is_up() { [ "$(http_code "$1")" != "000" ]; }

# --- .env（無ければ example から作る。dev は .env の DB_ADAPTER=sqlite を前提とする） ---
if [ ! -f "$ROOT/.env" ]; then
  say "ℹ️ .env が無いため .env.example から作成します。"
  cp "$ROOT/.env.example" "$ROOT/.env"
fi
get_env() { grep -E "^$1=" "$ROOT/.env" 2>/dev/null | tail -1 | cut -d= -f2- | tr -d "\"' "; }
BE_PORT="$(get_env NENE_CLEAR_PORT)";          BE_PORT="${BE_PORT:-8384}"
FE_PORT="$(get_env NENE_CLEAR_FRONTEND_PORT)";  FE_PORT="${FE_PORT:-5383}"

BE_URL="http://localhost:${BE_PORT}"
FE_URL="http://localhost:${FE_PORT}/"
BE_LOG="/tmp/nene-clear-backend.log";  BE_PID="/tmp/nene-clear-backend.pid"
FE_LOG="/tmp/nene-clear-frontend.log"; FE_PID="/tmp/nene-clear-frontend.pid"

say "▶ NeNe Clear ローカル起動 (php -S + SQLite + Vite)  repo: ${ROOT}"
say ""

backend="failed"; frontend="skip"; mail="skip"

# ============================================================================
# 1. backend: php -S + SQLite （docker compose の production app コンテナは使わない）
# ============================================================================
if is_up "${BE_URL}/health"; then
  say "✅ backend: 既に起動済み (${BE_URL}/health)"
  backend="ok"
else
  if [ ! -d "$ROOT/vendor" ]; then
    say "📦 composer install …"
    composer install --no-interaction 2>&1 | tail -3
  fi
  # SQLite スキーマを最新化（phinx は冪等。未適用が無ければ no-op）。
  # 流さないと live クエリが 500 になり得る。
  say "🗄  composer migrations:migrate (SQLite) …"
  composer migrations:migrate 2>&1 | tail -3
  [ "${PIPESTATUS[0]}" -eq 0 ] || say "⚠️ migration が非ゼロ終了。queries が 500 になる場合は上のログを確認。"

  say "🚀 backend 起動: php -S localhost:${BE_PORT} -t public_html/  log: ${BE_LOG}"
  : > "$BE_LOG"
  # setsid でセッションから切り離し、スクリプト終了後も生存させる
  setsid bash -c "cd '$ROOT' && exec php -S localhost:${BE_PORT} -t public_html/" >"$BE_LOG" 2>&1 < /dev/null &
  echo "$!" > "$BE_PID"
  for _ in $(seq 1 20); do is_up "${BE_URL}/health" && { backend="ok"; break; }; sleep 1; done
  if [ "$backend" = "ok" ]; then
    say "✅ backend: 起動完了 (${BE_URL}/health)"
  else
    say "❌ backend: 起動失敗。log 末尾:"; tail -20 "$BE_LOG" 2>/dev/null
  fi
fi
say ""

# ============================================================================
# 2. frontend: vite (npm run dev) — 冪等。既に応答していれば触らない。
# ============================================================================
if is_up "$FE_URL"; then
  say "✅ frontend: 既に起動済み (${FE_URL})"
  frontend="already"
elif [ -f "$ROOT/frontend/package.json" ]; then
  [ -d "$ROOT/frontend/node_modules" ] || { say "📦 npm install (frontend) …"; ( cd "$ROOT/frontend" && npm install ) 2>&1 | tail -3; }
  say "🚀 frontend 起動: (cd frontend && npm run dev)  log: ${FE_LOG}"
  : > "$FE_LOG"
  setsid bash -c "cd '$ROOT/frontend' && exec npm run dev" >"$FE_LOG" 2>&1 < /dev/null &
  echo "$!" > "$FE_PID"
  for _ in $(seq 1 25); do is_up "$FE_URL" && { frontend="started"; break; }; sleep 1; done
  if [ "$frontend" = "started" ]; then
    say "✅ frontend: 起動完了 (${FE_URL})"
  else
    frontend="failed"; say "❌ frontend: 起動失敗。log 末尾:"; tail -20 "$FE_LOG" 2>/dev/null
  fi
else
  say "ℹ️ frontend/package.json なし → フロントはスキップ"
fi
say ""

# ============================================================================
# 3. mail（任意）: 督促メール確認用 Mailpit のみ起動（production app コンテナは起動しない）。
#    .env が SMTP=127.0.0.1:1383 を指すため Docker が動いていれば上げる。
#    Docker が止まっていても front/back は SQLite で動くので失敗扱いにしない。
# ============================================================================
if docker info >/dev/null 2>&1; then
  if docker compose up -d mailpit >/dev/null 2>&1; then
    mail="ok"; say "✅ mail: Mailpit 起動 (SMTP :1383 / Web UI http://localhost:8383)"
  else
    mail="failed"; say "⚠️ mail: Mailpit 起動に失敗（督促メール確認のみ影響。front/back には無影響）"
  fi
else
  mail="skip"; say "ℹ️ mail: Docker 未起動 → Mailpit はスキップ（督促メール送信は無効。front/back は動作）"
fi

# ============================================================================
# サマリ
# ============================================================================
say ""
say "──────────── run-local サマリ ────────────"
say "  backend  : [${backend}]  ${BE_URL}  (SQLite)"
say "  frontend : [${frontend}]  ${FE_URL}"
say "  mail     : [${mail}]  Mailpit http://localhost:8383"
say "──────────────────────────────────────────"
say "停止: backend = kill \$(cat ${BE_PID})  /  frontend = kill \$(cat ${FE_PID})  /  mail = docker compose stop mailpit"
say "（フロント停止に pkill -f vite は使わない: ホスト上の sibling プロジェクトの Vite を巻き添えにするため）"

# mail は任意なので終了コードに含めない。
[ "$backend" = "failed" ] && exit 1
[ "$frontend" = "failed" ] && exit 1
exit 0
