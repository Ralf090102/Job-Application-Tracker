import { useEffect, useState } from 'react'
import {
  createJobApplication,
  deleteJobApplication,
  listJobApplications,
  updateJobApplication,
} from './api/jobApplications'
import JobApplicationForm from './components/JobApplicationForm'
import JobApplicationList from './components/JobApplicationList'
import PipelineSummary from './components/PipelineSummary'

function App() {
  const [jobApplications, setJobApplications] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [editingApplication, setEditingApplication] = useState(null) // null = create mode

  useEffect(() => {
    listJobApplications()
      .then(setJobApplications)
      .catch((error) => setLoadError(error.message))
      .finally(() => setLoading(false))
  }, [])

  function handleCreate(payload) {
    return createJobApplication(payload).then((created) => {
      setJobApplications((list) => [created, ...list])
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

  return (
    <main className="mx-auto max-w-3xl px-6 py-12">
      <h1 className="font-display text-4xl font-medium tracking-tight text-ink">Job Application Tracker</h1>

      <section className="mt-10 rounded-lg border border-line bg-surface p-6">
        <h2 className="font-display text-xl font-medium text-ink">
          {editingApplication ? `Edit: ${editingApplication.company}` : 'Add a job application'}
        </h2>
        <div className="mt-4">
          <JobApplicationForm
            key={editingApplication?.id ?? 'create'} // remounts the form (fresh state) when switching targets
            initialValues={editingApplication}
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
              onEdit={setEditingApplication}
              onDelete={handleDelete}
            />
          </div>
        )}
      </section>
    </main>
  )
}

export default App
