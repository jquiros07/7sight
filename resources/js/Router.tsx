import { useEffect } from 'react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { RequireAuth } from './lib/RequireAuth';
import CameraCreate from './pages/CameraCreate';
import CameraEdit from './pages/CameraEdit';
import Cameras from './pages/Cameras';
import Dashboard from './pages/Dashboard';
import Home from './pages/Home';
import Login from './pages/Login';
import Profile from './pages/Profile';
import Register from './pages/Register';
import VerifyEmail from './pages/VerifyEmail';
import VideoEdit from './pages/VideoEdit';
import VideoResults from './pages/VideoResults';
import VideoSearch from './pages/VideoSearch';
import VideoUpload from './pages/VideoUpload';
import Videos from './pages/Videos';
import WorkspaceCreate from './pages/WorkspaceCreate';
import WorkspaceDashboard from './pages/WorkspaceDashboard';
import WorkspaceEdit from './pages/WorkspaceEdit';
import Workspaces from './pages/Workspaces';

export default function Router() {
    const location = useLocation();

    useEffect(() => {
        window.HSStaticMethods.autoInit();
    }, [location.pathname]);

    return (
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
                path="/cameras"
                element={
                    <RequireAuth>
                        <Cameras />
                    </RequireAuth>
                }
            />
            <Route
                path="/cameras/create"
                element={
                    <RequireAuth>
                        <CameraCreate />
                    </RequireAuth>
                }
            />
            <Route
                path="/cameras/:id/edit"
                element={
                    <RequireAuth>
                        <CameraEdit />
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
                path="/profile"
                element={
                    <RequireAuth requireVerified={false}>
                        <Profile />
                    </RequireAuth>
                }
            />
        </Routes>
    );
}
