export default function NeuCard({ children, className = '' }) {
    return (
        <div className={`neu-card ${className}`}>
            {children}
        </div>
    );
}
