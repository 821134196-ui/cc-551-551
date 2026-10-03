import React, { useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth.jsx';
import api from '../api.js';
import { Badge, fmt } from '../ui.jsx';

export default function Layout() {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const [notifications, setNotifications] = useState([]);
    const [unread, setUnread] = useState(0);
    const [open, setOpen] = useState(false);
    const panelRef = useRef(null);

    const load = async () => {
        try {
            const { data } = await api.get('/notifications');
            setNotifications(data.notifications);
            setUnread(data.unread_count);
        } catch (e) { /* ignore */ }
    };

    useEffect(() => {
        load();
        const timer = setInterval(load, 8000); // 本地模拟通知：轮询
        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        const onClick = (e) => {
            if (panelRef.current && !panelRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    const markAll = async () => {
        await api.post('/notifications/read-all');
        load();
    };

    const markOne = async (n) => {
        if (!n.read_at) {
            await api.post(`/notifications/${n.id}/read`);
        }
        const pid = n.data?.project_id;
        if (pid) navigate(`/projects/${pid}`);
        setOpen(false);
        load();
    };

    return (
        <div className="min-h-screen bg-slate-50 text-slate-800">
            <header className="bg-white border-b border-slate-200 sticky top-0 z-20">
                <div className="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
                    <div className="flex items-center gap-6">
                        <Link to="/" className="font-semibold text-indigo-700">🎓 毕设指导系统</Link>
                        <nav className="flex items-center gap-1 text-sm">
                            {user.role === 'student' && (
                                <NavLink to="/student" className={({ isActive }) =>
                                    `px-3 py-1.5 rounded-lg ${isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-100'}`}>
                                    我的首页
                                </NavLink>
                            )}
                            {user.role === 'advisor' && (
                                <>
                                    <NavLink to="/advisor" className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg ${isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-100'}`}>
                                        待办首页
                                    </NavLink>
                                    <NavLink to="/advisor/slots" className={({ isActive }) =>
                                        `px-3 py-1.5 rounded-lg ${isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-100'}`}>
                                        时段管理
                                    </NavLink>
                                </>
                            )}
                        </nav>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="relative" ref={panelRef}>
                            <button
                                onClick={() => setOpen((v) => !v)}
                                className="relative p-2 rounded-lg hover:bg-slate-100"
                                title="通知（本地模拟）"
                            >
                                🔔
                                {unread > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-red-500 text-white text-[10px] flex items-center justify-center">
                                        {unread > 99 ? '99+' : unread}
                                    </span>
                                )}
                            </button>
                            {open && (
                                <div className="absolute right-0 mt-2 w-80 bg-white rounded-xl border border-slate-200 shadow-lg max-h-96 overflow-y-auto">
                                    <div className="flex items-center justify-between px-3 py-2 border-b border-slate-100">
                                        <span className="text-sm font-medium">通知</span>
                                        <button onClick={markAll} className="text-xs text-indigo-600 hover:underline">全部已读</button>
                                    </div>
                                    {notifications.length === 0 && <div className="p-4 text-sm text-slate-400 text-center">暂无通知</div>}
                                    {notifications.map((n) => (
                                        <button
                                            key={n.id}
                                            onClick={() => markOne(n)}
                                            className={`w-full text-left px-3 py-2 border-b border-slate-50 hover:bg-slate-50 ${!n.read_at ? 'bg-indigo-50/40' : ''}`}
                                        >
                                            <div className="text-sm font-medium text-slate-800">{n.title}</div>
                                            {n.body && <div className="text-xs text-slate-500 mt-0.5 line-clamp-2">{n.body}</div>}
                                            <div className="text-[10px] text-slate-400 mt-1">{fmt(n.created_at)}</div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="text-sm text-right leading-tight">
                            <div className="font-medium">{user.name}</div>
                            <div><Badge tone={user.role === 'advisor' ? 'violet' : 'blue'}>{user.role === 'advisor' ? `导师 · ${user.title || ''}` : '学生'}</Badge></div>
                        </div>
                        <button onClick={async () => { await logout(); navigate('/login'); }} className="text-sm text-slate-500 hover:text-slate-800 px-2 py-1">
                            退出
                        </button>
                    </div>
                </div>
            </header>

            <main className="max-w-6xl mx-auto px-4 py-6">
                <Outlet />
            </main>
        </div>
    );
}
