import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type AlertVariant = 'default' | 'destructive';

const variantClasses: Record<AlertVariant, string> = {
    default: 'bg-layer border-layer-line text-foreground',
    destructive: 'bg-red-100 border-red-200 text-red-800 dark:bg-red-500/20 dark:border-red-900 dark:text-red-400',
};

interface AlertProps extends HTMLAttributes<HTMLDivElement> {
    variant?: AlertVariant;
}

export function Alert({ className, variant = 'default', ...props }: AlertProps) {
    return <div role="alert" className={cn('rounded-lg border p-4 text-sm', variantClasses[variant], className)} {...props} />;
}

export function AlertDescription({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('text-sm', className)} {...props} />;
}
