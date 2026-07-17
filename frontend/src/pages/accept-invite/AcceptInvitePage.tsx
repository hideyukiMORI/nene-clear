import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { getInvitation, acceptInvitation } from '@/api/endpoints'
import { ApiError } from '@/shared/api/client'
import { Button } from '@/shared/ui/button'
import { Icon } from '@/shared/ui/icon'
import { LogoMark } from '@/shared/ui/logo-mark'
import { Notice } from '@/shared/ui/notice'
import { useTranslation } from '@/shared/i18n/use-translation'

const MIN_PASSWORD = 8

/**
 * Public onboarding page reached from the invitation e-mail link
 * (`/accept-invite?token=…`). It validates the token, lets the invitee choose a
 * password, activates the account, then sends them to the login screen.
 */
export default function AcceptInvitePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''

  const [status, setStatus] = useState<'loading' | 'ready' | 'invalid' | 'expired'>(() => token === '' ? 'invalid' : 'loading')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState('')
  const [done, setDone] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    if (token === '') return
    const controller = new AbortController()
    getInvitation(token, controller.signal)
      .then(({ email }) => { setEmail(email); setStatus('ready') })
      .catch(err => {
        if (controller.signal.aborted) return
        setStatus(err instanceof ApiError && err.status === 410 ? 'expired' : 'invalid')
      })
    return () => controller.abort()
  }, [token])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError('')
    if (password.length < MIN_PASSWORD) { setError(t('acceptInvite.tooShort')); return }
    if (password !== confirm) { setError(t('acceptInvite.mismatch')); return }
    setSubmitting(true)
    try {
      await acceptInvitation(token, password)
      setDone(true)
    } catch (err) {
      if (err instanceof ApiError && err.status === 410) setStatus('expired')
      else if (err instanceof ApiError && err.status === 404) setStatus('invalid')
      else setError(t('acceptInvite.failed'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="login">
      <div className="login-aside">
        <div className="login-grid" />
        <div className="brand">
          <LogoMark height={36} className="logo-mark" />
          <b>NeNe Clear</b>
        </div>
        <div className="pitch">
          <h2>{t('acceptInvite.pitchTitle')}</h2>
          <p>{t('acceptInvite.pitchBody')}</p>
        </div>
      </div>

      <div className="login-main">
        <div className="login-card">
          <div className="lh">
            <h1>{t('acceptInvite.title')}</h1>
            {status === 'ready' && <p>{t('acceptInvite.subtitle', { email })}</p>}
          </div>

          {status === 'loading' && <p className="muted">{t('acceptInvite.loading')}</p>}

          {status === 'invalid' && <Notice variant="bad">{t('acceptInvite.invalid')}</Notice>}
          {status === 'expired' && <Notice variant="bad">{t('acceptInvite.expired')}</Notice>}

          {status === 'ready' && done && (
            <>
              <Notice variant="ok">{t('acceptInvite.success')}</Notice>
              <Button variant="primary" style={{ justifyContent: 'center', padding: '11px', marginTop: 16, width: '100%' }} onClick={() => navigate('/admin')}>
                <Icon name="login" />{t('acceptInvite.toLogin')}
              </Button>
            </>
          )}

          {status === 'ready' && !done && (
            <form className="stack" onSubmit={handleSubmit}>
              <div className="field">
                <label>{t('acceptInvite.password')}</label>
                <div className="inp-icon">
                  <Icon name="lock" />
                  <input
                    className="inp" type="password" name="password"
                    value={password} onChange={e => setPassword(e.target.value)}
                    autoComplete="new-password" required minLength={MIN_PASSWORD}
                  />
                </div>
              </div>
              <div className="field">
                <label>{t('acceptInvite.passwordConfirm')}</label>
                <div className="inp-icon">
                  <Icon name="lock" />
                  <input
                    className="inp" type="password" name="password_confirm"
                    value={confirm} onChange={e => setConfirm(e.target.value)}
                    autoComplete="new-password" required minLength={MIN_PASSWORD}
                  />
                </div>
              </div>
              <Button variant="primary" type="submit" disabled={submitting} style={{ justifyContent: 'center', padding: '11px' }}>
                <Icon name="check" />{submitting ? t('common.processing') : t('acceptInvite.submit')}
              </Button>
              {error && <Notice variant="bad">{error}</Notice>}
            </form>
          )}
        </div>
      </div>
    </div>
  )
}
