import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth.jsx';
import { Button, ErrorBanner } from '../ui.jsx';

const demoAccounts = [
    { label: '学生 · 张一鸣', email: 'zhang@example.edu' },
    { label: '学生 · 陈思琪', email: 'chen@example.edu' },
    { label: '导师 · 王雅琴', email: 'wang@example.edu' },
    { label: '导师 · 李国栋', email: 'li@example.edu' },
];

export default function Login() {
    const { login, user, ready } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('zhang@example.edu');
    const [password, setPassword] = useState('password');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    if (ready && user) {
        navigate(user.role === 'advisor' ? '/advisor' : '/student', { replace: true });
    }

    const submit = async (e) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        try {
            const u = await login(email, password);
            navigate(u.role === 'advisor' ? '/advisor' : '/student', { replace: true });
        } catch (err) {
            setError(err);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-50 via-white to-sky-50 px-4">
            <div className="w-full max-w-md">
                <div className="text-center mb-6">
                    <div className="text-4xl mb-2">🎓</div>
                    <h1 className="text-2xl font-bold text-slate-800">毕业设计指导预约与评语版本系统</h1>
                    <p className="text-sm text-slate-500 mt-1">约谈预约 · 稿件版本 · 评语追溯</p>
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <ErrorBanner error={error} />
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">邮箱</label>
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                            required
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">密码</label>
                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                            required
                        />
                    </div>
                    <Button type="submit" className="w-full justify-center" disabled={loading}>
                        {loading ? '登录中…' : '登录'}
                    </Button>
                </form>

                <div className="mt-4 bg-white/70 rounded-xl border border-slate-200 p-4">
                    <div className="text-xs text-slate-500 mb-2">演示账号（密码均为 <code className="bg-slate-100 px-1 rounded">password</code>），点击快速填充：</div>
                    <div className="grid grid-cols-2 gap-2">
                        {demoAccounts.map((a) => (
                            <button
                                key={a.email}
                                type="button"
                                onClick={() => setEmail(a.email)}
                                className="text-left text-xs px-2 py-1.5 rounded-lg border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 transition"
                            >
                                {a.label}
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
