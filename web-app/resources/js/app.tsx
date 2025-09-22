import './bootstrap';
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from 'next-themes';
import { AuthProvider } from './hooks/use-auth';
import { ProtectedRoute } from './components/protected-route';
import { PublicRoute } from './components/public-route';
import Layout from '@/components/layout';
import Dashboard from '@/pages/dashboard';
import YourMatches from '@/pages/your-matches';
import GrenadeLibrary from '@/pages/grenade-library';
import MatchDetail from '@/pages/match-detail';
import LoginPage from '@/pages/login';
import RegisterPage from '@/pages/register';
import { SteamCallbackPage } from '@/pages/steam-callback';
import { OnboardingPage } from '@/pages/onboarding';
import AccountSettingsPage from '@/pages/account-settings';
import { Toaster } from '@/components/ui/sonner';

const App: React.FC = () => {
  return (
    <ThemeProvider attribute="class" defaultTheme="dark" enableSystem={false}>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            {/* Public routes - only accessible when not authenticated */}
            <Route
              path="/login"
              element={
                <PublicRoute>
                  <LoginPage />
                </PublicRoute>
              }
            />
            <Route
              path="/register"
              element={
                <PublicRoute>
                  <RegisterPage />
                </PublicRoute>
              }
            />
            <Route path="/steam-callback" element={<SteamCallbackPage />} />
            <Route path="/onboarding" element={<OnboardingPage />} />

            {/* Protected routes - only accessible when authenticated */}
            <Route
              path="/"
              element={
                <ProtectedRoute>
                  <Layout />
                </ProtectedRoute>
              }
            >
              <Route index element={<YourMatches />} />
              <Route path="dashboard" element={<Dashboard />} />
              <Route path="grenade-library" element={<GrenadeLibrary />} />
              <Route
                path="matches/:id/:tab/:playerId?"
                element={<MatchDetail />}
              />
              <Route
                path="matches/:id"
                element={<Navigate to="match-details" replace />}
              />
              <Route path="settings" element={<AccountSettingsPage />} />
            </Route>

            {/* Catch all route - redirect to root if authenticated, login if not */}
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </BrowserRouter>
        <Toaster />
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
