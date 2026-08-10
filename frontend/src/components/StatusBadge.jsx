import { statusColor, statusLabel } from '../statuses'

export default function StatusBadge({ status }) {
  return (
    <span
      className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
      style={{ background: statusColor(status) }}
    >
      {statusLabel(status)}
    </span>
  )
}
