// Shared API client (Roadmap.md Phase 1) — every call to the Laravel
// backend should go through this instead of hardcoding the base URL or
// repeating fetch boilerplate in every component.
//
// `credentials: 'include'` sends/receives cookies cross-origin — the
// session cookie Phase 6's auth relies on, and the XSRF-TOKEN cookie read
// below. A no-op for unauthenticated requests like Phase 1's /api/ping.

const API_URL = import.meta.env.VITE_API_URL

if (!API_URL) {
  // Fail loud in dev rather than silently fetching relative URLs that
  // happen to 404 against the Vite dev server instead of Laravel.
  throw new Error('VITE_API_URL is not set — copy frontend/.env.example to frontend/.env')
}

// Sanctum's CSRF cookie (set by GET /sanctum/csrf-cookie) isn't HttpOnly —
// it's readable JS specifically so it can be echoed back as a header.
// Laravel's own axios defaults do exactly this; we're on plain fetch, so
// it has to happen by hand, in one place, rather than in every call site.
function xsrfTokenFromCookie() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : null
}

export async function apiFetch(path, options = {}) {
  const method = (options.method ?? 'GET').toUpperCase()
  const xsrfToken = method !== 'GET' ? xsrfTokenFromCookie() : null

  const response = await fetch(`${API_URL}${path}`, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
      ...options.headers,
    },
    ...options,
  })

  // 204 No Content (DELETE) has no body — response.json() would throw
  // trying to parse an empty string as JSON.
  const hasBody = response.status !== 204 && response.headers.get('content-length') !== '0'
  const data = hasBody ? await response.json() : null

  if (!response.ok) {
    // Laravel's validation-failure body is {message, errors: {field: [msgs]}}.
    // Attach both so callers (e.g. a form) can show field-specific errors
    // instead of just a generic failure message.
    const error = new Error(data?.message || `API request failed: ${response.status} ${response.statusText}`)
    error.status = response.status
    error.errors = data?.errors ?? null
    throw error
  }

  return data
}
