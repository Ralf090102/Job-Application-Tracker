import { useEffect, useState } from 'react'
import { apiFetch } from '../api/client'

// Roadmap.md Phase 1 exit criteria: prove this component received real data
// from Laravel over an actual cross-origin HTTP call, not a hardcoded string.
export default function Ping() {
  const [status, setStatus] = useState('loading') // 'loading' | 'ok' | 'error'
  const [message, setMessage] = useState(null)

  useEffect(() => {
    apiFetch('/api/ping')
      .then((data) => {
        setMessage(data.message)
        setStatus('ok')
      })
      .catch((error) => {
        setMessage(error.message)
        setStatus('error')
      })
  }, [])

  if (status === 'loading') return <p>Contacting the Laravel API…</p>
  if (status === 'error') return <p style={{ color: 'crimson' }}>Failed: {message}</p>

  return (
    <p>
      Laravel says: <strong>{message}</strong>
    </p>
  )
}
