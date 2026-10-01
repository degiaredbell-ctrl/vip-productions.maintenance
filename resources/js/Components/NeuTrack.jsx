export default function NeuTrack({ percentage, className = '' }) {
    return (
        <div className={`h-3.5 rounded-lg shadow-neu-in p-[3px] ${className}`}>
            <div
                className="h-full rounded-md bg-neu-accent transition-all duration-300"
                style={{ width: `${percentage}%` }}
            />
        </div>
    );
}
