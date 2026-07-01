# 請求書の次に必要になる「入金消込・督促」を自分で持つ — NeNe Clear を Docker で試す

## はじめに

請求書を発行できるようになると、次に必ず出てくるのが「入金後の運用」です。

たとえば、こんな作業です。

- 銀行CSVをダウンロードする
- 入金行を請求書と照合する
- 一部入金、振込手数料差引、過入金を確認する
- 未入金の請求に督促メールを送る
- あとから税理士や担当者に説明できるよう、誰が何を確認したかを残す

この領域は、Excel や手元のメモで回りがちです。小さなチームなら、最初はそれでも十分かもしれません。

ただ、入金消込は「請求書を作る」こととは別の業務です。請求書の金額、銀行の入金行、消込判断、督促履歴が混ざると、あとから何が正なのか分かりにくくなります。

**NeNe Clear** は、NENE2 上で動く self-hosted な **入金消込・督促管理** OSS です。

請求書発行は **NeNe Invoice** が担当し、NeNe Clear はその後ろで、銀行CSVの入金行、消込、督促、監査証跡を扱います。

> **リリース状況（2026年7月時点）** — NeNe Clear は OSS として開発中です。入金消込 API、銀行CSV取込、消込提案/確認、過入金の client credit、督促、監査ログ、React 管理 UI、ja/en UI、OpenAPI 仕様は入っています。一方で、配布形態、実運用向けセットアップ、MFA の管理 UI、MCP write tool などは継続開発中です。この記事は Docker / VPS 想定での試用・評価向けです。MIT ライセンスですが **無保証** です。

> **重要** — NeNe Clear は会計ソフトでも、請求書発行ソフトでも、債権回収代行でもありません。法務・税務判断を自動化するものではありません。運用時は税理士・公認会計士・弁護士など専門家の確認を前提にしてください。

---

## NeNe Clear でできること（この記事の範囲）

| 項目 | 内容 |
| --- | --- |
| 入金消込 | 銀行CSVの入金行を請求書・売掛金と照合する |
| 銀行CSV取込 | import batch / bank transaction として証跡を残す |
| 人間確認 | AIやルールは提案まで。確定はオペレーターが確認する |
| 一部入金 | outstanding を残したまま部分消込を扱う |
| 過入金 | 余剰分を client credit として残す |
| 督促 | 未入金・ overdue の請求に対して、オペレーター管理の督促を送る |
| 監査ログ | import / confirm / reverse / dunning などの操作履歴を残す |
| API | OpenAPI で管理された JSON API |

**しないこと**:

- 見積書・請求書・適格請求書 PDF の発行
- 消費税計算
- 仕訳作成や総勘定元帳の代替
- 貸倒判断
- 消滅時効などの法的判断
- 第三者の債権回収代行
- AI による DB 直アクセス

ここを分けているのが、NeNe Clear の一番大事なところです。

---

## Invoice と Clear を分ける理由

NeNe シリーズでは、請求書まわりを1つの巨大なアプリに詰め込まず、責務を分けています。

| プロダクト | 役割 |
| --- | --- |
| NeNe Invoice | 見積・請求・入金管理、適格請求書、PDF |
| NeNe Clear | 銀行入金の消込、督促、証跡 |

NeNe Clear は NeNe Invoice の上位互換ではありません。

別リポジトリ、別DB、別アプリです。

連携するときも、DBを共有せず、HTTP API 経由でつなぎます。

```text
NeNe Clear
  -> documented HTTP API
  -> NeNe Invoice
```

これは少し面倒に見えます。

でも、業務上はかなり大事です。

請求書の金額や税は Invoice 側が正。

銀行CSV、消込判断、督促履歴は Clear 側が正。

この境界を分けることで、「請求書の帳簿」と「銀行入金の証跡」をあとから突き合わせやすくします。AI や管理 UI が関わっても、境界は同じです。

---

## 1. Docker で周辺サービスを起動する

NeNe Clear は NENE2 を sibling checkout として参照します。

まず `NENE2` と `nene-clear` を同じ階層に clone します。

```bash
git clone https://github.com/hideyukiMORI/NENE2.git
git clone https://github.com/hideyukiMORI/nene-clear.git
cd nene-clear
cp .env.example .env
```

