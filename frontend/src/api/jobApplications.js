// Thin wrappers around apiFetch for the one resource Phase 4 needs.
// Each function returns the *unwrapped* record(s) — Laravel's
// JobApplicationResource wraps everything in a top-level "data" key,
// so callers here don't have to know or care about that shape.

import { apiFetch } from './client'

export function listJobApplications() {
  return apiFetch('/api/job-applications').then((json) => json.data)
}

export function createJobApplication(payload) {
  return apiFetch('/api/job-applications', {
    method: 'POST',
    body: JSON.stringify(payload),
  }).then((json) => json.data)
}

export function updateJobApplication(id, payload) {
  return apiFetch(`/api/job-applications/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  }).then((json) => json.data)
}

export function deleteJobApplication(id) {
  return apiFetch(`/api/job-applications/${id}`, { method: 'DELETE' })
}
