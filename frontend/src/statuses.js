// Mirrors App\Enums\ApplicationStatus on the backend. There's no shared
// source of truth across the language boundary in a decoupled setup like
// this one — if a case is ever added/renamed in the PHP enum, this list
// has to be updated by hand to match. (A `GET /api/statuses` endpoint
// would remove that duplication; not worth it yet for six fixed values.)
export const STATUS_OPTIONS = [
  { value: 'saved', label: 'Saved', color: '#6b7280' },
  { value: 'applied', label: 'Applied', color: '#2563eb' },
  { value: 'interviewing', label: 'Interviewing', color: '#d97706' },
  { value: 'offer', label: 'Offer', color: '#16a34a' },
  { value: 'rejected', label: 'Rejected', color: '#dc2626' },
  { value: 'withdrawn', label: 'Withdrawn', color: '#78716c' },
]

export function statusLabel(value) {
  return STATUS_OPTIONS.find((s) => s.value === value)?.label ?? value
}

export function statusColor(value) {
  return STATUS_OPTIONS.find((s) => s.value === value)?.color ?? '#6b7280'
}
