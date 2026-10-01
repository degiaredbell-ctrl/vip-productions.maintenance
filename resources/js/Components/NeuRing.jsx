export default function NeuRing({ percentage, size = 118, strokeWidth = 9 }) {
    const radius = (size - strokeWidth * 2) / 2;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (percentage / 100) * circumference;

    return (
        <div className="relative flex-none" style={{ width: size, height: size }}>
            <div className="absolute inset-0 rounded-full shadow-neu-up grid place-items-center">
                <div className="text-center">
                    <span className="text-2xl font-bold block">{percentage}%</span>
                    <span className="text-[11px] text-neu-sub">selesai</span>
                </div>
            </div>
            <svg className="absolute inset-0" width={size} height={size} style={{ transform: 'rotate(-90deg)' }}>
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="#C3CAD6"
                    strokeWidth={strokeWidth}
                    opacity={0.5}
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="#0F6E56"
                    strokeWidth={strokeWidth}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                />
            </svg>
        </div>
    );
}
