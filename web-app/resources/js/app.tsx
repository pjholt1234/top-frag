import './bootstrap';
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { ThemeProvider } from 'next-themes';
import Layout from '@/components/Layout';
import Dashboard from '@/pages/dashboard';
import YourMatches from '@/pages/your-matches';
import GrenadeLibrary from '@/pages/grenade-library';

const App: React.FC = () => {
    return (
        <ThemeProvider attribute="class" defaultTheme="dark" enableSystem={false}>
            <BrowserRouter>
                <Routes>
                    <Route path="/" element={<Layout />}>
                        <Route index element={<YourMatches />} />
                        <Route path="dashboard" element={<Dashboard />} />
                        <Route path="grenade-library" element={<GrenadeLibrary />} />
                    </Route>
                </Routes>
            </BrowserRouter>
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