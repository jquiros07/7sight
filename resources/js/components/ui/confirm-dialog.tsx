import type { ReactNode } from 'react';
import { X } from 'lucide-react';
import { Button, buttonVariants } from '@/components/ui/button';

interface ConfirmDialogProps {
    id: string;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    confirmIcon?: ReactNode;
    variant?: 'destructive' | 'primary';
    confirmDisabled?: boolean;
    onConfirm: () => void;
}

export function ConfirmDialog({
    id,
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    confirmIcon,
    variant = 'destructive',
    confirmDisabled = false,
    onConfirm,
}: ConfirmDialogProps) {
    const labelId = `${id}-label`;

    return (
        <div
            id={id}
            className="hs-overlay pointer-events-none fixed top-0 start-0 z-[60] hidden size-full overflow-x-hidden overflow-y-auto"
            role="dialog"
            tabIndex={-1}
            aria-labelledby={labelId}
        >
            <div className="hs-overlay-animation-target m-3 flex min-h-[calc(100%-56px)] scale-95 items-center opacity-0 transition-all duration-200 ease-in-out hs-overlay-open:scale-100 hs-overlay-open:opacity-100 sm:mx-auto sm:w-full sm:max-w-lg">
                <div className="flex w-full flex-col rounded-xl border border-overlay-line bg-overlay pointer-events-auto shadow-2xs">
                    <div className="flex items-center justify-between border-b border-overlay-header px-4 py-3">
                        <h3 id={labelId} className="font-heading font-semibold text-foreground">
                            {title}
                        </h3>
                        <button
                            type="button"
                            className="flex size-8 items-center justify-center gap-x-2 rounded-full border border-surface-line bg-surface text-surface-foreground hover:bg-surface-hover focus:bg-surface-focus focus:outline-hidden disabled:pointer-events-none disabled:opacity-50"
                            aria-label="Close"
                            data-hs-overlay={`#${id}`}
                        >
                            <span className="sr-only">Close</span>
                            <X className="size-4 shrink-0" strokeWidth={1.75} />
                        </button>
                    </div>
                    <div className="overflow-y-auto p-4">
                        <p className="text-sm text-muted-foreground-1">{description}</p>
                    </div>
                    <div className="flex items-center justify-end gap-x-2 border-t border-overlay-footer px-4 py-3">
                        <button type="button" className={buttonVariants('secondary')} data-hs-overlay={`#${id}`}>
                            {cancelLabel}
                        </button>
                        <Button type="button" variant={variant} onClick={onConfirm} disabled={confirmDisabled}>
                            {confirmLabel}
                            {confirmIcon}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}