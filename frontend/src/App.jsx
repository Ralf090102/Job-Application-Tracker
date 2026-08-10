import { useEffect, useState } from 'react'
import {
  createJobApplication,
  deleteJobApplication,
  listJobApplications,
  updateJobApplication,
} from './api/jobApplications'
import ExtractPostingCard from './components/ExtractPostingCard'
import JobApplicationForm from './components/JobApplicationForm'
import JobApplicationList from './components/JobApplicationList'
import PipelineSummary from './components/PipelineSummary'

function App() {
  const [jobApplications, setJobApplications] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [editingApplication, setEditingApplication] = useState(null) // null = create mode
  const [draftValues, setDraftValues] = useState(null) // Phase 5 extraction result, create mode only
  const [draftNonce, setDraftNonce] = useState(0) // forces the form to remount when a new draft arrives

  useEffect(() => {
    listJobApplications()
      .then(setJobApplications)
      .catch((error) => setLoadError(error.message))
      .finally(() => setLoading(false))
  }, [])

  function handleCreate(payload) {
    return createJobApplication(payload).then((created) => {
      setJobApplications((list) => [created, ...list])
      setDraftValues(null) // next blank "Add" should start empty, not re-prefill
    })
  }

  function handleUpdate(payload) {
    return updateJobApplication(editingApplication.id, payload).then((updated) => {
      setJobApplications((list) => list.map((app) => (app.id === updated.id ? updated : app)))
      setEditingApplication(null)
    })
  }

  function handleDelete(id) {
    return deleteJobApplication(id).then(() => {
      setJobApplications((list) => list.filter((app) => app.id !== id))
    })
  }

  function handleExtracted(extracted) {
    setEditingApplication(null) // an extraction always targets a new record
    setDraftValues(extracted)
    setDraftNonce((n) => n + 1)
  }

  function handleEdit(jobApplication) {
    setDraftValues(null) // editing an existing record takes priority over a pending draft
    setEditingApplication(jobApplication)
  }

  return (
    <main className="mx-auto max-w-3xl px-6 py-12">
      <h1 className="font-display text-4xl font-medium tracking-tight text-ink">Job Application Tracker</h1>

      <section className="mt-10 rounded-lg border border-line bg-surface p-6">
        <h2 className="font-display text-xl font-medium text-ink">Paste a job posting</h2>
        <p className="mt-1 text-sm text-ink-soft">
          Runs locally via Ollama — extracts fields and flags concerns for you to review below before saving.
        </p>
        <div className="mt-4">
          <ExtractPostingCard onExtracted={handleExtracted} />
        </div>
      </section>

      <section className="mt-10 rounded-lg border border-line bg-surface p-6">
        <h2 className="font-display text-xl font-medium text-ink">
          {editingApplication
            ? `Edit: ${editingApplication.company}`
            : draftValues
              ? 'Review extracted application'
              : 'Add a job application'}
        </h2>
        <div className="mt-4">
          <JobApplicationForm
            // remounts the form (fresh state) when switching targets or when a new draft arrives
            key={editingApplication?.id ?? (draftValues ? `draft-${draftNonce}` : 'create')}
            initialValues={editingApplication}
            draftValues={draftValues}
            onSubmit={editingApplication ? handleUpdate : handleCreate}
            onCancel={editingApplication ? () => setEditingApplication(null) : null}
            submitLabel={editingApplication ? 'Save changes' : 'Add application'}
          />
        </div>
      </section>

      <section className="mt-10">
        <h2 className="font-display text-xl font-medium text-ink">Applications</h2>

        {loading && <p className="mt-4 text-sm text-ink-soft">Loading…</p>}
        {loadError && (
          <p className="mt-4 rounded-md border border-status-rejected/30 bg-status-rejected/5 px-3 py-2 text-sm text-status-rejected">
            Failed to load: {loadError}
          </p>
        )}

        {!loading && !loadError && (
          <div className="mt-4 space-y-4">
            <PipelineSummary jobApplications={jobApplications} />
            <JobApplicationList
              jobApplications={jobApplications}
              onEdit={handleEdit}
              onDelete={handleDelete}
            />
          </div>
        )}
      </section>
    </main>
  )
}

export default App
