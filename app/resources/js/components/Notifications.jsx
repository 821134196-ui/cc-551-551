import React, { useEffect, useState } from 'react';
import { api, fmt } from '../api';

export default function Notifications({ onRead }) {
    const [items, setItems] = useState([]);

    const load = async () => setItems((await api('/notifications')).data);
    useEffect(() => { load(); }, []);

    const markAll = async () => {
        await api('/notifications/read', { method: 'POST' });
        load();
        onRead?.();
    };

    return (
        <div className="bg-white border rounded-xl">
            <div className="px-5 py-3 border-b flex items-center justify-between">
                <h3 className="font-semibold text-slate-700">通知（本地模拟：同时写入 storage/logs/notifications.log）</h3>
                <button onClick={markAll} className="text-sm text-indigo-600 hover:underline">全部标为已读</button>
            </div>
            <ul className="divide-y">
                {items.map((n) => (
                    <li key={n.id} className={`px-5 py-3 ${n.read_at ? 'opacity-60' : ''}`}>
                        <div className="flex items-center gap-2">
                            {!n.read_at && <span className="w-2 h-2 rounded-full bg-red-500" />}
                            <span className="text-sm font-medium text-slate-800">{n.title}</span>
                            <span className="text-xs text-slate-400 ml-auto">{fmt(n.created_at)}</span>
                        </div>
                        {n.body && <p className="text-sm text-slate-500 mt-1">{n.body}</p>}
                    </li>
                ))}
                {items.length === 0 && <li className="px-5 py-8 text-center text-slate-400 text-sm">暂无通知</li>}
            </ul>
        </div>
    );
}
