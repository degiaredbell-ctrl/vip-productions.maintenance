export default function NeuCard({ children, className = '', ...props }) {
    return (
        <div className={`neu-card ${className}`} {...props}>
            {children}
        </div>
    );
}