この記事では Docker の MySQL と Mailpit を使います。

`.env` を MySQL 用にします。

```dotenv
DB_ADAPTER=mysql
DB_HOST=127.0.0.1
DB_PORT=3383
DB_NAME=nene_clear
DB_USER=nene_clear
DB_PASSWORD=nene_clear

# 管理API（ログイン等 /admin/*）の有効化に必須。長いランダム文字列を設定する。
#   生成例: php -r 'echo bin2hex(random_bytes(32)), "\n";'
NENE_CLEAR_JWT_SECRET=replace-with-a-long-random-string

SMTP_HOST=127.0.0.1
SMTP_PORT=1383
```

`NENE_CLEAR_JWT_SECRET` は管理 API（`/admin/*`、ログインを含む）を有効化するために必要です。設定しないと `/health` だけが応答し、管理 UI にはログインできません。

MySQL と Mailpit を起動します。

```bash
docker compose up -d mysql mailpit
```

ローカルの主なポート:

| 用途 | URL |
| --- | --- |
| PHP backend | <http://localhost:8384> |
| Vite admin UI | <http://localhost:5383> |
| MySQL | `localhost:3383` |
| Mailpit | <http://localhost:8383> |
| Mailpit SMTP | `localhost:1383` |

<!--
スクリーンショット 1（必須）:
掲載場所: このセクション末尾。
画面: ターミナルで `docker compose up -d mysql mailpit` の後に `docker compose ps` を表示している画面。
目的: Clear は Docker/VPS 想定で、MySQL と Mailpit をローカルで起動できることを見せる。
写す要素: `nene-clear-db` / `nene-clear-mail`、ポート 3383 / 8383 / 1383。
-->

---

## 2. Backend と migration

PHP 依存を入れて、マイグレーションを流します。

```bash
composer install
composer migrations:migrate
```

開発用サーバーを起動します。

```bash
php -S localhost:8384 -t public_html/
```

別ターミナルでヘルスチェックします。

```bash
curl http://localhost:8384/health
```

API 仕様は OpenAPI で管理されており、リポジトリ内の `docs/openapi/openapi.yaml` で確認できます。この記事の時点では、実行時に OpenAPI を配信するエンドポイントはありません。

> 現時点では、公開リポジトリの quickstart は開発者向けです。初期管理ユーザー作成や本番向け installer は今後の配布整備で磨き込む予定です。管理 UI のフル操作を試す場合は、開発用データやシード済み環境を用意してください。

<!--
スクリーンショット 2（必須）:
掲載場所: `curl http://localhost:8384/health` の直後。
画面: backend 起動ターミナル + health check の成功。
目的: API と NENE2 runtime が起動していることを見せる。
写す要素: `php -S localhost:8384 -t public_html/`、`curl /health` の JSON。
-->

---

## 3. Admin UI を起動する

React 管理 UI は `frontend/` にあります。

```bash
npm --prefix frontend install
npm --prefix frontend run dev
```

Vite dev server は次の URL です。

```text
http://localhost:5383
```

管理 UI には、次のような画面があります。

- Dashboard
- 銀行CSV取込
- 銀行取引一覧
- 入金消込
- manual receivables（手入力・CSV取込の売掛参照）
- client credits（過入金）
- 督促
- 設定
- ユーザー管理
- 監査ログ

<!--
スクリーンショット 3（必須）:
掲載場所: このセクション末尾。
画面: NeNe Clear のログイン画面、またはシード済み環境でログイン後の Dashboard。
目的: APIだけでなく、React 管理UIがあることを見せる。
写す要素: 左ナビ、Dashboard KPI、未消込・督促などのカード。
注意: 実データのメールアドレス、取引先名、口座番号は必ずマスクする。
-->

---

## 4. 銀行CSV取込: bank transaction を証跡として残す

NeNe Clear の最初の入口は銀行CSVです。

銀行の入出金明細から、入金行を取り込みます。

内部的には、次のようなデータとして扱います。

| データ | 内容 |
| --- | --- |
| bank import batch | 1回のCSV取込。ファイル名、ハッシュ、行数、取込者など |
| bank transaction | 1行の入金データ。入金日、金額、摘要、状態など |

