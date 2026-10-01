export default function NeuBars({ data, className = '' }) {
    const max = Math.max(...data.map(d => d.value), 1);

    return (
        <div className={`flex gap-2.5 h-44 ${className}`}>
            {data.map((d, i) => (
                <div key={i} className="flex-1 flex flex-col items-center gap-2">
                    <span className="text-[11px] text-neu-sub">{d.value}</span>
                    <div className="w-full flex-1 rounded-xl shadow-neu-in flex items-end p-1">
                        <div
                            className="w-full rounded-lg bg-neu-accent"
                            style={{ height: `${Math.max((d.value / max) * 100, 2)}%` }}
                        />
                    </div>
                    <span className="text-[11px] text-neu-sub">{d.label}</span>
                </div>
            ))}
        </div>
    );
}
