import { lazy, Suspense, useEffect } from 'react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import { RequireAuth } from './lib/RequireAuth';

const Dashboard = lazy(() => import('./pages/Dashboard'));
const Home = lazy(() => import('./pages/Home'));
const Login = lazy(() => import('./pages/Login'));
const Profile = lazy(() => import('./pages/Profile'));
const Register = lazy(() => import('./pages/Register'));
const VerifyEmail = lazy(() => import('./pages/VerifyEmail'));
const VideoEdit = lazy(() => import('./pages/VideoEdit'));
const VideoResults = lazy(() => import('./pages/VideoResults'));
const VideoSearch = lazy(() => import('./pages/VideoSearch'));
const VideoUpload = lazy(() => import('./pages/VideoUpload'));
const Videos = lazy(() => import('./pages/Videos'));
const WorkspaceCreate = lazy(() => import('./pages/WorkspaceCreate'));
const WorkspaceDashboard = lazy(() => import('./pages/WorkspaceDashboard'));
const WorkspaceEdit = lazy(() => import('./pages/WorkspaceEdit'));
const WorkspaceMembers = lazy(() => import('./pages/WorkspaceMembers'));
const Workspaces = lazy(() => import('./pages/Workspaces'));

function RouteFallback() {
    return (
        <div className="flex min-h-screen items-center justify-center">
            <Loader2 className="size-6 animate-spin text-primary" strokeWidth={1.75} />
        </div>
    );
}

export default function Router() {
    const location = useLocation();

    useEffect(() => {
        window.HSStaticMethods.autoInit();
    }, [location.pathname]);

    return (
        <Suspense fallback={<RouteFallback />}>
            <Routes>
                <Route path="/" element={<Login />} />
                <Route path="/login" element={<Login />} />
                <Route path="/register" element={<Register />} />
                <Route
                    path="/verify-email"
                    element={
                        <RequireAuth requireVerified={false}>
                            <VerifyEmail />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/dashboard"
                    element={
                        <RequireAuth>
                            <Dashboard />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/videos"
                    element={
                        <RequireAuth>
                            <Videos />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/videos/upload"
                    element={
                        <RequireAuth>
                            <VideoUpload />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/videos/:id/edit"
                    element={
                        <RequireAuth>
                            <VideoEdit />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/videos/:id/results"
                    element={
                        <RequireAuth>
                            <VideoResults />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/search"
                    element={
                        <RequireAuth>
                            <VideoSearch />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/workspaces"
                    element={
                        <RequireAuth>
                            <Workspaces />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/workspaces/create"
                    element={
                        <RequireAuth>
                            <WorkspaceCreate />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/workspaces/:id/edit"
                    element={
                        <RequireAuth>
                            <WorkspaceEdit />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/workspaces/:id/dashboard"
                    element={
                        <RequireAuth>
                            <WorkspaceDashboard />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/workspaces/:id/members"
                    element={
                        <RequireAuth>
                            <WorkspaceMembers />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/profile"
                    element={
                        <RequireAuth requireVerified={false}>
                            <Profile />
                        </RequireAuth>
                    }
                />
            </Routes>
        </Suspense>
    );
}
