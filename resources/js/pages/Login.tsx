import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import logo from '../../images/7sight.png';
import { useAuth } from '../lib/auth';
import { getErrorMessages } from '../lib/errors';
import { LogIn } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Login() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setErrors([]);
        setSubmitting(true);
        try {
            await login(email, password);
            navigate('/dashboard');
        } catch (err) {
            setErrors(getErrorMessages(err));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-muted/30 px-4">
            <img src={logo} alt="7Sight Video Intelligence Platform" className="h-25 w-40 object-contain" />
            <h1 className="font-heading text-xl font-semibold text-foreground/80">
                <span className="text-primary">7</span>Sight
            </h1>
            <h3 className="font-heading text-lg font-semibold text-foreground/80 pb-4">Video Intelligence Platform</h3>
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Log in</CardTitle>
                    <CardDescription>Enter your credentials to access your account.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
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
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                            />
                        </div>
                        <Button type="submit" disabled={submitting} className="mt-2 self-center">
                            {submitting ? 'Logging in…' : (
                                <>
                                    Log in
                                    <LogIn className="size-4" strokeWidth={1.75} />
                                </>
                            )}
                        </Button>
                    </form>
                    <p className="mt-4 text-center text-sm text-muted-foreground-1">
                        Don't have an account?{' '}
                        <Link to="/register" className="font-medium text-primary hover:underline">
                            Sign up
                        </Link>
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
