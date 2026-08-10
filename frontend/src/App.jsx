import { useEffect, useState } from 'react'
import {
  createJobApplication,
  deleteJobApplication,
  listJobApplications,
  updateJobApplication,
} from './api/jobApplications'
import JobApplicationForm from './components/JobApplicationForm'
import JobApplicationList from './components/JobApplicationList'
import './App.css'

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
    <main>
      <h1>Job Application Tracker</h1>

      <section>
        <h2>{editingApplication ? `Edit: ${editingApplication.company}` : 'Add a job application'}</h2>
        <JobApplicationForm
          key={editingApplication?.id ?? 'create'} // remounts the form (fresh state) when switching targets
          initialValues={editingApplication}
          onSubmit={editingApplication ? handleUpdate : handleCreate}
          onCancel={editingApplication ? () => setEditingApplication(null) : null}
          submitLabel={editingApplication ? 'Save changes' : 'Add'}
        />
      </section>

      <section>
        <h2>Applications</h2>
        {loading && <p>Loading…</p>}
        {loadError && <p style={{ color: 'crimson' }}>Failed to load: {loadError}</p>}
        {!loading && !loadError && (
          <JobApplicationList
            jobApplications={jobApplications}
            onEdit={setEditingApplication}
            onDelete={handleDelete}
          />
        )}
      </section>
    </main>
  )
}

export default App
