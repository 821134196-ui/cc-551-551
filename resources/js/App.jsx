import React from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider, useAuth } from './auth.jsx';
import Layout from './pages/Layout.jsx';
import Login from './pages/Login.jsx';
import StudentDashboard from './pages/student/Dashboard.jsx';
import AdvisorDashboard from './pages/advisor/Dashboard.jsx';
import ProjectDetail from './pages/ProjectDetail.jsx';
import AdvisorSlots from './pages/advisor/AdvisorSlots.jsx';
import NewProject from './pages/student/NewProject.jsx';
import { Spinner } from './ui.jsx';

function Protected({ children, role }) {
    const { user, ready } = useAuth();
    if (!ready) return <Spinner />;
    if (!user) return <Navigate to="/login" replace />;
    if (role && user.role !== role) return <Navigate to="/" replace />;
    return children;
}

function HomeRedirect() {
    const { user, ready } = useAuth();
    if (!ready) return <Spinner />;
    if (!user) return <Navigate to="/login" replace />;
    return <Navigate to={user.role === 'advisor' ? '/advisor' : '/student'} replace />;
}

export default function App() {
    return (
        <AuthProvider>
            <BrowserRouter>
                <Routes>
                    <Route path="/login" element={<Login />} />
                    <Route element={<Protected><Layout /></Protected>}>
                        <Route path="/" element={<HomeRedirect />} />
                        <Route path="/student" element={<Protected role="student"><StudentDashboard /></Protected>} />
                        <Route path="/student/new-project" element={<Protected role="student"><NewProject /></Protected>} />
                        <Route path="/advisor" element={<Protected role="advisor"><AdvisorDashboard /></Protected>} />
                        <Route path="/advisor/slots" element={<Protected role="advisor"><AdvisorSlots /></Protected>} />
                        <Route path="/projects/:id" element={<Protected><ProjectDetail /></Protected>} />
                    </Route>
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </BrowserRouter>
        </AuthProvider>
    );
}
