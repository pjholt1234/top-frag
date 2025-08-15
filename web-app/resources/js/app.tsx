import './bootstrap';
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from 'next-themes';
import { AuthProvider } from './hooks/useAuth';
import { ProtectedRoute } from './components/ProtectedRoute';
import { PublicRoute } from './components/PublicRoute';
import Layout from '@/components/Layout';
import Dashboard from '@/pages/dashboard';
import YourMatches from '@/pages/your-matches';
import GrenadeLibrary from '@/pages/grenade-library';
import LoginPage from '@/pages/login';
import RegisterPage from '@/pages/register';
import LandingPage from '@/pages/landing';

const App: React.FC = () => {
    return (
        <ThemeProvider attribute="class" defaultTheme="dark" enableSystem={false}>
            <AuthProvider>
                <BrowserRouter>
                    <Routes>
                        {/* Public routes - only accessible when not authenticated */}
                        <Route path="/login" element={
                            <PublicRoute>
                                <LoginPage />
                            </PublicRoute>
                        } />
                        <Route path="/register" element={
                            <PublicRoute>
                                <RegisterPage />
                            </PublicRoute>
                        } />

                        {/* Protected routes - only accessible when authenticated */}
                        <Route path="/" element={
                            <ProtectedRoute>
                                <Layout />
                            </ProtectedRoute>
                        }>
                            <Route index element={<YourMatches />} />
                            <Route path="dashboard" element={<Dashboard />} />
                            <Route path="grenade-library" element={<GrenadeLibrary />} />
                        </Route>

                        {/* Catch all route - redirect to root if authenticated, login if not */}
                        <Route path="*" element={<Navigate to="/" replace />} />
                    </Routes>
                </BrowserRouter>
            </AuthProvider>
        </ThemeProvider>
    );
};

// Check if the element exists before rendering
const element = document.getElementById('app');
if (element) {
    ReactDOM.createRoot(element).render(
        <React.StrictMode>
            <App />
        </React.StrictMode>
    );
} 