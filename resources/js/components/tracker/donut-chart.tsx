export function DonutChart({
    data,
    size = 180,
    thickness = 26,
    centerLabel,
    centerValue,
}: {
    data: { name: string; color: string; total: number }[];
    size?: number;
    thickness?: number;
    centerLabel?: string;
    centerValue?: string;
}) {
    const total = data.reduce((sum, item) => sum + item.total, 0);
    const radius = (size - thickness) / 2;
    const circumference = 2 * Math.PI * radius;

    let offset = 0;

    return (
        <div
            className="relative inline-flex items-center justify-center"
            style={{ width: size, height: size }}
        >
            <svg
                width={size}
                height={size}
                viewBox={`0 0 ${size} ${size}`}
                className="-rotate-90"
            >
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="rgba(255,255,255,0.05)"
                    strokeWidth={thickness}
                />
                {total > 0 &&
                    data.map((item) => {
                        const fraction = item.total / total;
                        const dash = fraction * circumference;
                        const element = (
                            <circle
                                key={item.name}
                                cx={size / 2}
                                cy={size / 2}
                                r={radius}
                                fill="none"
                                stroke={item.color}
                                strokeWidth={thickness}
                                strokeDasharray={`${dash} ${circumference - dash}`}
                                strokeDashoffset={-offset}
                                strokeLinecap="butt"
                            />
                        );
                        offset += dash;

                        return element;
                    })}
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="font-display text-2xl font-extrabold text-foreground tabular-nums">
                    {centerValue}
                </span>
                {centerLabel !== undefined && (
                    <span className="text-[11px] font-medium text-muted-foreground">
                        {centerLabel}
                    </span>
                )}
            </div>
        </div>
    );
}
