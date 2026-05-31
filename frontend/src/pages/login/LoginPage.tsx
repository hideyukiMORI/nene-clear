import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { login } from '@/api/endpoints'
import { storeToken } from '@/api/client'
import { Icon, Button, Notice } from '@/components/ui'

export default function LoginPage() {
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const { token } = await login(email, password)
      storeToken(token)
      navigate('/admin', { replace: true })
    } catch {
      setError('メールアドレスまたはパスワードが正しくありません。')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="login">
      {/* Left aside */}
      <div className="login-aside">
        <div className="login-grid" />
        <div className="brand">
          <svg style={{ width: 36, height: 36 }} viewBox="0 0 40 40" fill="none">
            <rect x="2" y="2" width="36" height="36" rx="3" fill="#fff" fillOpacity="0.06" stroke="#fff" strokeOpacity="0.5" />
            <path d="M11 27V13l9 10V13M24 20.5h6M27 13l3 7.5-3 7" stroke="#fff" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
          <b>NeNe Clear</b>
        </div>
        <div className="pitch">
          <h2>入金消込から督促まで、<br />確実に、ひと続きで。</h2>
          <p>銀行入金データと請求を正確に突合し、未収の取りこぼしを防ぐ。経理の現場のための堅実な消込・債権管理基盤です。</p>
        </div>
        <div className="feats">
          <div className="feat">
            <span className="fi"><Icon name="reconcile" size="sm" /></span>
            銀行CSVの自動突合と消込候補のサジェスト
          </div>
          <div className="feat">
            <span className="fi"><Icon name="bell" size="sm" /></span>
            延滞請求の督促送信と履歴の一元管理
          </div>
          <div className="feat">
            <span className="fi"><Icon name="shield" size="sm" /></span>
            ロール権限と監査ログによる堅牢な統制
          </div>
        </div>
      </div>

      {/* Right form */}
      <div className="login-main">
        <div className="login-card">
          <div className="lh">
            <h1>サインイン</h1>
            <p>アカウント情報を入力してください。</p>
          </div>
          <form className="stack" onSubmit={handleSubmit}>
            <div className="field">
              <label>メールアドレス</label>
              <div className="inp-icon">
                <Icon name="mail" />
                <input
                  className="inp"
                  type="email" name="email"
                  placeholder="you@example.com"
                  value={email}
                  onChange={e => setEmail(e.target.value)}
                  autoComplete="username"
                  required
                />
              </div>
            </div>
            <div className="field">
              <label>パスワード</label>
              <div className="inp-icon">
                <Icon name="lock" />
                <input
                  className="inp"
                  type="password" name="password"
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  autoComplete="current-password"
                  required
                />
              </div>
            </div>
            <Button variant="primary" type="submit" disabled={loading} style={{ justifyContent: 'center', padding: '11px' }}>
              <Icon name="login" />
              {loading ? '確認中…' : 'サインイン'}
            </Button>
            {error && <Notice variant="bad">{error}</Notice>}
          </form>
          <p className="faint" style={{ textAlign: 'center', marginTop: 26, fontSize: '11.5px' }}>
            © 2026 NeNe Clear — Accounts Receivable Suite
          </p>
        </div>
      </div>
    </div>
  )
}
