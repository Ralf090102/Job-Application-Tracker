// Phase 6: Sanctum SPA session auth — cookies, not bearer tokens. See
// Roadmap.md's Architecture section for why this pattern was chosen.

import { apiFetch } from './client'

// Sanctum's own route, outside /api — sets the (non-HttpOnly) XSRF-TOKEN
// cookie that client.js echoes back as a header on state-changing requests.
// Must happen before login specifically; an authenticated session's cookie
// can outlive the CSRF cookie, so refreshing it before login is what the
// Sanctum docs themselves recommend, not just paranoia.
function ensureCsrfCookie() {
  return apiFetch('/sanctum/csrf-cookie')
}

export async function login(email, password) {
  await ensureCsrfCookie()
  return apiFetch('/api/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  }).then((json) => json.data)
}

export function logout() {
  return apiFetch('/api/logout', { method: 'POST' })
}

// Doubles as "am I logged in?" — resolves to the user, or null if not
// authenticated. A 401 here is an expected state, not a failure to
// propagate to the caller as a thrown error.
export async function fetchCurrentUser() {
  try {
    return await apiFetch('/api/user')
  } catch (error) {
    if (error.status === 401) return null
    throw error
  }
}
