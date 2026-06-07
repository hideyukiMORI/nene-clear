/**
 * Help-page content for NeNe Clear. Co-located bilingual ja/en pairs (like
 * `components/keyboard/shortcuts-data.ts`) rather than locale-catalog keys,
 * since the page is long-form prose with steps, flows, and an FAQ that don't fit
 * flat keys. `HelpPage` picks ja or en from the active locale.
 *
 * Inline markup inside any string: `**bold**` and `` `code` `` (rendered by the
 * page's <Rich> helper). Keep wording aligned with the real UI labels so the
 * guide stays accurate. Ported from nene-invoice's help page (design 05),
 * rewritten for Clear's domain (入金消込・督促).
 */

/** A translated string: primary ja + secondary en. May contain inline markup. */
export interface Bi {
  ja: string
  en: string
}

export type HelpBlock =
  | { kind: 'lede'; text: Bi }
  | { kind: 'subhead'; text: Bi }
  | { kind: 'steps'; items: { title: Bi; desc: Bi }[] }
  | { kind: 'proc'; items: Bi[] }
  | { kind: 'flow'; chips: Bi[]; branch?: Bi }
  | { kind: 'terms'; items: { term: Bi; desc: Bi }[] }
  | { kind: 'deflist'; items: { term: Bi; desc: Bi }[] }
  | { kind: 'note'; tone?: 'warn'; title?: Bi; text: Bi }
  | { kind: 'options'; items: { title: Bi; desc: Bi }[] }
  | { kind: 'keys'; groups: { heading: Bi; rows: { label: Bi; caps: string[] }[] }[] }
  | { kind: 'faq'; items: { q: Bi; a: Bi }[] }

export interface HelpSection {
  /** Anchor id (also the ToC target). */
  id: string
  title: Bi
  /** Marks the section as admin-only (badge in the ToC and the heading). */
  admin?: boolean
  blocks: HelpBlock[]
}

export const HELP_LABELS = {
  eyebrow: { ja: 'ヘルプ', en: 'Help' },
  heroTitle: { ja: '操作ガイド・よくある質問', en: 'Guides & FAQ' },
  heroLede: {
    ja: 'NeNe Clear を初めて使う方向けの操作ガイドです。銀行入金の取込から消込・督促までの流れと、つまずきやすいポイントをまとめています。',
    en: 'A getting-started guide for people new to NeNe Clear. It walks through the flow from importing bank deposits to reconciliation and dunning, and the spots people commonly get stuck on.',
  },
  chipReconcile: { ja: '入金消込', en: 'Reconciliation' },
  chipSelfhost: { ja: '自己ホスト型', en: 'Self-hosted' },
  chipUpdated: { ja: '最終更新', en: 'Updated' },
  updatedDate: '2026-06-08',
  toc: { ja: '目次', en: 'Contents' },
  adminBadge: { ja: '管理者', en: 'Admin' },
  backToToc: { ja: '目次へ戻る', en: 'Back to contents' },
  footTitle: { ja: '解決しませんでしたか？', en: 'Still need help?' },
  footDesc: {
    ja: '管理者にお問い合わせいただくか、キーボードショートカット一覧（? キー）もご活用ください。',
    en: 'Contact your administrator, or press ? for the keyboard-shortcut list.',
  },
} as const

