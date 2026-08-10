import JobApplicationRow from './JobApplicationRow'

const TH = 'px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-ink-soft'

export default function JobApplicationList({
  jobApplications,
  onEdit,
  onDelete,
  emptyMessage = 'No job applications yet — add one above.',
}) {
  if (jobApplications.length === 0) {
    return (
      <p className="rounded-lg border border-dashed border-line px-4 py-8 text-center text-sm text-ink-soft">
        {emptyMessage}
      </p>
    )
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-line bg-surface">
      <table className="w-full min-w-[640px] border-collapse text-sm">
        <thead>
          <tr className="border-b border-line">
            <th className={TH}>Company</th>
            <th className={TH}>Role</th>
            <th className={TH}>Status</th>
            <th className={TH}>Salary</th>
            <th className={TH}>Location</th>
            <th className={TH}>Actions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-line">
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
    </div>
  )
}
