export const MOD = 'mod'

export interface ShortcutCombo {
  caps: string[]
  join: 'then' | 'plus' | 'none'
}
export interface ShortcutRow {
  ja: string
  en: string
  combos: ShortcutCombo[]
}
export interface ShortcutGroup {
  ja: string
  en: string
  rows: ShortcutRow[]
}

// Labels mirror the nene-clear nav terminology; the g 2nd keys match GOTO /
// COMMANDS in KeyboardShortcuts.tsx / CommandPalette.tsx.
export const SHORTCUT_GROUPS: ShortcutGroup[] = [
  {
    ja: '画面遷移',
    en: 'Navigation',
    rows: [
      { ja: 'ダッシュボード', en: 'Dashboard', combos: [{ caps: ['g', 'd'], join: 'then' }] },
      { ja: '消込', en: 'Reconciliation', combos: [{ caps: ['g', 'r'], join: 'then' }] },
      { ja: '銀行取引一覧', en: 'Bank transactions', combos: [{ caps: ['g', 'b'], join: 'then' }] },
      { ja: '銀行CSV取込', en: 'Bank import', combos: [{ caps: ['g', 'i'], join: 'then' }] },
      { ja: '前受金', en: 'Client credits', combos: [{ caps: ['g', 'c'], join: 'then' }] },
      { ja: '督促', en: 'Dunning', combos: [{ caps: ['g', 'n'], join: 'then' }] },
      { ja: 'ユーザー管理', en: 'Users', combos: [{ caps: ['g', 'u'], join: 'then' }] },
      { ja: '設定', en: 'Settings', combos: [{ caps: ['g', 's'], join: 'then' }] },
      { ja: '監査ログ', en: 'Audit log', combos: [{ caps: ['g', 'a'], join: 'then' }] },
      { ja: 'ヘルプ', en: 'Help', combos: [{ caps: ['g', 'h'], join: 'then' }] },
    ],
  },
  {
    ja: 'リスト',
    en: 'List',
    rows: [
      {
        ja: '次 / 前の行',
        en: 'Next / Prev row',
        combos: [{ caps: ['j'], join: 'none' }, { caps: ['k'], join: 'none' }],
      },
      {
        ja: '選択行を開く',
        en: 'Open row',
        combos: [{ caps: ['Enter'], join: 'none' }, { caps: ['o'], join: 'none' }],
      },
    ],
  },
  {
    ja: 'アクション',
    en: 'Actions',
    rows: [
      { ja: '検索へフォーカス', en: 'Focus search', combos: [{ caps: ['/'], join: 'none' }] },
    ],
  },
  {
    ja: 'フォーム',
    en: 'Form',
    rows: [
      { ja: '確定 / 送信', en: 'Submit', combos: [{ caps: [MOD, 'Enter'], join: 'plus' }] },
      { ja: '中断 / 閉じる', en: 'Cancel', combos: [{ caps: ['Esc'], join: 'none' }] },
    ],
  },
  {
    ja: '全般',
    en: 'General',
    rows: [
      { ja: 'コマンドパレット', en: 'Command palette', combos: [{ caps: [MOD, 'K'], join: 'plus' }] },
      { ja: 'ショートカット一覧', en: 'Show shortcuts', combos: [{ caps: ['?'], join: 'none' }] },
    ],
  },
]
