import React, { useCallback, useEffect, useState } from 'react';
import { useAuth } from '../app';
import { api } from '../api';
import Notifications from './Notifications';
import Slots from './Slots';
import Appointments from './Appointments';
import Submissions from './Submissions';
import StudentDashboard from './StudentDashboard';
import AdvisorDashboard from './AdvisorDashboard';

const TABS = {
    student: [
        { key: 'home', label: '首页·待办意见' },
        { key: 'slots', label: '预约时段' },
        { key: 'appointments', label: '我的预约' },
        { key: 'submissions', label: '稿件与评语' },
    ],
    advisor: [
        { key: 'home', label: '首页·待办' },
        { key: 'slots', label: '时段管理' },
        { key: 'appointments', label: '预约与评语' },
        { key: 'submissions', label: '学生稿件' },
    ],
};

export default function Shell() {
    const { user, logout } = useAuth();
    const tabs = TABS[user.role];
    const [tab, setTab] = useState('home');
    const [notice, setNotice] = useState(null);
    const [unread, setUnread] = useState(0);

    const refreshUnread = useCallback(async () => {
        try {
            const d = await api('/notifications');
            setUnread(d.unread_count);
        } catch { /* ignore */ }
    }, []);

    useEffect(() => { refreshUnread(); const t = setInterval(refreshUnread, 15000); return () => clearInterval(t); }, [refreshUnread]);

    const notify = (message, ok = true) => {
        setNotice({ message, ok });
        setTimeout(() => setNotice(null), 3500);
    };

    return (
        <div className="min-h-screen bg-slate-100">
            <header className="bg-white border-b">
                <div className="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
                    <span className="font-bold text-slate-800">毕设指导系统</span>
                    <nav className="flex gap-1 flex-wrap">
                        {tabs.map((t) => (
                            <button key={t.key} onClick={() => setTab(t.key)}
                                className={`px-3 py-1.5 rounded-lg text-sm ${tab === t.key ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                                {t.label}
                            </button>
                        ))}
                        <button onClick={() => setTab('notifications')}
                            className={`px-3 py-1.5 rounded-lg text-sm relative ${tab === 'notifications' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                            通知
                            {unread > 0 && <span className="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1.5">{unread}</span>}
                        </button>
                    </nav>
                    <div className="ml-auto flex items-center gap-3 text-sm text-slate-500">
                        <span>{user.name} · {user.role === 'student' ? '学生' : '导师'}</span>
                        <button onClick={logout} className="text-slate-400 hover:text-slate-700">退出</button>
                    </div>
                </div>
            </header>

            {notice && (
                <div className={`max-w-6xl mx-auto mt-3 px-4`}>
                    <div className={`rounded-lg px-4 py-2 text-sm ${notice.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`}>
                        {notice.message}
                    </div>
                </div>
            )}

            <main className="max-w-6xl mx-auto px-4 py-6">
                {tab === 'home' && (user.role === 'student' ? <StudentDashboard goto={setTab} /> : <AdvisorDashboard goto={setTab} />)}
                {tab === 'slots' && <Slots notify={notify} />}
                {tab === 'appointments' && <Appointments notify={notify} gotoSubmissions={() => setTab('submissions')} />}
                {tab === 'submissions' && <Submissions notify={notify} />}
                {tab === 'notifications' && <Notifications onRead={refreshUnread} />}
            </main>
        </div>
    );
}
