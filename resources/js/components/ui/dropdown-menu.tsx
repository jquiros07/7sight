import { createContext, useContext, useId, type ButtonHTMLAttributes, type HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const DropdownIdContext = createContext<string | undefined>(undefined);

export function DropdownMenu({ className, children, ...props }: HTMLAttributes<HTMLDivElement>) {
    const id = useId();
    return (
        <DropdownIdContext.Provider value={id}>
            <div className={cn('hs-dropdown relative inline-flex', className)} {...props}>
                {children}
            </div>
        </DropdownIdContext.Provider>
    );
}

export function DropdownMenuTrigger({ className, ...props }: ButtonHTMLAttributes<HTMLButtonElement>) {
    const id = useContext(DropdownIdContext);
    return (
        <button
            id={id}
            type="button"
            aria-haspopup="menu"
            aria-expanded="false"
            className={cn('hs-dropdown-toggle', className)}
            {...props}
        />
    );
}

interface DropdownMenuContentProps extends HTMLAttributes<HTMLDivElement> {
    align?: 'start' | 'end';
}

export function DropdownMenuContent({ align = 'start', className, ...props }: DropdownMenuContentProps) {
    const id = useContext(DropdownIdContext);
    return (
        <div
            role="menu"
            aria-orientation="vertical"
            aria-labelledby={id}
            className={cn(
                'hs-dropdown-menu absolute z-20 duration mt-2 hidden min-w-56 divide-y divide-dropdown-divider rounded-lg border border-dropdown-line bg-dropdown opacity-0 shadow-md transition-[opacity,margin] hs-dropdown-open:opacity-100',
                align === 'end' ? 'end-0' : 'start-0',
                className,
            )}
            {...props}
        />
    );
}

export function DropdownMenuGroup({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('space-y-0.5 p-1', className)} {...props} />;
}

export function DropdownMenuLabel({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('px-3 py-2 text-sm font-medium text-foreground', className)} {...props} />;
}

interface DropdownMenuItemProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'default' | 'destructive';
}

const itemVariantClasses = {
    default: 'text-dropdown-item-foreground hover:bg-dropdown-item-hover focus:bg-dropdown-item-focus',
    destructive: 'text-destructive hover:bg-destructive/10 focus:bg-destructive/10',
};

export function DropdownMenuItem({ className, variant = 'default', ...props }: DropdownMenuItemProps) {
    return (
        <button
            type="button"
            role="menuitem"
            className={cn('flex w-full items-center gap-x-3.5 rounded-lg px-3 py-2 text-sm', itemVariantClasses[variant], className)}
            {...props}
        />
    );
}
