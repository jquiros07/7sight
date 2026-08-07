import type { HTMLAttributes } from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

type AlertVariant = 'default' | 'destructive' | 'success';

const variantClasses: Record<AlertVariant, string> = {
    default: 'bg-layer border-layer-line text-foreground',
    destructive: 'bg-red-100 border-red-200 text-red-800 dark:bg-red-500/20 dark:border-red-900 dark:text-red-400',
    success: 'bg-green-100 border-green-200 text-green-800 dark:bg-green-500/20 dark:border-green-900 dark:text-green-400',
};

interface AlertProps extends HTMLAttributes<HTMLDivElement> {
    variant?: AlertVariant;
    onDismiss?: () => void;
}

export function Alert({ className, variant = 'default', onDismiss, children, ...props }: AlertProps) {
    return (
        <div role="alert" className={cn('rounded-lg border p-4 text-sm', variantClasses[variant], className)} {...props}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">{children}</div>
                {onDismiss && (
                    <button
                        type="button"
                        onClick={onDismiss}
                        aria-label="Dismiss"
                        className="shrink-0 rounded-md opacity-70 hover:opacity-100 focus:outline-hidden"
                    >
                        <X className="size-4" strokeWidth={1.75} />
                    </button>
                )}
            </div>
        </div>
    );
}

export function AlertDescription({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('text-sm', className)} {...props} />;
}
