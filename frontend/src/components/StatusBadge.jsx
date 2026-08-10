import { statusColor, statusLabel } from '../statuses'

export default function StatusBadge({ status }) {
  return (
    <span
      style={{
        display: 'inline-block',
        padding: '2px 10px',
        borderRadius: '999px',
        fontSize: '0.85em',
        color: 'white',
        background: statusColor(status),
      }}
    >
      {statusLabel(status)}
    </span>
  )
}
