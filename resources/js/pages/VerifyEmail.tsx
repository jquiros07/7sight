import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/auth';
import { getErrorMessages } from '../lib/errors';
import { api } from '../lib/api';
import { MailCheck, Send } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function VerifyEmail() {
    const { user, logout, refreshUser } = useAuth();
    const navigate = useNavigate();
    const [status, setStatus] = useState<string | null>(null);
    const [errors, setErrors] = useState<string[]>([]);
    const [resending, setResending] = useState(false);
    const [checking, setChecking] = useState(false);

    if (user?.email_verified_at) {
        return <Navigate to="/dashboard" replace />;
    }

    async function handleResend() {
        setStatus(null);
        setErrors([]);
        setResending(true);
        try {
            await api.post('/email/verification-notification');
            setStatus('Verification link sent! Check your inbox.');
        } catch (err) {
            setErrors(getErrorMessages(err));
        } finally {
            setResending(false);
        }
    }

    async function handleCheckVerified() {
        setChecking(true);
        try {
            await refreshUser();
        } finally {
            setChecking(false);
        }
    }

    async function handleLogout() {
        await logout();
        navigate('/');
    }

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-muted/30 px-4">
            <h1 className="font-heading text-3xl font-medium p-5">Video Intelligence Platform</h1>
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Verify your email</CardTitle>
                    <CardDescription>We sent a verification link to {user?.email}. Click it to activate your account.</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {errors.length > 0 && (
                        <Alert variant="destructive" onDismiss={() => setErrors([])}>
                            <AlertDescription>
                                <ul className="list-disc space-y-1 pl-4">
                                    {errors.map((message) => (
                                        <li key={message}>{message}</li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    )}
                    {status && <p className="text-sm text-muted-foreground-1">{status}</p>}
                    <Button onClick={handleCheckVerified} disabled={checking}>
                        {checking ? 'Checking…' : (
                            <>
                                Continue
                                <MailCheck className="size-4" strokeWidth={1.75} />
                            </>
                        )}
                    </Button>
                    <Button variant="secondary" onClick={handleResend} disabled={resending}>
                        {resending ? 'Sending…' : (
                            <>
                                Resend
                                <Send className="size-4" strokeWidth={1.75} />
                            </>
                        )}
                    </Button>
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="text-center text-sm text-muted-foreground-1 hover:underline"
                    >
                        Log out
                    </button>
                </CardContent>
            </Card>
        </div>
    );
}
