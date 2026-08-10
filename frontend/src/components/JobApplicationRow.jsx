import { useState } from 'react'
import StatusBadge from './StatusBadge'

const BUTTON = 'rounded-md border px-2.5 py-1 text-xs font-medium transition-colors disabled:opacity-50'
const BUTTON_NEUTRAL = `${BUTTON} border-line text-ink-soft hover:bg-paper`
const BUTTON_DANGER = `${BUTTON} border-status-rejected bg-status-rejected text-white hover:opacity-90`

// Custom two-step confirm instead of window.confirm() — a native confirm()
// blocks the whole page (and, not incidentally, blocks browser-automation
// testing tools too), which is worse UX than a plain inline toggle.
export default function JobApplicationRow({ jobApplication, onEdit, onDelete }) {
  const [confirming, setConfirming] = useState(false)
  const [deleting, setDeleting] = useState(false)

  async function handleConfirmDelete() {
    setDeleting(true)
    try {
      await onDelete(jobApplication.id)
    } finally {
      setDeleting(false)
      setConfirming(false)
    }
  }

  return (
    <tr className="transition-colors hover:bg-paper/60">
      <td className="px-4 py-3 font-medium text-ink">{jobApplication.company}</td>
      <td className="px-4 py-3 text-ink-soft">{jobApplication.role}</td>
      <td className="px-4 py-3">
        <StatusBadge status={jobApplication.status} />
      </td>
      <td className="px-4 py-3 font-mono text-ink-soft">
        {jobApplication.salary_min && jobApplication.salary_max
          ? `${jobApplication.salary_min.toLocaleString()} – ${jobApplication.salary_max.toLocaleString()}`
          : '—'}
      </td>
      <td className="px-4 py-3">
        <div className="flex items-center gap-2">
          <button type="button" className={BUTTON_NEUTRAL} onClick={() => onEdit(jobApplication)} disabled={confirming}>
            Edit
          </button>
          {confirming ? (
            <>
              <button type="button" className={BUTTON_DANGER} onClick={handleConfirmDelete} disabled={deleting}>
                {deleting ? 'Deleting…' : 'Confirm delete?'}
              </button>
              <button
                type="button"
                className={BUTTON_NEUTRAL}
                onClick={() => setConfirming(false)}
                disabled={deleting}
              >
                Cancel
              </button>
            </>
          ) : (
            <button type="button" className={BUTTON_NEUTRAL} onClick={() => setConfirming(true)}>
              Delete
            </button>
          )}
        </div>
      </td>
    </tr>
  )
}
