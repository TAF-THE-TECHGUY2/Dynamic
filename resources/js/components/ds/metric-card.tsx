/**
 * A dashboard metric card. The value is always mono — every number in this
 * console is, which is what makes it read like a fuel depot.
 */
export default function MetricCard({
    label,
    value,
    delta,
    detail,
    detailTone = 'flat',
    previousLabel,
}: {
    label: string;
    value: string;
    delta?: { percent: number; direction: 'up' | 'dn' | 'flat' } | null;
    detail?: string;
    detailTone?: 'up' | 'dn' | 'flat';
    previousLabel?: string;
}) {
    return (
        <div className="ds-card">
            <div className="k">{label}</div>
            <div className="v">{value}</div>

            {delta ? (
                <div className={`d ${delta.direction}`}>
                    {delta.direction === 'up'
                        ? '▲'
                        : delta.direction === 'dn'
                          ? '▼'
                          : '■'}{' '}
                    {delta.percent}%
                    {previousLabel ? ` vs ${previousLabel}` : ''}
                </div>
            ) : detail ? (
                <div className={`d ${detailTone}`}>{detail}</div>
            ) : (
                <div className="d flat">
                    {previousLabel
                        ? `No ${previousLabel} figure to compare`
                        : '—'}
                </div>
            )}
        </div>
    );
}
