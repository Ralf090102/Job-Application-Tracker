import { useState } from 'react'
import { STATUS_OPTIONS } from '../statuses'

const EMPTY_VALUES = {
  company: '',
  role: '',
  status: 'saved', // sensible default for a brand-new entry — see Roadmap.md
  // Phase 2's "open question" note on default status.
  salary_min: '',
  salary_max: '',
  posting_url: '',
  posting_text: '',
  notes: '',
}

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
    <form onSubmit={handleSubmit} noValidate>
      {generalError && <p style={{ color: 'crimson' }}>{generalError}</p>}

      <label>
        Company
        <input value={values.company} onChange={handleChange('company')} />
        {fieldError('company') && <span className="field-error">{fieldError('company')}</span>}
      </label>

      <label>
        Role
        <input value={values.role} onChange={handleChange('role')} />
        {fieldError('role') && <span className="field-error">{fieldError('role')}</span>}
      </label>

      <label>
        Status
        <select value={values.status} onChange={handleChange('status')}>
          {STATUS_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        {fieldError('status') && <span className="field-error">{fieldError('status')}</span>}
      </label>

      <label>
        Salary min
        <input type="number" min="0" value={values.salary_min} onChange={handleChange('salary_min')} />
        {fieldError('salary_min') && <span className="field-error">{fieldError('salary_min')}</span>}
      </label>

      <label>
        Salary max
        <input type="number" min="0" value={values.salary_max} onChange={handleChange('salary_max')} />
        {fieldError('salary_max') && <span className="field-error">{fieldError('salary_max')}</span>}
      </label>

      <label>
        Posting URL
        <input value={values.posting_url} onChange={handleChange('posting_url')} />
        {fieldError('posting_url') && <span className="field-error">{fieldError('posting_url')}</span>}
      </label>

      <label>
        Posting text
        <textarea rows={4} value={values.posting_text} onChange={handleChange('posting_text')} />
      </label>

      <label>
        Notes
        <textarea rows={2} value={values.notes} onChange={handleChange('notes')} />
      </label>

      <div className="form-actions">
        <button type="submit" disabled={submitting}>
          {submitting ? 'Saving…' : submitLabel}
        </button>
        {onCancel && (
          <button type="button" onClick={onCancel} disabled={submitting}>
            Cancel
          </button>
        )}
      </div>
    </form>
  )
}
