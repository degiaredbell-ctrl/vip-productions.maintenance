import { forwardRef } from 'react';

const NeuInput = forwardRef(({ className = '', ...props }, ref) => {
    return (
        <input
            ref={ref}
            className={`neu-input ${className}`}
            {...props}
        />
    );
});

NeuInput.displayName = 'NeuInput';

export default NeuInput;
