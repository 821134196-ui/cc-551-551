import React, { useState } from 'react';
import { useAuth } from '../app';

const DEMO = [
    { label: '学生·张三', email: 'zhangsan@demo.edu' },
    { label: '导师·王明', email: 'wangming@demo.edu' },
    { label: '学生·赵四(导师B)', email: 'zhaosi@demo.edu' },
    { label: '导师·李华(导师B)', email: 'lihua@demo.edu' },
];

export default function Login() {
    const { login } = useAuth();
    const [email, setEmail] = useState('zhangsan@demo.edu');
    const [password, setPassword] = useState('password');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const submit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await login(email, password);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-slate-100 flex items-center justify-center p-4">
            <div className="bg-white rounded-2xl shadow-lg w-full max-w-md p-8">
                <h1 className="text-xl font-bold text-slate-800">毕业设计指导系统</h1>
                <p className="text-sm text-slate-500 mt-1">预约指导 · 稿件版本 · 评语追溯</p>

                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div>
                        <label className="text-sm font-medium text-slate-600">邮箱</label>
                        <input className="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2"
                            value={email} onChange={(e) => setEmail(e.target.value)} />
                    </div>
                    <div>
                        <label className="text-sm font-medium text-slate-600">密码</label>
                        <input type="password" className="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2"
                            value={password} onChange={(e) => setPassword(e.target.value)} />
                    </div>
                    {error && <div className="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2">{error}</div>}
                    <button disabled={loading}
                        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 font-medium disabled:opacity-50">
                        {loading ? '登录中…' : '登录'}
                    </button>
                </form>

                <div className="mt-6 border-t pt-4">
                    <p className="text-xs text-slate-400 mb-2">演示账号（密码均为 password），点击快速填充：</p>
                    <div className="flex flex-wrap gap-2">
                        {DEMO.map((d) => (
                            <button key={d.email} type="button" onClick={() => { setEmail(d.email); setPassword('password'); }}
                                className="text-xs border border-slate-300 rounded-full px-3 py-1 hover:bg-slate-100 text-slate-600">
                                {d.label}
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