ポイントは、取り込んだ銀行行を勝手に書き換えないことです。

NeNe Clear は、銀行CSVを「あとから確認できる証跡」として扱います。

間違えて取り込んだ場合も、行を削除してなかったことにするのではなく、取消・reversal として履歴を残す設計です。

これは、入金消込を「便利なマッチング画面」だけで終わらせず、後から説明できる状態にするためです。

<!--
スクリーンショット 4（必須）:
掲載場所: このセクション末尾。
画面: 銀行CSV取込画面。
目的: CSV upload と import history があることを見せる。
写す要素: 口座選択、CSVファイル選択、取込履歴。
注意: 実際の銀行名・口座番号・摘要はマスクする。
-->

---

## 5. 消込: AI は提案まで、人間が確定する

NeNe Clear の設計で大事なのは、**human confirms, AI proposes** です。

つまり、システムや AI ができるのは「この入金はこの請求書では？」という提案まで。

最終的な消込確定は、オペレーターが確認します。

```text
bank transaction
  -> propose match
  -> operator confirms
  -> reconciliation is recorded
  -> payment is written back to Invoice API
  -> audit event is recorded
```

入金消込には、地味だけど危ないケースがあります。

- 請求額ぴったりの入金
- 一部入金
- 複数請求書をまとめた入金
- 振込手数料が引かれた入金
- 請求額より多い入金

これらを「なんとなく自動で処理」すると、後で説明できません。

NeNe Clear では、確定操作、金額配分、取消理由、監査ログを残す方向で作っています。便利さより先に、説明できる状態を守る設計です。

<!--
スクリーンショット 5（必須）:
掲載場所: このセクション末尾。
画面: 入金消込画面。
目的: bank transaction と invoice/receivable を見比べ、提案・確認する UI を見せる。
写す要素: 未消込の入金、候補、confirm ボタン、状態 badge。
注意: 実在取引先名や請求番号はマスクするか、サンプルデータを使う。
-->

---

## 6. 過入金は捨てない: client credit

入金額が請求残高を上回ることがあります。

たとえば、110,000円の請求に対して、誤って120,000円が振り込まれた場合です。

このとき、差額10,000円を黙って消してはいけません。

NeNe Clear では、過入金を **client credit** として残す考え方を取っています。

```text
bank deposit: 120,000
invoice outstanding: 110,000
client credit: 10,000
```

client credit は、後で別の請求に充当する余地を残します。

もちろん、それも自動ではなく、オペレーターが確認する前提です。

ここでも大事なのは、金額差分を「なかったこと」にしないことです。

---

## 7. 督促: 送った事実を残す

NeNe Clear は督促も扱います。

ただし、ここでも範囲を絞っています。

NeNe Clear が扱うのは、オペレーター自身の売掛金に対する、専門的で穏当な支払リマインダーです。

第三者の債権回収代行ではありません。

法的判断や強い文言での取立も扱いません。

督促では、次のような情報を残します。

- どの請求に対して送ったか
- 送信先メールアドレス
- 送信時点の未入金額
- 送信者
- 送信日時
- template version

また、短期間に何度も送らないよう、最小送信間隔の考え方もあります。

ローカルでは Mailpit を使うと、実際の外部メールを送らずに送信内容を確認できます。

```text
http://localhost:8383
```

> 注意: NeNe Clear が記録できるのは「送信を試みた」事実です。実運用では SPF / DKIM / DMARC、SMTP relay、bounce 管理などのメール到達性の設計が必要です。

<!--
スクリーンショット 6（必須）:
掲載場所: このセクション末尾。
画面: 督促画面 + Mailpit の受信画面。
目的: 督促がアプリ上で管理され、ローカルでは Mailpit で確認できることを見せる。
写す要素: 督促対象、送信履歴、Mailpit のメール本文。
注意: 実在メールアドレスや取引先名はマスクする。
-->

---

## 8. 監査ログ: 誰が何をしたか

入金消込や督促では、後から「誰が何をしたか」を説明できることが重要です。

NeNe Clear では、変更系の操作を audit event として残します。

例:

