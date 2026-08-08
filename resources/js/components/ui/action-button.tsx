import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function ActionButton({
    icon,
    label,
    ariaLabel,
    onClick,
    disabled,
    hoverClassName,
}: {
    icon: ReactNode;
    label: string;
    ariaLabel: string;
    onClick: () => void;
    disabled?: boolean;
    hoverClassName: string;
}) {
    return (
        <div className="hs-tooltip inline-block">
            <button
                type="button"
                disabled={disabled}
                onClick={onClick}
                aria-label={ariaLabel}
                className={cn(
                    'hs-tooltip-toggle flex size-8 items-center justify-center rounded-lg text-muted-foreground-1 hover:bg-layer-hover disabled:pointer-events-none disabled:opacity-50',
                    hoverClassName,
                )}
            >
                {icon}
            </button>
            <span
                className="hs-tooltip-content hs-tooltip-shown:opacity-100 hs-tooltip-shown:visible invisible absolute z-10 rounded-lg border border-tooltip-line bg-tooltip px-2 py-1 text-xs text-tooltip-foreground opacity-0 shadow-sm transition-opacity"
                role="tooltip"
            >
                {label}
            </span>
        </div>
    );
}
