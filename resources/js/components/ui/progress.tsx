import { cn } from '@/lib/utils';

interface ProgressProps {
    value: number;
    className?: string;
}

export function Progress({ value, className }: ProgressProps) {
    const clamped = Math.min(100, Math.max(0, value));

    return (
        <div
            role="progressbar"
            aria-valuenow={clamped}
            aria-valuemin={0}
            aria-valuemax={100}
            className={cn('h-2 w-full overflow-hidden rounded-full bg-surface-1', className)}
        >
            <div className="h-full rounded-full bg-primary transition-[width] duration-500 ease-out" style={{ width: `${clamped}%` }} />
        </div>
    );
}
