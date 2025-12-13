import './bootstrap';
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from 'next-themes';
import { AuthProvider } from './hooks/use-auth';
import { PlayerCardProvider } from './hooks/use-player-card';
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
import AimPage from '@/pages/aim';
import UtilityPage from '@/pages/utility';
import MapStatsPage from '@/pages/map-stats';
import RanksPage from '@/pages/ranks';
import MyClans from '@/pages/my-clans';
import ClanDetail from '@/pages/clan-detail';
import { Toaster } from '@/components/ui/sonner';
import { PlayerCardModal } from '@/components/player-card-modal';

const App: React.FC = () => {
  return (
    <ThemeProvider attribute="class" defaultTheme="dark" enableSystem={false}>
      <AuthProvider>
        <PlayerCardProvider>
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
                <Route path="aim" element={<AimPage />} />
                <Route path="utility" element={<UtilityPage />} />
                <Route path="map-stats" element={<MapStatsPage />} />
                <Route path="ranks" element={<RanksPage />} />
                <Route path="clans" element={<MyClans />} />
                <Route path="clans/:id/:tab" element={<ClanDetail />} />
                <Route
                  path="clans/:id"
                  element={<Navigate to="overview" replace />}
                />
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
          <PlayerCardModal />
        </PlayerCardProvider>
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
