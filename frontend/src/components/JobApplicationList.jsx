import JobApplicationRow from './JobApplicationRow'

export default function JobApplicationList({ jobApplications, onEdit, onDelete }) {
  if (jobApplications.length === 0) {
    return <p>No job applications yet — add one above.</p>
  }

  return (
    <table>
      <thead>
        <tr>
          <th>Company</th>
          <th>Role</th>
          <th>Status</th>
          <th>Salary</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        {jobApplications.map((jobApplication) => (
          <JobApplicationRow
            key={jobApplication.id}
            jobApplication={jobApplication}
            onEdit={onEdit}
            onDelete={onDelete}
          />
        ))}
      </tbody>
    </table>
  )
}
