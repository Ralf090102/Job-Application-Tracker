import { useState } from 'react'
import { extractJobPosting } from '../api/jobApplications'

// Roadmap.md Phase 5: paste raw posting text -> local-model extraction ->
// caller (App.jsx) prefills the create form with the result for review.
// Never saves anything itself. Local inference ran 20-50s in testing, so
// the pasted text is deliberately kept in local state on failure instead
// of being cleared — losing what someone just pasted in would be a real
// paper cut on a call this slow.
export default function ExtractPostingCard({ onExtracted }) {
  const [postingText, setPostingText] = useState('')
  const [extracting, setExtracting] = useState(false)
  const [error, setError] = useState(null)

  async function handleExtract(event) {
    event.preventDefault()
    setExtracting(true)
    setError(null)

    try {
      const extracted = await extractJobPosting(postingText)
      onExtracted(extracted)
    } catch (err) {
      setError(err.message)
    } finally {
      setExtracting(false)
    }
  }

  return (
    <form onSubmit={handleExtract}>
      {error && (
        <p className="mb-3 rounded-md border border-status-rejected/30 bg-status-rejected/5 px-3 py-2 text-sm text-status-rejected">
          {error}
        </p>
      )}

      <label className="block">
        <span className="mb-1 block text-sm font-medium text-ink-soft">Paste a job posting</span>
        <textarea
          className="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink transition-colors focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
          rows={6}
          value={postingText}
          onChange={(event) => setPostingText(event.target.value)}
          placeholder="Paste the full job posting text here — company, role, requirements, salary, everything."
        />
      </label>

      <div className="mt-3 flex items-center gap-3">
        <button
          type="submit"
          disabled={extracting || postingText.trim().length < 20}
          className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50"
        >
          {extracting ? 'Extracting…' : 'Extract fields'}
        </button>
        {extracting && (
          <span className="text-sm text-ink-soft">
            Running locally — this can take up to a minute.
          </span>
        )}
      </div>
    </form>
  )
}
