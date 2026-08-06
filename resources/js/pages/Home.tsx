import { Link } from 'react-router-dom';
import { useAuth } from '../lib/auth';
import { buttonVariants } from '@/components/ui/button';

export default function Home() {
    const { user } = useAuth();

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-4 px-4 text-center">
            <h1 className="font-heading text-3xl font-medium">Video Intelligence Platform</h1>
            {user ? (
                <div className="flex flex-col items-center gap-3">
                    <p className="text-sm text-muted-foreground-1">Signed in as {user.name}.</p>
                    <Link to="/dashboard" className={buttonVariants()}>
                        Go to dashboard
                    </Link>
                </div>
            ) : (
                <Link to="/login" className={buttonVariants()}>
                    Log in
                </Link>
            )}
        </div>
    );
}