| event | 意味 |
| --- | --- |
| `bank_import` | 銀行CSVを取り込んだ |
| `bank_import_batch_reversed` | 取込バッチを取消した |
| `reconciliation_confirmed` | 消込を確定した |
| `reconciliation_reversed` | 消込を取消した |
| `client_credit_applied` | client credit を充当した |
| `dunning_sent` | 督促を送った |

これは「便利な履歴表示」だけではありません。

入金消込はお金に関わる操作なので、後から説明できる形にしておく必要があります。

<!--
スクリーンショット 7（任意だが強い）:
掲載場所: このセクション末尾。
画面: 監査ログ画面。
目的: 操作履歴が UI で確認できることを見せる。
写す要素: event type、actor、occurred_at、詳細 payload。
注意: payload にメールアドレス・口座番号・実名が出る場合はマスクする。
-->

---

## 9. OpenAPI と MCP

NeNe Clear は API-first です。

管理 UI も、基本的には API のクライアントです。

API 契約は OpenAPI で管理されています。

仕様はリポジトリ内の `docs/openapi/openapi.yaml` にあります。この記事の時点では、実行時に OpenAPI を配信するエンドポイントはありません。

また、NeNe Clear には MCP tool catalog の設計もあります。

ただし、重要なのはここです。

**AI が直接 DB を触るのではありません。**

NeNe Clear の方針は、NENE2 と同じです。

```text
AI Agent
  -> MCP tool
  -> documented HTTP API
  -> Handler
  -> UseCase
  -> Repository / upstream API
```

消込では、AI は提案まで。

確定は人間が行う。

この境界を守ることで、AI を使いつつも、お金の操作を無音で走らせないようにしています。

---

## 10. 向いているケース、向いていないケース

向いているケース:

- 銀行CSVと請求書の照合を Excel から分離したい
- 入金消込の履歴を後から説明できるようにしたい
- 督促メールの送信履歴を残したい
- NeNe Invoice と HTTP API でつなぎたい
- self-hosted な業務OSSを試したい
- AI / MCP を使うとしても、アプリ境界を守りたい

向いていないケース:

- 請求書や適格請求書 PDF を発行したい
- 会計ソフトの代わりに仕訳まで作りたい
- 貸倒や時効などの判断を自動化したい
- 第三者の債権回収代行に使いたい
- 共有ホスティングに置くだけで本番運用したい
- AI にDBを直接触らせたい

請求書発行が必要なら NeNe Invoice。

銀行CSVの標準化だけを扱うなら NeNe Profile。

入金消込と督促を扱うのが NeNe Clear です。

---

## まとめ

NeNe Clear は、NENE2 上に作っている API-first な入金消込・督促管理 OSS です。

- 銀行CSVを取り込み、入金行を証跡として残す
- 請求書や売掛金と照合する
- AI やルールは提案まで、人間が確定する
- 一部入金・過入金・client credit を扱う
- 督促メールと送信履歴を管理する
- audit event で操作履歴を残す
- OpenAPI / MCP の境界を意識している
- NeNe Invoice とは別DB・別アプリとして分けている

請求書を作るだけでは、入金後の運用は終わりません。

NeNe Clear は、その「請求書の次」に来る地味で大事な作業を、API と監査証跡のある形に分けて持つためのプロダクトです。

---

## リンク

| 種類 | URL |
| --- | --- |
| NeNe Clear | <https://github.com/hideyukiMORI/nene-clear> |
| NENE2 | <https://github.com/hideyukiMORI/NENE2> |
| NeNe Invoice | <https://github.com/hideyukiMORI/nene-invoice> |
| OpenAPI | <https://github.com/hideyukiMORI/nene-clear/blob/main/docs/openapi/openapi.yaml> |
| Product Vision | <https://github.com/hideyukiMORI/nene-clear/blob/main/docs/explanation/product-vision.md> |
| Scope Contract | <https://github.com/hideyukiMORI/nene-clear/blob/main/docs/explanation/scope-contract.md> |
| Compliance Notes | <https://github.com/hideyukiMORI/nene-clear/blob/main/docs/explanation/payment-reconciliation-dunning-compliance.md> |

フィードバックは GitHub Issues へ歓迎します。
