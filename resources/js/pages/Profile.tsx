import { FormEvent, useState } from 'react';
import { AppLayout } from '@/components/AppLayout';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { getErrorMessages } from '../lib/errors';
import { KeyRound, Save } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Profile() {
    const { user, refreshUser } = useAuth();

    const [name, setName] = useState(user?.name ?? '');
    const [email, setEmail] = useState(user?.email ?? '');
    const [profileErrors, setProfileErrors] = useState<string[]>([]);
    const [profileStatus, setProfileStatus] = useState<string | null>(null);
    const [savingProfile, setSavingProfile] = useState(false);

    const [currentPassword, setCurrentPassword] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [passwordErrors, setPasswordErrors] = useState<string[]>([]);
    const [passwordStatus, setPasswordStatus] = useState<string | null>(null);
    const [savingPassword, setSavingPassword] = useState(false);

    async function handleProfileSubmit(e: FormEvent) {
        e.preventDefault();
        setProfileErrors([]);
        setProfileStatus(null);
        setSavingProfile(true);
        const emailChanged = email !== user?.email;
        try {
            await api.put('/user/profile-information', { name, email });
            await refreshUser();
            setProfileStatus(
                emailChanged
                    ? 'Profile updated. Since you changed your email, please verify it again — check your inbox.'
                    : 'Profile updated.',
            );
        } catch (err) {
            setProfileErrors(getErrorMessages(err));
        } finally {
            setSavingProfile(false);
        }
    }

    async function handlePasswordSubmit(e: FormEvent) {
        e.preventDefault();
        setPasswordErrors([]);
        setPasswordStatus(null);
        setSavingPassword(true);
        try {
            await api.put('/user/password', {
                current_password: currentPassword,
                password,
                password_confirmation: passwordConfirmation,
            });
            setCurrentPassword('');
            setPassword('');
            setPasswordConfirmation('');
            setPasswordStatus('Password updated.');
        } catch (err) {
            setPasswordErrors(getErrorMessages(err));
        } finally {
            setSavingPassword(false);
        }
    }

    return (
        <AppLayout active="profile">
            <h1 className="font-heading text-2xl font-medium">Profile settings</h1>

            <div className="mt-6 flex flex-col gap-6 lg:max-w-lg">
                <Card>
                    <CardHeader>
                        <CardTitle>Profile information</CardTitle>
                        <CardDescription>Update your name and email address.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleProfileSubmit} noValidate className="flex flex-col gap-4">
                            {profileErrors.length > 0 && (
                                <Alert variant="destructive" onDismiss={() => setProfileErrors([])}>
                                    <AlertDescription>
                                        <ul className="list-disc space-y-1 pl-4">
                                            {profileErrors.map((message) => (
                                                <li key={message}>{message}</li>
                                            ))}
                                        </ul>
                                    </AlertDescription>
                                </Alert>
                            )}
                            {profileStatus && (
                                <Alert variant="success" onDismiss={() => setProfileStatus(null)}>
                                    <AlertDescription>{profileStatus}</AlertDescription>
                                </Alert>
                            )}
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="profile-name">Name</Label>
                                <Input id="profile-name" value={name} onChange={(e) => setName(e.target.value)} />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="profile-email">Email</Label>
                                <Input
                                    id="profile-email"
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                />
                            </div>
                            <Button type="submit" disabled={savingProfile} className="self-center">
                                {savingProfile ? 'Saving…' : (
                                    <>
                                        Save
                                        <Save className="size-4" strokeWidth={1.75} />
                                    </>
                                )}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Password</CardTitle>
                        <CardDescription>Update your account password.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handlePasswordSubmit} noValidate className="flex flex-col gap-4">
                            {passwordErrors.length > 0 && (
                                <Alert variant="destructive" onDismiss={() => setPasswordErrors([])}>
                                    <AlertDescription>
                                        <ul className="list-disc space-y-1 pl-4">
                                            {passwordErrors.map((message) => (
                                                <li key={message}>{message}</li>
                                            ))}
                                        </ul>
                                    </AlertDescription>
                                </Alert>
                            )}
                            {passwordStatus && (
                                <Alert variant="success" onDismiss={() => setPasswordStatus(null)}>
                                    <AlertDescription>{passwordStatus}</AlertDescription>
                                </Alert>
                            )}
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="current-password">Current password</Label>
                                <Input
                                    id="current-password"
                                    type="password"
                                    value={currentPassword}
                                    onChange={(e) => setCurrentPassword(e.target.value)}
                                />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="new-password">New password</Label>
                                <Input
                                    id="new-password"
                                    type="password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="new-password-confirmation">Confirm new password</Label>
                                <Input
                                    id="new-password-confirmation"
                                    type="password"
                                    value={passwordConfirmation}
                                    onChange={(e) => setPasswordConfirmation(e.target.value)}
                                />
                            </div>
                            <Button type="submit" disabled={savingPassword} className="self-center">
                                {savingPassword ? 'Saving…' : (
                                    <>
                                        Update
                                        <KeyRound className="size-4" strokeWidth={1.75} />
                                    </>
                                )}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
