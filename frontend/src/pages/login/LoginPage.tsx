import { useState } from 'react'
import { login } from '@/shared/api/endpoints'
import { storeToken } from '@/shared/api/client'
import { Button } from '@hideyukimori/nene2-ui'
import { Icon } from '@/shared/ui/icon'
import { LogoMark } from '@/shared/ui/logo-mark'
import { Notice } from '@/shared/ui/notice'
import { useTranslation } from '@/shared/i18n/use-translation'

export default function LoginPage() {
  const { t } = useTranslation()
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
      // Storing the token flips the reactive auth store; the auth shell then
      // reveals the route the user is on (no navigation needed).
      storeToken(token)
    } catch {
      setError(t('login.error'))
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
          <LogoMark height={36} className="logo-mark" />
          <b>NeNe Clear</b>
        </div>
        <div className="pitch">
          <h2>{t('login.pitch.titleA')}<br />{t('login.pitch.titleB')}</h2>
          <p>{t('login.pitch.body')}</p>
        </div>
        <div className="feats">
          <div className="feat">
            <span className="fi"><Icon decorative name="reconcile" size="sm" /></span>
            {t('login.feature.reconcile')}
          </div>
          <div className="feat">
            <span className="fi"><Icon decorative name="bell" size="sm" /></span>
            {t('login.feature.dunning')}
          </div>
          <div className="feat">
            <span className="fi"><Icon decorative name="shield" size="sm" /></span>
            {t('login.feature.control')}
          </div>
        </div>
      </div>

      {/* Right form */}
      <div className="login-main">
        <div className="login-card">
          <div className="lh">
            <h1>{t('login.title')}</h1>
            <p>{t('login.subtitle')}</p>
          </div>
          <form className="stack" onSubmit={handleSubmit}>
            <div className="field">
              <label>{t('login.email')}</label>
              <div className="inp-icon">
                <Icon decorative name="mail" />
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
              <label>{t('login.password')}</label>
              <div className="inp-icon">
                <Icon decorative name="lock" />
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
            <Button type="submit" disabled={loading} style={{ justifyContent: 'center', padding: '11px' }}>
              <Icon decorative name="login" />
              {loading ? t('common.checking') : t('login.submit')}
            </Button>
            {error && <Notice variant="bad">{error}</Notice>}
          </form>
          <p className="faint" style={{ textAlign: 'center', marginTop: 22, fontSize: '11px', lineHeight: 1.7 }}>
            {t('login.disclaimer')}
          </p>
          <p className="faint" style={{ textAlign: 'center', marginTop: 10, fontSize: '11.5px' }}>
            {t('login.copyright')}
          </p>
        </div>
      </div>
    </div>
  )
}
