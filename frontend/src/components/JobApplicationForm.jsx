import { useState } from 'react'
import { STATUS_OPTIONS } from '../statuses'

const EMPTY_VALUES = {
  company: '',
  role: '',
  status: 'saved', // "Saved" = actively preparing but not submitted yet — the
  // correct default for a brand-new entry. See Roadmap.md Phase 2.
  salary_min: '',
  salary_max: '',
  posting_url: '',
  posting_text: '',
  notes: '',
}

const LABEL = 'mb-1 block text-sm font-medium text-ink-soft'
const FIELD =
  'w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink transition-colors ' +
  'focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30'
const ERROR = 'mt-1 text-xs text-status-rejected'

function toPayload(values) {
  return {
    company: values.company,
    role: values.role,
    status: values.status,
    salary_min: values.salary_min === '' ? null : Number(values.salary_min),
    salary_max: values.salary_max === '' ? null : Number(values.salary_max),
    posting_url: values.posting_url || null,
    posting_text: values.posting_text || null,
    notes: values.notes || null,
  }
}

// initialValues: null for "create" mode, a JobApplication record for "edit"
// mode. onSubmit receives the built payload and must return a promise —
// rejecting with an error carrying `.errors` (see api/client.js) surfaces
// field-specific 422 messages here instead of just failing silently.
export default function JobApplicationForm({ initialValues, onSubmit, onCancel, submitLabel }) {
  const isEdit = initialValues !== null
  const [values, setValues] = useState(() =>
    isEdit
      ? {
          company: initialValues.company,
          role: initialValues.role,
          status: initialValues.status,
          salary_min: initialValues.salary_min ?? '',
          salary_max: initialValues.salary_max ?? '',
          posting_url: initialValues.posting_url ?? '',
          posting_text: initialValues.posting_text ?? '',
          notes: initialValues.notes ?? '',
        }
      : EMPTY_VALUES
  )
  const [fieldErrors, setFieldErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [generalError, setGeneralError] = useState(null)

  function handleChange(field) {
    return (event) => setValues((v) => ({ ...v, [field]: event.target.value }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setFieldErrors({})
    setGeneralError(null)

    try {
      await onSubmit(toPayload(values))
      if (!isEdit) setValues(EMPTY_VALUES) // clear the form after a successful create
    } catch (error) {
      if (error.status === 422 && error.errors) {
        setFieldErrors(error.errors)
      } else {
        setGeneralError(error.message)
      }
    } finally {
      setSubmitting(false)
    }
  }

  function fieldError(field) {
    return fieldErrors[field]?.[0] ?? null
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      {generalError && (
        <p className="rounded-md border border-status-rejected/30 bg-status-rejected/5 px-3 py-2 text-sm text-status-rejected sm:col-span-2">
          {generalError}
        </p>
      )}

      <label>
        <span className={LABEL}>Company</span>
        <input className={FIELD} value={values.company} onChange={handleChange('company')} />
        {fieldError('company') && <p className={ERROR}>{fieldError('company')}</p>}
      </label>

      <label>
        <span className={LABEL}>Role</span>
        <input className={FIELD} value={values.role} onChange={handleChange('role')} />
        {fieldError('role') && <p className={ERROR}>{fieldError('role')}</p>}
      </label>

      <label>
        <span className={LABEL}>Status</span>
        <select className={FIELD} value={values.status} onChange={handleChange('status')}>
          {STATUS_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        {fieldError('status') && <p className={ERROR}>{fieldError('status')}</p>}
      </label>

      <div className="grid grid-cols-2 gap-4">
        <label>
          <span className={LABEL}>Salary min</span>
          <input
            className={FIELD}
            type="number"
            min="0"
            value={values.salary_min}
            onChange={handleChange('salary_min')}
          />
          {fieldError('salary_min') && <p className={ERROR}>{fieldError('salary_min')}</p>}
        </label>

        <label>
          <span className={LABEL}>Salary max</span>
          <input
            className={FIELD}
            type="number"
            min="0"
            value={values.salary_max}
            onChange={handleChange('salary_max')}
          />
          {fieldError('salary_max') && <p className={ERROR}>{fieldError('salary_max')}</p>}
        </label>
      </div>

      <label className="sm:col-span-2">
        <span className={LABEL}>Posting URL</span>
        <input className={FIELD} value={values.posting_url} onChange={handleChange('posting_url')} />
        {fieldError('posting_url') && <p className={ERROR}>{fieldError('posting_url')}</p>}
      </label>

      <label className="sm:col-span-2">
        <span className={LABEL}>Posting text</span>
        <textarea
          className={`${FIELD} resize-y`}
          rows={4}
          value={values.posting_text}
          onChange={handleChange('posting_text')}
        />
      </label>

      <label className="sm:col-span-2">
        <span className={LABEL}>Notes</span>
        <textarea
          className={`${FIELD} resize-y`}
          rows={2}
          value={values.notes}
          onChange={handleChange('notes')}
        />
      </label>

      <div className="flex items-center gap-3 sm:col-span-2">
        <button
          type="submit"
          disabled={submitting}
          className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50"
        >
          {submitting ? 'Saving…' : submitLabel}
        </button>
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            disabled={submitting}
            className="rounded-md border border-line px-4 py-2 text-sm font-medium text-ink-soft transition-colors hover:bg-paper disabled:opacity-50"
          >
            Cancel
          </button>
        )}
      </div>
    </form>
  )
}
