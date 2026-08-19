export function TrendChart({
    data,
    height = 120,
}: {
    data: { month: string; total: number }[];
    height?: number;
}) {
    const width = 300;
    const padding = 8;
    const max = Math.max(...data.map((item) => item.total), 1);

    const points = data.map((item, index) => {
        const x =
            padding +
            (index * (width - padding * 2)) / Math.max(data.length - 1, 1);
        const y =
            height - padding - (item.total / max) * (height - padding * 2);

        return { x, y, ...item };
    });

    const line = points.map((point) => `${point.x},${point.y}`).join(' ');

    return (
        <div className="w-full overflow-hidden">
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="w-full"
                style={{ height }}
                preserveAspectRatio="none"
            >
                <defs>
                    <linearGradient id="trend-fill" x1="0" y1="0" x2="0" y2="1">
                        <stop
                            offset="0%"
                            stopColor="#10B981"
                            stopOpacity="0.3"
                        />
                        <stop
                            offset="100%"
                            stopColor="#10B981"
                            stopOpacity="0"
                        />
                    </linearGradient>
                    <linearGradient id="trend-line" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stopColor="#34D399" />
                        <stop offset="100%" stopColor="#10B981" />
                    </linearGradient>
                </defs>

                <polygon
                    points={`${padding},${height - padding} ${line} ${width - padding},${height - padding}`}
                    fill="url(#trend-fill)"
                />

                <polyline
                    points={line}
                    fill="none"
                    stroke="url(#trend-line)"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />

                {points.map((point) => (
                    <circle
                        key={point.month}
                        cx={point.x}
                        cy={point.y}
                        r="3"
                        fill="#0B1220"
                        stroke="#10B981"
                        strokeWidth="2"
                    />
                ))}
            </svg>
            <div className="mt-1 flex justify-between px-1">
                {points.map((point) => (
                    <span
                        key={point.month}
                        className="text-[9px] font-medium text-muted-foreground"
                    >
                        {point.month}
                    </span>
                ))}
            </div>
        </div>
    );
}
