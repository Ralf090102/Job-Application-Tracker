// Shared API client (Roadmap.md Phase 1) — every call to the Laravel
// backend should go through this instead of hardcoding the base URL or
// repeating fetch boilerplate in every component.
//
// `credentials: 'include'` is set now, ahead of Phase 6 (Sanctum SPA auth),
// so cookies are sent/received cross-origin once auth is wired up. It's a
// no-op for unauthenticated requests like Phase 1's /api/ping.

const API_URL = import.meta.env.VITE_API_URL

if (!API_URL) {
  // Fail loud in dev rather than silently fetching relative URLs that
  // happen to 404 against the Vite dev server instead of Laravel.
  throw new Error('VITE_API_URL is not set — copy frontend/.env.example to frontend/.env')
}

export async function apiFetch(path, options = {}) {
  const response = await fetch(`${API_URL}${path}`, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
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