export const HELP_SECTIONS: HelpSection[] = [
  {
    id: 'start',
    title: { ja: 'はじめに（クイックスタート）', en: 'Getting started (quick start)' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: 'NeNe Clear は、銀行入金と請求を突き合わせて**入金消込**を行い、未収には**督促**を送る自己ホスト型システムです。請求書の作成は行いません（請求データは Invoice 連携から取得します）。',
          en: 'NeNe Clear is a self-hosted system that matches bank deposits to invoices (**reconciliation**) and sends **dunning** for unpaid balances. It does not create invoices — invoice data comes from the Invoice integration.',
        },
      },
      {
        kind: 'lede',
        text: {
          ja: '全体の流れはシンプルです。銀行の入出金明細CSVを取り込み、入金を請求書に消し込み、未収には督促を送る。ダッシュボードで未消込・延滞・前受金の状況をいつでも把握できます。',
          en: 'The flow is simple: import your bank statement CSV, reconcile deposits against invoices, and dun the unpaid ones. The dashboard shows your unmatched / overdue / client-credit situation at any time.',
        },
      },
      {
        kind: 'flow',
        chips: [
          { ja: '銀行CSVを取込', en: 'Import bank CSV' },
          { ja: '入金を消込', en: 'Reconcile deposits' },
          { ja: '未収へ督促', en: 'Dun the unpaid' },
        ],
        branch: { ja: '請求に紐づかない入金は「前受金」へ', en: 'Deposits with no invoice → client credits' },
      },
      {
        kind: 'note',
        title: { ja: '権限について', en: 'About roles' },
        text: {
          ja: '消込・督促はオペレーター以上で実行できます。**設定・ユーザー管理・監査ログは管理者のみ**です（メニューに表示されない場合は権限がありません）。',
          en: 'Operators and above can reconcile and dun. **Settings, user management, and the audit log are admin-only** (if a menu item is missing, you lack the capability).',
        },
      },
    ],
  },
  {
    id: 'dashboard',
    title: { ja: 'ダッシュボード', en: 'Dashboard' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: 'ログイン後の最初の画面です。未消込の取引、延滞している請求、当月の消込済み、前受金残高などの概況をまとめて確認できます。',
          en: 'The first screen after login. It summarises unmatched transactions, overdue invoices, this month’s cleared amount, and the client-credit balance.',
        },
      },
      {
        kind: 'proc',
        items: [
          { ja: '気になる数字のカードから各画面へ移動できます。', en: 'Jump to each screen from the KPI card you care about.' },
          { ja: '未消込の取引一覧から、そのまま消込作業に入れます。', en: 'Start reconciling straight from the unmatched-transactions list.' },
        ],
      },
    ],
  },
  {
    id: 'import',
    title: { ja: '銀行CSVの取込', en: 'Importing bank CSV' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '銀行の入出金明細CSVを取り込み、消込対象の入金取引として登録します。口座ごとのCSV形式（文字コード・日付書式・列位置）は設定画面で登録しておきます。',
          en: 'Import your bank statement CSV to register deposits as transactions for reconciliation. Each account’s CSV format (encoding, date format, column positions) is registered in Settings beforehand.',
        },
      },
      {
        kind: 'steps',
        items: [
          { title: { ja: '口座を選ぶ', en: 'Choose the account' }, desc: { ja: '取込先の銀行口座を選択します。', en: 'Pick the bank account to import into.' } },
          { title: { ja: 'CSVを選ぶ', en: 'Choose the CSV' }, desc: { ja: 'ファイルをドラッグ＆ドロップ、またはクリックして選択します。', en: 'Drag & drop the file, or click to choose it.' } },
          { title: { ja: '取込む', en: 'Import' }, desc: { ja: '「取込む」を押すと取引が登録されます。**重複行は自動でスキップ**されます。', en: 'Press Import to register the rows. **Duplicate rows are skipped automatically.**' } },
        ],
      },
      {
        kind: 'note',
        text: {
          ja: '誤って取り込んだバッチは取込履歴から**取消**できます（消込済みの行を含むバッチは、先に消込を取り消す必要があります）。',
          en: 'A wrong batch can be **reversed** from the import history (a batch with already-matched lines needs those reconciliations reversed first).',
        },
      },
    ],
  },
  {
    id: 'reconcile',
    title: { ja: '入金の消込', en: 'Reconciling deposits' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '消込画面は「**未消込**」タブと「**消込一覧（履歴）**」タブに分かれています。未消込タブで入金取引を請求書に突き合わせて確定します。',
          en: 'The Reconciliation screen has an **Unmatched** tab and a **Reconciliations (history)** tab. Match deposits to invoices and confirm them on the Unmatched tab.',
        },
      },
      {
        kind: 'steps',
        items: [
          { title: { ja: '取引を開く', en: 'Open a transaction' }, desc: { ja: '未消込タブで対象の入金の「消込を確定」を押します（一覧で **j / k** で移動し **Enter** でも開けます）。', en: 'Press “Confirm match” on a deposit in the Unmatched tab (or move with **j / k** and press **Enter**).' } },
          { title: { ja: '候補を確認', en: 'Review candidates' }, desc: { ja: '金額や相手先から推定された請求書候補が表示されます。「選択」で配賦に取り込めます。', en: 'Suggested invoice candidates (by amount / payer) are shown. “Use” pulls one into the allocation.' } },
          { title: { ja: '配賦を入力', en: 'Enter allocations' }, desc: { ja: '請求書IDと消込金額を入力します。**1件以上**必要です。1入金を複数請求へ分けることもできます。', en: 'Enter invoice ID and amount. **At least one** is required; one deposit can be split across several invoices.' } },
          { title: { ja: '確定', en: 'Confirm' }, desc: { ja: '内容を確認して「消込を確定」。必要なら理由コード（相殺・手数料差引など）を残せます。', en: 'Review and confirm. Optionally record a reason code (offset, fee deduction, etc.).' } },
        ],
      },
      {
        kind: 'flow',
        chips: [
          { ja: '未消込', en: 'Unmatched' },
          { ja: '一部消込', en: 'Partially matched' },
          { ja: '消込済', en: 'Matched' },
        ],
        branch: { ja: '取消すと未消込へ戻ります', en: 'Reversing returns it to unmatched' },
      },
      {
        kind: 'note',
        text: {
          ja: '確定した消込は履歴タブから**取消**できます。取消理由は監査ログに残ります。入金額が請求の未収を超える場合は、超過分が**前受金**として記録されます。',
          en: 'A confirmed match can be **reversed** from the history tab; the reason is audited. If a deposit exceeds the invoice’s outstanding balance, the excess is recorded as a **client credit**.',
        },
      },
    ],
  },
  {
    id: 'credits',
    title: { ja: '前受金', en: 'Client credits' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '請求に紐づかない入金や過入金は「前受金」として残高管理し、後から請求へ適用できます。',
          en: 'Deposits not tied to an invoice, or overpayments, are held as client credits and can be applied to an invoice later.',
        },
      },
      {
        kind: 'proc',
        items: [
          { ja: '前受金一覧で対象の「適用」を押します。', en: 'Press “Apply” on a credit in the list.' },
          { ja: '適用先の請求書IDと金額を入力します（残高の範囲内）。', en: 'Enter the target invoice ID and amount (within the remaining balance).' },
          { ja: '適用すると残高が減り、全額適用で「無効（適用済み）」になります。', en: 'Applying reduces the balance; once fully applied the credit becomes voided/applied.' },
        ],
      },
    ],
  },
  {
    id: 'dunning',
    title: { ja: '督促', en: 'Dunning' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '延滞・一部入金の請求に対して督促メールを送り、送信履歴を一元管理します。督促画面は対象請求の一覧と送信履歴で構成されます。',
          en: 'Send dunning e-mails for overdue / partially-paid invoices and keep the send history in one place. The screen lists eligible invoices and the send history.',
        },
      },
      {
        kind: 'steps',
        items: [
          { title: { ja: '対象を選ぶ', en: 'Pick an invoice' }, desc: { ja: '督促対象の請求書一覧から送信したいものを選びます。', en: 'Choose one from the eligible-invoice list.' } },
          { title: { ja: '送信', en: 'Send' }, desc: { ja: '「督促を送信」で、登録テンプレートのメールが送られ履歴に記録されます。', en: '“Send dunning” sends the templated e-mail and records it in the history.' } },
          { title: { ja: '停止 / 再開', en: 'Pause / resume' }, desc: { ja: '入金調整中などは請求単位で**停止**できます（理由は履歴に残る）。再開で戻せます。', en: 'You can **pause** dunning per invoice (e.g. while arranging payment); the reason is kept. Resume to re-enable.' } },
        ],
      },
      {
        kind: 'note',
        text: {
          ja: '同じ請求への督促には**最短間隔**（設定画面の「督促の最短間隔」）があり、間隔内の再送はブロックされます。実際のメール送信には SMTP の設定が必要です（未設定の場合はログのみ）。',
          en: 'A **minimum interval** (Settings) applies per invoice; re-sending within it is blocked. Real e-mail delivery needs SMTP configured (otherwise it is log-only).',
        },
      },
    ],
  },
  {
    id: 'export',
    title: { ja: 'CSVエクスポート', en: 'CSV export' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '各一覧（入金明細・消込・前受金・督促）は「CSV出力」で書き出せます。**画面で適用中の絞り込み（期間・金額・ステータス等）がそのままCSVに反映**されます。',
          en: 'Each list (bank transactions, reconciliations, client credits, dunning) exports via “Export CSV”. **The filters currently applied on screen (date range, amount, status, …) are reflected in the CSV.**',
        },
      },
      {
        kind: 'note',
        text: {
          ja: '日付範囲は、設定の**決算月**から算出した「当会計年度の期初〜本日」が初期値になります。全期間を出したいときは絞り込みを**クリア**してから出力してください。',
          en: 'The date range defaults to the **current fiscal year** (from the fiscal-year start, derived from the fiscal year-end month in Settings, to today). To export everything, **Clear** the filters first.',
        },
      },
    ],
  },
  {
    id: 'settings',
    title: { ja: '設定', en: 'Settings' },
    admin: true,
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '連携・ポリシー・口座をまとめて構成します（管理者のみ）。',
          en: 'Configure integration, policy, and accounts in one place (admin only).',
        },
      },
      {
        kind: 'deflist',
        items: [
          { term: { ja: 'Invoice API 連携', en: 'Invoice API' }, desc: { ja: '請求データ取得元のURLとトークン参照。「接続テスト」で疎通を確認できます。', en: 'URL and token reference for the invoice source. “Test connection” checks reachability.' } },
          { term: { ja: '督促の最短間隔', en: 'Dunning interval' }, desc: { ja: '同一請求への督促を再送できるまでの最短日数（1以上）。', en: 'Minimum days before dunning the same invoice again (≥ 1).' } },
          { term: { ja: '決算月', en: 'Fiscal year-end month' }, desc: { ja: '会計年度の末月（1〜12、任意）。CSVの期間初期値に使われます。', en: 'The fiscal year-end month (1–12, optional). Used as the default CSV date range.' } },
          { term: { ja: '銀行口座', en: 'Bank accounts' }, desc: { ja: '取込CSVの形式（文字コード・日付書式・列位置）を口座ごとに登録します。', en: 'Per-account CSV format (encoding, date format, column positions) for imports.' } },
        ],
      },
    ],
  },
  {
    id: 'users',
    title: { ja: 'ユーザー管理（招待）', en: 'User management (invitations)' },
    admin: true,
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: 'メンバーをメールで招待します（管理者のみ）。招待された人は自分でパスワードを設定してから利用を開始します。',
          en: 'Invite members by e-mail (admin only). Invitees set their own password before they can sign in.',
        },
      },
      {
        kind: 'steps',
        items: [
          { title: { ja: '招待', en: 'Invite' }, desc: { ja: 'ユーザー管理で「ユーザーを招待」→ メールアドレスとロールを入力して送信。', en: 'In Users, “Invite user” → enter e-mail and role, then send.' } },
          { title: { ja: 'メールのリンク', en: 'E-mail link' }, desc: { ja: '招待者に届くメールのリンクを開きます（**有効期限7日**）。', en: 'The invitee opens the link in the e-mail (**valid for 7 days**).' } },
          { title: { ja: 'パスワード設定', en: 'Set password' }, desc: { ja: 'パスワードを設定するとアカウントが有効化され、ログインできるようになります。', en: 'Setting a password activates the account, and they can sign in.' } },
        ],
      },
      {
        kind: 'note',
        text: {
          ja: 'ロールは **管理者 / オペレーター / 閲覧者**。オペレーターは消込・督促が可能、閲覧者は閲覧のみ。管理者だけが設定・ユーザー管理・監査ログにアクセスできます。',
          en: 'Roles are **admin / operator / viewer**. Operators can reconcile and dun; viewers are read-only. Only admins reach Settings, Users, and the audit log.',
        },
      },
    ],
  },
  {
    id: 'audit',
    title: { ja: '監査ログ', en: 'Audit log' },
    admin: true,
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '取込・消込・督促・設定変更・ログインなど、状態を変える操作はすべて記録されます（管理者のみ）。誰が・いつ・何を変更したかを追跡できます。',
          en: 'Every state-changing operation — imports, reconciliations, dunning, settings changes, logins — is recorded (admin only), so you can trace who changed what and when.',
        },
      },
    ],
  },
  {
    id: 'keys',
    title: { ja: 'キーボードショートカット', en: 'Keyboard shortcuts' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '`?` キーでいつでもショートカット一覧を開けます。主なものは次のとおりです。',
          en: 'Press `?` anytime to open the shortcut list. The main ones:',
        },
      },
      {
        kind: 'keys',
        groups: [
          {
            heading: { ja: '画面遷移（g に続けて）', en: 'Navigation (g, then…)' },
            rows: [
              { label: { ja: 'ダッシュボード', en: 'Dashboard' }, caps: ['g', 'd'] },
              { label: { ja: '消込', en: 'Reconciliation' }, caps: ['g', 'r'] },
              { label: { ja: '銀行CSV取込', en: 'Bank import' }, caps: ['g', 'i'] },
              { label: { ja: '督促', en: 'Dunning' }, caps: ['g', 'n'] },
            ],
          },
          {
            heading: { ja: 'リスト操作', en: 'List' },
            rows: [
              { label: { ja: '次 / 前の行', en: 'Next / Prev row' }, caps: ['j', 'k'] },
              { label: { ja: '選択行を開く', en: 'Open row' }, caps: ['Enter'] },
              { label: { ja: '検索へフォーカス', en: 'Focus search' }, caps: ['/'] },
            ],
          },
          {
            heading: { ja: '全般', en: 'General' },
            rows: [
              { label: { ja: 'コマンドパレット', en: 'Command palette' }, caps: ['Ctrl', 'K'] },
              { label: { ja: 'ショートカット一覧', en: 'Shortcut list' }, caps: ['?'] },
            ],
          },
        ],
      },
    ],
  },
  {
    id: 'faq',
    title: { ja: 'よくある質問', en: 'FAQ' },
    blocks: [
      {
        kind: 'faq',
        items: [
          {
            q: { ja: 'メニューに「設定」や「ユーザー管理」が出てきません。', en: 'I don’t see Settings or Users in the menu.' },
            a: { ja: 'それらは**管理者のみ**の画面です。オペレーター・閲覧者には表示されません。必要な場合は管理者にロール変更を依頼してください。', en: 'Those are **admin-only**. They are hidden for operators and viewers. Ask an admin to change your role if needed.' },
          },
          {
            q: { ja: '消込の確定でエラーになります。', en: 'Confirming a match shows an error.' },
            a: { ja: '消込先（請求書IDと消込金額）が**1件以上**入力されているか確認してください。金額が請求の未収を超える場合は超過分が前受金になります。', en: 'Check that **at least one** allocation (invoice ID + amount) is filled in. If the amount exceeds the invoice’s outstanding, the excess becomes a client credit.' },
          },
          {
            q: { ja: '督促メールが届きません。', en: 'The dunning e-mail didn’t arrive.' },
            a: { ja: '実送信には SMTP の設定が必要です（未設定だとログのみ）。また、同一請求への督促は設定の**最短間隔**内は再送できません。', en: 'Real delivery needs SMTP configured (otherwise log-only). Also, dunning the same invoice is blocked within the configured **minimum interval**.' },
          },
          {
            q: { ja: 'CSVを全期間で出したい。', en: 'I want to export the full period in CSV.' },
            a: { ja: '日付範囲が当会計年度で初期設定されています。絞り込みの**クリア**を押してから「CSV出力」してください。', en: 'The date range defaults to the current fiscal year. Press **Clear** on the filters, then “Export CSV”.' },
          },
          {
            q: { ja: 'しばらく操作していなかったら画面が変わりました。', en: 'The screen changed after I was idle.' },
            a: { ja: 'セッションが切れると、その場にログイン画面が表示されます。再ログインすると元の画面に戻れます。', en: 'When your session expires, the login screen appears in place. Sign in again to return to the same screen.' },
          },
        ],
      },
    ],
  },
  {
    id: 'disclaimer',
    title: { ja: '免責事項・ご利用にあたって', en: 'Disclaimer & terms of use' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '本ソフトウェア（NeNe Clear）は、入金消込・督促管理を補助するツールであり、税務・会計・法務に関する助言を提供するものではありません。消込結果の正確性、督促の内容・時期・送付の可否、税務申告および帳簿・書類の保存（電子帳簿保存法等）については、最終的にご利用者ご自身および顧問税理士の責任でご確認ください。',
          en: 'This software (NeNe Clear) is a tool that assists with payment reconciliation and dunning. It does not provide tax, accounting, or legal advice. The accuracy of reconciliation results, the content / timing / appropriateness of dunning, and tax filing and record retention (including under the Electronic Books Preservation Act) are ultimately your responsibility and that of your tax accountant.',
        },
      },
      {
        kind: 'lede',
        text: {
          ja: '本ソフトウェアは MIT ライセンスに基づき「現状有姿（AS IS）」で提供され、明示・黙示を問わずいかなる保証も行いません。本ソフトウェアの使用または使用不能から生じたいかなる損害についても、作者および権利者は責任を負いません。',
          en: 'This software is provided “AS IS” under the MIT License, without warranty of any kind, express or implied. The authors and copyright holders are not liable for any damages arising from the use of, or inability to use, this software.',
        },
      },
      {
        kind: 'lede',
        text: {
          ja: '自己ホスト型のため、データのバックアップ・保管・セキュリティおよび法定保存期間の遵守は、運用される事業者の責任となります。また、法令・制度の改正への追随を保証するものではありません。',
          en: 'Because it is self-hosted, backing up, storing, and securing your data, and complying with statutory retention periods, are the responsibility of the operating business. Nor does it guarantee that it stays up to date with changes in laws or regulations.',
        },
      },
      {
        kind: 'note',
        text: {
          ja: '督促メールの送信は、送信先・文面・頻度を含め、関連法令や取引先との契約に照らしてご利用者の判断と責任で行ってください。',
          en: 'Sending dunning e-mails — including recipients, wording, and frequency — is done at your own discretion and responsibility, in light of applicable laws and your agreements with clients.',
        },
      },
    ],
  },
]
