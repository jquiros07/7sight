import { forwardRef, type ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type ButtonVariant = 'primary' | 'secondary' | 'destructive';

const variantClasses: Record<ButtonVariant, string> = {
    primary: 'bg-primary border-primary-line text-primary-foreground hover:bg-primary-hover focus:bg-primary-focus',
    secondary: 'bg-secondary border-secondary-line text-secondary-foreground hover:bg-secondary-hover focus:bg-secondary-focus',
    destructive: 'bg-destructive border-transparent text-destructive-foreground hover:bg-destructive-hover focus:bg-destructive-focus',
};

export function buttonVariants(variant: ButtonVariant = 'primary', className?: string) {
    return cn(
        'w-fit py-1.5 px-3 inline-flex items-center justify-center gap-x-2 text-sm font-medium rounded-lg border disabled:opacity-50 disabled:pointer-events-none',
        variantClasses[variant],
        className,
    );
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
    { className, variant = 'primary', type = 'button', ...props },
    ref,
) {
    return <button ref={ref} type={type} className={buttonVariants(variant, className)} {...props} />;
});
