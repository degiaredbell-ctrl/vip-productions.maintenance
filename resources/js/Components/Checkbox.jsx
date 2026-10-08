export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded-neu-sm border-neu-dark/40 bg-neu-bg text-neu-accent shadow-neu-in focus:ring-neu-accent/40 ' +
                className
            }
        />
    );
}
