import { Camera, Folder, LayoutDashboard, Menu, Search, Video } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import logo from '../../images/7sight.png';
import { useAuth } from '../lib/auth';
import { cn } from '../lib/utils';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type NavKey = 'dashboard' | 'videos' | 'search' | 'cameras' | 'workspaces' | 'profile';

function initials(name: string) {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function SidebarItem({
    icon,
    label,
    active,
    soon,
    href,
}: {
    icon: ReactNode;
    label: string;
    active?: boolean;
    soon?: boolean;
    href?: string;
}) {
    const className = cn(
        'flex items-center gap-3 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium',
        active
            ? 'bg-primary/10 text-primary'
            : soon
              ? 'cursor-not-allowed text-muted-foreground-1'
              : 'text-foreground hover:bg-layer-hover',
    );
    const content = (
        <>
            {icon}
            {label}
            {soon && <span className="ms-auto text-xs text-muted-foreground-2">soon</span>}
        </>
    );

    if (!href || soon) {
        return <span className={className}>{content}</span>;
    }

    return (
        <Link to={href} className={className}>
            {content}
        </Link>
    );
}

const iconProps = { className: 'size-5 shrink-0', strokeWidth: 1.75 };

const NAV_ITEMS: { key: NavKey; label: string; icon: ReactNode; href?: string; soon?: boolean }[] = [
    { key: 'dashboard', label: 'Dashboard', icon: <LayoutDashboard {...iconProps} />, href: '/dashboard' },
    { key: 'videos', label: 'Videos', icon: <Video {...iconProps} />, href: '/videos' },
    { key: 'search', label: 'AI Search', icon: <Search {...iconProps} />, href: '/search' },
    { key: 'cameras', label: 'Cameras', icon: <Camera {...iconProps} />, href: '/cameras' },
    { key: 'workspaces', label: 'Workspaces', icon: <Folder {...iconProps} />, href: '/workspaces' },
];

export function AppLayout({ active, children }: { active: NavKey; children: ReactNode }) {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const [sidebarOpen, setSidebarOpen] = useState(true);

    // RequireAuth renders null while the auth check is in flight, so on a hard
    // refresh this component (and its .hs-dropdown) doesn't exist yet when
    // Router's pathname-based autoInit() runs. Re-init here once we actually mount.
    useEffect(() => {
        window.HSStaticMethods.autoInit();
    }, []);

    async function handleLogout() {
        await logout();
        navigate('/');
    }

    return (
        <div className="flex min-h-screen bg-muted/30">
            <aside
                className={cn(
                    'flex shrink-0 flex-col overflow-hidden border-r border-navbar-line bg-navbar transition-all duration-200',
                    sidebarOpen ? 'w-60' : 'w-0',
                )}
            >
                <div className="flex items-center gap-2 px-4 py-4">
                    <img src={logo} alt="7Sight Video Intelligence Platform" className="size-7 shrink-0 object-contain" />
                    <span className="font-heading text-sm font-bold text-foreground/80 whitespace-nowrap">
                        <span className="text-primary">7</span>Sight
                    </span>
                </div>
                <nav className="flex flex-col gap-1 px-3 py-2">
                    {NAV_ITEMS.map((item) => (
                        <SidebarItem
                            key={item.key}
                            icon={item.icon}
                            label={item.label}
                            active={item.key === active}
                            soon={item.soon}
                            href={item.href}
                        />
                    ))}
                </nav>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex items-center justify-between border-b border-navbar-line bg-navbar px-4 py-3">
                    <button
                        type="button"
                        onClick={() => setSidebarOpen((open) => !open)}
                        className="flex size-9 items-center justify-center rounded-lg text-muted-foreground-1 hover:bg-layer-hover hover:text-foreground"
                        aria-label={sidebarOpen ? 'Hide menu' : 'Show menu'}
                    >
                        <Menu className="size-5" strokeWidth={1.75} />
                    </button>
                    <DropdownMenu>
                        <DropdownMenuTrigger className="outline-none">
                            <Avatar>
                                <AvatarFallback>{user ? initials(user.name) : '?'}</AvatarFallback>
                            </Avatar>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuGroup>
                                <DropdownMenuLabel>{user?.name}</DropdownMenuLabel>
                            </DropdownMenuGroup>
                            <DropdownMenuGroup>
                                <DropdownMenuItem onClick={() => navigate('/profile')}>Profile settings</DropdownMenuItem>
                            </DropdownMenuGroup>
                            <DropdownMenuGroup>
                                <DropdownMenuItem variant="destructive" onClick={handleLogout}>
                                    Log out
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </header>
                <main className="p-6">{children}</main>
            </div>
        </div>
    );
}
