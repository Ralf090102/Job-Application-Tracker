import { STATUS_OPTIONS } from '../statuses'

// The signature element (see the design-plan note in the styling commit):
// not decoration — a live count per pipeline stage is genuinely more
// useful than a flat table for answering "where do things actually
// stand," and it's the same six-color language used everywhere else on
// the page (form dropdown, table badges).
export default function PipelineSummary({ jobApplications }) {
  const counts = Object.fromEntries(STATUS_OPTIONS.map((s) => [s.value, 0]))
  for (const app of jobApplications) {
    counts[app.status] = (counts[app.status] ?? 0) + 1
  }

  return (
    <dl className="flex flex-wrap gap-x-6 gap-y-3 rounded-lg border border-line bg-surface px-5 py-4">
      {STATUS_OPTIONS.map((stage) => (
        <div key={stage.value} className="flex items-center gap-2">
          <span
            aria-hidden="true"
            className="h-2.5 w-2.5 shrink-0 rounded-full"
            style={{ background: stage.color }}
          />
          <dt className="text-sm text-ink-soft">{stage.label}</dt>
          <dd className="font-mono text-sm font-medium text-ink">{counts[stage.value]}</dd>
        </div>
      ))}
    </dl>
  )
}
