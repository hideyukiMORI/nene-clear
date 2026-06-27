---
description: Start NeNe Clear backend (PHP 8384, SQLite) and frontend (Vite 5383) for local development
---

# Run NeNe Clear locally

ローカル起動の手順は `run-local.sh` が「単一の真実」として所有する — このスキルはそれを実行するだけ。
（グローバル個人スキル `/dev-up` も、この repo では同じ `run-local.sh` を検出して委譲する。）

```bash
bash .claude/skills/run-local/run-local.sh
```

## なぜ専用スクリプトなのか

`docker compose up` は **本番相当の `app` コンテナ**（`APP_ENV=production` + MySQL + イメージビルド。
お試し用ワンコマンド構成 #180）を立ち上げるもので、**ローカル開発フローとは別物**。
開発の backend は **`php -S` + SQLite**（`.env` の `DB_ADAPTER=sqlite`）で、汎用検出では起動できない。
そのため起動手順をこのスクリプトに閉じ込めている。

## `run-local.sh` がやること（すべて冪等）

1. **backend** — `composer migrations:migrate`（SQLite スキーマ最新化）後、
   `php -S localhost:8384 -t public_html/` を切り離して起動。`vendor/` が無ければ
   先に `composer install`。`/health` が既に応答していれば触らない。
2. **frontend** — `cd frontend && npm run dev`（Vite `5383`、`/admin`・`/health` を `8384` へ proxy）。
   `node_modules` が無ければ先に `npm install`。既に `5383` が応答していれば触らない。
3. **mail（任意）** — Docker が動いていれば `docker compose up -d mailpit` で
   督促メール確認用の Mailpit のみ起動（SMTP `1383` / Web UI `8383`）。
   Docker が止まっていても front/back は SQLite で動くのでスキップ扱い。
4. backend / frontend / mail の状態サマリを出す。backend か frontend が失敗で非ゼロ終了。

## ポート（CLAUDE.md のポート表に固定）

| Service | URL / Port |
| --- | --- |
| PHP backend | `http://localhost:8384` |
| Vite dev server | `http://localhost:5383` |
| Mailpit Web UI | `http://localhost:8383` |

## 停止

```bash
kill $(cat /tmp/nene-clear-backend.pid)    # backend
kill $(cat /tmp/nene-clear-frontend.pid)   # frontend（pkill -f vite は使わない: sibling を巻き添えにする）
docker compose stop mailpit                # mail
```
