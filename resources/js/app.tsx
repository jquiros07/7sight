import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import Router from './Router';
import { AuthProvider } from './lib/auth';
import '../css/app.css';
import 'preline';

const root = document.getElementById('app');

if (root) {
    createRoot(root).render(
        <BrowserRouter>
            <AuthProvider>
                <Router />
            </AuthProvider>
        </BrowserRouter>,
    );
}
