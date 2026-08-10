import { useState } from 'react'
import StatusBadge from './StatusBadge'

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
    <tr>
      <td>{jobApplication.company}</td>
      <td>{jobApplication.role}</td>
      <td>
        <StatusBadge status={jobApplication.status} />
      </td>
      <td>
        {jobApplication.salary_min && jobApplication.salary_max
          ? `${jobApplication.salary_min.toLocaleString()} - ${jobApplication.salary_max.toLocaleString()}`
          : '—'}
      </td>
      <td>
        <button type="button" onClick={() => onEdit(jobApplication)} disabled={confirming}>
          Edit
        </button>{' '}
        {confirming ? (
          <>
            <button type="button" onClick={handleConfirmDelete} disabled={deleting}>
              {deleting ? 'Deleting…' : 'Confirm delete?'}
            </button>{' '}
            <button type="button" onClick={() => setConfirming(false)} disabled={deleting}>
              Cancel
            </button>
          </>
        ) : (
          <button type="button" onClick={() => setConfirming(true)}>
            Delete
          </button>
        )}
      </td>
    </tr>
  )
}
