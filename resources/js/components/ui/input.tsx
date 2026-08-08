import { forwardRef, type InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input(
    { className, type = 'text', ...props },
    ref,
) {
    return (
        <input
            ref={ref}
            type={type}
            className={cn(
                'block w-full rounded-lg border-layer-line bg-layer px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground-1 focus:border-primary-focus focus:ring-primary-focus disabled:pointer-events-none disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
});
