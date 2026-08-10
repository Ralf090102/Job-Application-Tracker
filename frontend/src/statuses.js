// Mirrors App\Enums\ApplicationStatus on the backend. There's no shared
// source of truth across the language boundary in a decoupled setup like
// this one — if a case is ever added/renamed in the PHP enum, this list
// has to be updated by hand to match. (A `GET /api/statuses` endpoint
// would remove that duplication; not worth it yet for six fixed values.)
//
// Colors match the --color-status-* tokens in index.css. Duplicated
// rather than read from CSS because these drive inline styles (dynamic
// per-row data, which Tailwind's class-scanning can't pick up) — see
// StatusBadge and PipelineSummary.
export const STATUS_OPTIONS = [
  { value: 'saved', label: 'Saved', color: '#8b8578' },
  { value: 'applied', label: 'Applied', color: '#3b6ea8' },
  { value: 'interviewing', label: 'Interviewing', color: '#c17f2c' },
  { value: 'offer', label: 'Offer', color: '#3f7d4c' },
  { value: 'rejected', label: 'Rejected', color: '#a64444' },
  { value: 'withdrawn', label: 'Withdrawn', color: '#6b6560' },
]

export function statusLabel(value) {
  return STATUS_OPTIONS.find((s) => s.value === value)?.label ?? value
}

export function statusColor(value) {
  return STATUS_OPTIONS.find((s) => s.value === value)?.color ?? '#6b7280'
}
