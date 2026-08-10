const FIELD =
  'rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink transition-colors ' +
  'focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30'

export const SORT_OPTIONS = [
  { value: 'newest', label: 'Newest first' },
  { value: 'oldest', label: 'Oldest first' },
  { value: 'company', label: 'Company A–Z' },
  { value: 'salary', label: 'Salary: high to low' },
]

export default function JobApplicationFilters({ search, onSearchChange, sortBy, onSortChange }) {
  return (
    <div className="flex flex-wrap items-center gap-3">
      <input
        type="search"
        value={search}
        onChange={(event) => onSearchChange(event.target.value)}
        placeholder="Search company or role…"
        className={`${FIELD} min-w-0 flex-1`}
      />
      <label className="flex items-center gap-2 text-sm text-ink-soft">
        Sort
        <select value={sortBy} onChange={(event) => onSortChange(event.target.value)} className={FIELD}>
          {SORT_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </label>
    </div>
  )
}
