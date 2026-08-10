import { STATUS_OPTIONS } from '../statuses'

// The signature element (see the design-plan note in the styling commit):
// not decoration — a live count per pipeline stage is genuinely more
// useful than a flat table for answering "where do things actually
// stand," and it's the same six-color language used everywhere else on
// the page (form dropdown, table badges).
//
// Phase 7: each stage is now also a status filter — clicking one narrows
// the list below to that stage, clicking the active one clears it. The
// counts themselves stay computed off the *unfiltered* list, so the strip
// keeps answering "where do things stand overall," not just "within the
// current filter" — a filter UI that only describes itself isn't useful.
export default function PipelineSummary({ jobApplications, activeStatus, onSelectStatus }) {
  const counts = Object.fromEntries(STATUS_OPTIONS.map((s) => [s.value, 0]))
  for (const app of jobApplications) {
    counts[app.status] = (counts[app.status] ?? 0) + 1
  }

  return (
    <dl className="flex flex-wrap gap-x-2 gap-y-2 rounded-lg border border-line bg-surface px-4 py-3">
      {STATUS_OPTIONS.map((stage) => {
        const isActive = activeStatus === stage.value
        return (
          <button
            key={stage.value}
            type="button"
            onClick={() => onSelectStatus(isActive ? null : stage.value)}
            className="flex items-center gap-2 rounded-md px-2 py-1 transition-colors hover:bg-paper"
            style={isActive ? { boxShadow: `inset 0 0 0 1.5px ${stage.color}` } : undefined}
            aria-pressed={isActive}
            title={isActive ? `Showing ${stage.label} only — click to clear` : `Show ${stage.label} only`}
          >
            <span aria-hidden="true" className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ background: stage.color }} />
            <dt className="text-sm text-ink-soft">{stage.label}</dt>
            <dd className="font-mono text-sm font-medium text-ink">{counts[stage.value]}</dd>
          </button>
        )
      })}
    </dl>
  )
}
