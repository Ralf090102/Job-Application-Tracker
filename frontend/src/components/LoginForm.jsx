import { useState } from 'react'
import { login } from '../api/auth'

const FIELD =
  'w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink transition-colors ' +
  'focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30'

// Single-user personal tool, not multi-tenant (Roadmap.md Phase 6) — no
// registration screen, just a login backed by the one seeded account.
export default function LoginForm({ onLoggedIn }) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)

    try {
      const user = await login(email, password)
      onLoggedIn(user)
    } catch (err) {
      setError(err.errors?.email?.[0] ?? err.message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="mx-auto flex max-w-sm flex-col justify-center px-6 py-24">
      <h1 className="font-display text-3xl font-medium tracking-tight text-ink">Job Application Tracker</h1>
      <p className="mt-2 text-sm text-ink-soft">Sign in to continue.</p>

      <form onSubmit={handleSubmit} className="mt-8 space-y-4 rounded-lg border border-line bg-surface p-6">
        {error && (
          <p className="rounded-md border border-status-rejected/30 bg-status-rejected/5 px-3 py-2 text-sm text-status-rejected">
            {error}
          </p>
        )}

        <label className="block">
          <span className="mb-1 block text-sm font-medium text-ink-soft">Email</span>
          <input
            className={FIELD}
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            autoComplete="username"
          />
        </label>

        <label className="block">
          <span className="mb-1 block text-sm font-medium text-ink-soft">Password</span>
          <input
            className={FIELD}
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            autoComplete="current-password"
          />
        </label>

        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-md bg-accent px-4 py-2 text-sm font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50"
        >
          {submitting ? 'Signing in…' : 'Sign in'}
        </button>
      </form>
    </main>
  )
}
