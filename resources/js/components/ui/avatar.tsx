import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export function Avatar({ className, ...props }: HTMLAttributes<HTMLSpanElement>) {
    return (
        <span
            className={cn(
                'inline-flex size-9.5 items-center justify-center rounded-full bg-primary text-primary-foreground ring-2 ring-primary/25 ring-offset-2 ring-offset-navbar',
                className,
            )}
            {...props}
        />
    );
}

export function AvatarFallback({ className, ...props }: HTMLAttributes<HTMLSpanElement>) {
    return <span className={cn('text-sm font-semibold', className)} {...props} />;
}
