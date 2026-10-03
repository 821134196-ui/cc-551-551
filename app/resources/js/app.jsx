import React, { createContext, useContext, useState } from 'react';
import { createRoot } from 'react-dom/client';
import Login from './components/Login';
import Shell from './components/Shell';
import { getStoredUser, saveSession, clearSession, api } from './api';

const AuthContext = createContext(null);
export const useAuth = () => useContext(AuthContext);

function App() {
    const [user, setUser] = useState(getStoredUser());

    const login = async (email, password) => {
        const data = await api('/auth/login', {
            method: 'POST',
            body: { email, password },
        });
        saveSession(data.token, data.user);
        setUser(data.user);
    };

    const logout = () => {
        clearSession();
        setUser(null);
    };

    return (
        <AuthContext.Provider value={{ user, login, logout, setUser }}>
            {user ? <Shell /> : <Login />}
        </AuthContext.Provider>
    );
}

createRoot(document.getElementById('root')).render(<App />);
