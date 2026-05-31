<?php

declare(strict_types=1);

/**
 * 日本語メッセージカタログ — 全日本語ユーザー向け文言の唯一の管理ファイル。
 * キー: 'problem.{slug}.title', 'problem.{slug}.detail', 'dunning_email.*'
 * スラグは使用前に docs/explanation/terminology.md §4 に登録すること。
 *
 * @return array<string, string>
 */
return [

    // -------------------------------------------------------------------------
    // Problem Details — タイトルと詳細（APIエラーレスポンス）
    // -------------------------------------------------------------------------

    'problem.validation-failed.title'  => 'バリデーションエラー',
    'problem.validation-failed.detail' => '入力内容にエラーがあります。',
    'problem.validation-failed.csv-prefix' => 'CSVを解析できませんでした: ',

    'problem.unauthorized.title'  => '認証エラー',
    'problem.unauthorized.detail' => '認証されたユーザーが存在しません。',

    'problem.invalid-credentials.title'  => '認証失敗',
    'problem.invalid-credentials.detail' => 'メールアドレスまたはパスワードが正しくありません。',

    'problem.too-many-login-attempts.title'  => 'ログイン試行回数の超過',
    'problem.too-many-login-attempts.detail' => 'ログイン試行が多すぎます。しばらくしてから再度お試しください。',

    'problem.role-not-assignable.title'  => 'ロール割り当て不可',
    'problem.role-not-assignable.detail' => 'このロールを割り当てる権限がありません。',

    'problem.insufficient-capability.title'  => '権限不足',
    'problem.insufficient-capability.detail' => 'このアクションに必要な権限がありません。',

    'problem.organization-not-found.title'  => '組織が見つかりません',
    'problem.organization-not-found.detail' => '指定された組織は存在しません。',

    'problem.organization-already-exists.title'  => '組織が既に存在します',
    'problem.organization-already-exists.detail' => '同じスラッグの組織がすでに登録されています。',

    'problem.user-not-found.title'  => 'ユーザーが見つかりません',
    'problem.user-not-found.detail' => '指定されたユーザーは存在しません。',

    'problem.user-already-exists.title'  => 'ユーザーが既に存在します',
    'problem.user-already-exists.detail' => 'そのメールアドレスはすでに使用されています。',

    'problem.bank-account-not-found.title'  => '銀行口座が見つかりません',
    'problem.bank-account-not-found.detail' => '指定された銀行口座は存在しません。',

    'problem.bank-import-batch-not-found.title'  => 'インポートバッチが見つかりません',
    'problem.bank-import-batch-not-found.detail' => '指定されたインポートバッチは存在しません。',

    'problem.bank-transaction-not-found.title'  => '銀行取引が見つかりません',
    'problem.bank-transaction-not-found.detail' => '指定された銀行取引は存在しません。',

    'problem.reconciliation-not-found.title'  => '消込が見つかりません',
    'problem.reconciliation-not-found.detail' => '指定された消込は存在しません。',

    'problem.client-credit-not-found.title'  => '前受金が見つかりません',
    'problem.client-credit-not-found.detail' => '指定された前受金は存在しません。',

    'problem.dunning-notice-not-found.title'  => '督促記録が見つかりません',
    'problem.dunning-notice-not-found.detail' => '指定された督促記録は存在しません。',

    // invalid-state-transition: 共通タイトル、コンテキスト別の詳細
    'problem.invalid-state-transition.title'                          => '無効な状態変更',
    'problem.invalid-state-transition.batch-reversed.detail'          => 'バッチはすでに取消済みです。',
    'problem.invalid-state-transition.batch-has-matched.detail'       => '消込済みの取引が含まれているため、バッチを取り消せません。先に消込を解除してください。',
    'problem.invalid-state-transition.tx-not-matchable.detail'        => 'この銀行取引は現在の状態では消込できません。',
    'problem.invalid-state-transition.reconciliation-reversed.detail' => 'この消込はすでに取り消されています。',

    'problem.duplicate-bank-import.title'  => 'インポート重複',
    'problem.duplicate-bank-import.detail' => 'このファイルはすでにインポートされています。',

    'problem.allocation-exceeds-outstanding.title'  => '消込金額超過',
    'problem.allocation-exceeds-outstanding.detail' => '消込金額が請求書の未収残高を超えています。',

    'problem.upstream-invoice-unavailable.title'  => '請求書サービス利用不可',
    'problem.upstream-invoice-unavailable.detail' => '請求書APIに接続できません。消込の確定は一時的に停止しています。',

    'problem.upstream-invoice-not-found.title'  => '請求書が見つかりません',
    'problem.upstream-invoice-not-found.detail' => '上流サービスに指定の請求書が存在しません。',

    'problem.upstream-client-not-found.title'  => '取引先が見つかりません',
    'problem.upstream-client-not-found.detail' => '上流サービスに指定の取引先が存在しないか、削除されています。',

    'problem.invoice-not-eligible-for-dunning.title'  => '督促対象外',
    'problem.invoice-not-eligible-for-dunning.detail' => 'この請求書は支払済み、または残高がないため督促できません。',

    'problem.dunning-too-frequent.title'  => '督促間隔未達',
    'problem.dunning-too-frequent.detail' => '前回の督促から最短間隔が経過していません。',

    'problem.dunning-paused.title'  => '督促一時停止中',
    'problem.dunning-paused.detail' => 'この請求書の督促は一時停止されています。督促を再開してから送信してください。',

    'problem.credit-exceeds-remaining.title'  => '前受金残高超過',
    'problem.credit-exceeds-remaining.detail' => '適用額が前受金の残高を超えています。',

    // -------------------------------------------------------------------------
    // ダニングメール — 取引先への督促通知
    // ADR 0005: 取引先向けは常に日本語。このファイルが唯一の使用テンプレート。
    // sprintf引数: (invoice_number)
    // -------------------------------------------------------------------------
    'dunning_email.subject' => '【お支払いのご案内】請求書 %s のご入金をお願いいたします',

    // sprintf引数: (contact_name, invoice_number, due_at, outstanding, due_at, outstanding)
    'dunning_email.body' => "%s 様\n\n"
        . "平素より大変お世話になっております。\n\n"
        . "請求書 %s（お支払期日：%s）について、¥%s のご入金がまだ確認できておりません（%s 現在）。\n\n"
        . "未収残高：¥%s\n\n"
        . "お手数ですが、お早めにお支払手続きをお願いいたします。\n\n"
        . 'すでにお振込み済みの場合は、本メールへのご返信は不要です。',

];
