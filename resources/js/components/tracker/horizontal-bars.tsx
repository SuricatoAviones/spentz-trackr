export function HorizontalBars({
    data,
}: {
    data: { name: string; color: string; total: number; percent: number }[];
}) {
    const max = Math.max(...data.map((item) => item.total), 1);

    return (
        <div className="space-y-4">
            {data.map((item) => (
                <div key={item.name}>
                    <div className="mb-1.5 flex items-baseline justify-between gap-2">
                        <span className="text-sm font-medium text-foreground">
                            {item.name}
                        </span>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {item.percent}%
                        </span>
                    </div>
                    <div className="h-2.5 overflow-hidden rounded-full bg-white/5">
                        <div
                            className="h-full rounded-full transition-all duration-700"
                            style={{
                                width: `${(item.total / max) * 100}%`,
                                backgroundColor: item.color,
                            }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}
