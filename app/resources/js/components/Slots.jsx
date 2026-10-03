import React, { useEffect, useState } from 'react';
import { api, fmt } from '../api';
import { useAuth } from '../app';

export default function Slots({ notify }) {
    const { user } = useAuth();
    const [slots, setSlots] = useState([]);
    const [questions, setQuestions] = useState({});
    const [booking, setBooking] = useState(null);
    const [form, setForm] = useState({ start_at: '', duration_minutes: 60, location: '' });
    const [leave, setLeave] = useState({ start_at: '', end_at: '', reason: '' });

    const load = async () => setSlots((await api('/time-slots')).data);
    useEffect(() => { load(); }, []);

    const book = async (slotId) => {
        try {
            const qs = (questions[slotId] || '').split('\n').map((s) => s.trim()).filter(Boolean);
            await api('/appointments', { method: 'POST', body: { time_slot_id: slotId, questions: qs } });
            notify('预约成功！同一时段其他学生将无法再预约。');
            setBooking(null);
            setQuestions({});
            load();
        } catch (e) { notify(e.message, false); }
    };

    const createSlot = async (e) => {
        e.preventDefault();
        try {
            await api('/time-slots', { method: 'POST', body: form });
            notify('时段已开放。');
            setForm({ start_at: '', duration_minutes: 60, location: '' });
            load();
        } catch (e) { notify(e.message, false); }
    };

    const createLeave = async (e) => {
        e.preventDefault();
        try {
            await api('/leaves', { method: 'POST', body: leave });
            notify('请假已登记，冲突的已预约时段已释放并通知学生。');
            setLeave({ start_at: '', end_at: '', reason: '' });
            load();
        } catch (e) { notify(e.message, false); }
    };

    return (
        <div className="space-y-6">
            {user.role === 'advisor' && (
                <div className="grid md:grid-cols-2 gap-6">
                    <form onSubmit={createSlot} className="bg-white border rounded-xl p-5 space-y-3">
                        <h3 className="font-semibold text-slate-700">开放新时段</h3>
                        <input type="datetime-local" className="w-full border rounded-lg px-3 py-2" required
                            value={form.start_at} onChange={(e) => setForm({ ...form, start_at: e.target.value })} />
                        <input type="number" min="10" max="480" className="w-full border rounded-lg px-3 py-2" placeholder="时长（分钟）"
                            value={form.duration_minutes} onChange={(e) => setForm({ ...form, duration_minutes: e.target.value })} />
                        <input className="w-full border rounded-lg px-3 py-2" placeholder="地点（可选）"
                            value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} />
                        <button className="w-full bg-indigo-600 text-white rounded-lg py-2 hover:bg-indigo-700">开放时段</button>
                    </form>
                    <form onSubmit={createLeave} className="bg-white border rounded-xl p-5 space-y-3">
                        <h3 className="font-semibold text-slate-700">导师请假（自动释放冲突时段）</h3>
                        <div className="grid grid-cols-2 gap-2">
                            <input type="datetime-local" className="border rounded-lg px-3 py-2" required
                                value={leave.start_at} onChange={(e) => setLeave({ ...leave, start_at: e.target.value })} />
                            <input type="datetime-local" className="border rounded-lg px-3 py-2" required
                                value={leave.end_at} onChange={(e) => setLeave({ ...leave, end_at: e.target.value })} />
                        </div>
                        <input className="w-full border rounded-lg px-3 py-2" placeholder="请假原因，如：外出开会" required
                            value={leave.reason} onChange={(e) => setLeave({ ...leave, reason: e.target.value })} />
                        <button className="w-full bg-red-600 text-white rounded-lg py-2 hover:bg-red-700">登记请假并释放时段</button>
                    </form>
                </div>
            )}

            <div className="bg-white border rounded-xl">
                <h3 className="px-5 py-3 border-b font-semibold text-slate-700">
                    {user.role === 'advisor' ? '我的时段' : `${user.advisor?.name || '导师'}的可预约时段`}
                </h3>
                <ul className="divide-y">
                    {slots.map((s) => (
                        <li key={s.id} className="px-5 py-3 flex items-center gap-4 flex-wrap">
                            <div>
                                <p className="text-slate-800">{fmt(s.start_at)} – {fmt(s.end_at).slice(11)}</p>
                                <p className="text-xs text-slate-400">{s.location}</p>
                                {s.cancel_reason && <p className="text-xs text-red-500">{s.cancel_reason}</p>}
                            </div>
                            <StatusBadge status={s.status} bookedBy={s.booked_by?.name} />
                            <div className="ml-auto">
                                {user.role === 'student' && s.status === 'open' && !s.booked_by && (
                                    booking === s.id ? (
                                        <div className="flex gap-2 items-start">
                                            <textarea rows={3} className="border rounded-lg px-2 py-1 text-sm"
                                                placeholder="准备讨论的问题，每行一条"
                                                value={questions[s.id] || ''}
                                                onChange={(e) => setQuestions({ ...questions, [s.id]: e.target.value })} />
                                            <div className="flex flex-col gap-1">
                                                <button onClick={() => book(s.id)} className="bg-indigo-600 text-white text-sm rounded-lg px-3 py-1">确认预约</button>
                                                <button onClick={() => setBooking(null)} className="text-sm text-slate-400">取消</button>
                                            </div>
                                        </div>
                                    ) : (
                                        <button onClick={() => setBooking(s.id)}
                                            className="bg-indigo-600 text-white text-sm rounded-lg px-4 py-1.5 hover:bg-indigo-700">预约</button>
                                    )
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

function StatusBadge({ status, bookedBy }) {
    const map = {
        open: ['可预约', 'bg-emerald-100 text-emerald-700'],
        booked: [`已预约${bookedBy ? `·${bookedBy}` : ''}`, 'bg-slate-200 text-slate-600'],
        cancelled: ['已取消/释放', 'bg-red-100 text-red-600'],
    };
    const [label, cls] = map[status] || [];
    return <span className={`text-xs rounded-full px-2.5 py-1 ${cls}`}>{label}</span>;
}
