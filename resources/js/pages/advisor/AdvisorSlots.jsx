import React, { useEffect, useState } from 'react';
import api from '../../api.js';
import { Badge, Button, Card, ErrorBanner, fmt } from '../../ui.jsx';

export default function AdvisorSlots() {
    const [slots, setSlots] = useState([]);
    const [start, setStart] = useState('');
    const [duration, setDuration] = useState(60);
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);
    const [leaveReason, setLeaveReason] = useState({});

    const load = async () => {
        const { data } = await api.get('/slots/mine');
        setSlots(data);
    };

    useEffect(() => { load(); }, []);

    const createSlot = async () => {
        setError(null);
        if (!start) { setError({ response: { data: { message: '请选择时段开始时间' } } }); return; }
        setBusy(true);
        try {
            const startAt = new Date(start);
            const endAt = new Date(startAt.getTime() + Number(duration) * 60000);
            await api.post('/slots', {
                start_at: startAt.toISOString(),
                end_at: endAt.toISOString(),
            });
            setStart('');
            load();
        } catch (e) { setError(e); } finally { setBusy(false); }
    };

    const leave = async (slot) => {
        const reason = leaveReason[slot.id] || '';
        if (!window.confirm(`确定对 ${fmt(slot.start_at)} 的时段请假吗？${slot.booking ? '已预约学生将收到取消通知。' : ''}`)) return;
        await api.post(`/slots/${slot.id}/leave`, { reason: reason || null });
        setLeaveReason((r) => ({ ...r, [slot.id]: '' }));
        load();
    };

    const reopen = async (slot) => {
        await api.post(`/slots/${slot.id}/reopen`);
        load();
    };

    const cancelBooking = async (booking) => {
        if (!window.confirm('确定取消该学生的预约并重新开放时段吗？')) return;
        await api.delete(`/bookings/${booking.id}`);
        load();
    };

    const statusMeta = {
        open: { tone: 'green', text: '可预约' },
        booked: { tone: 'blue', text: '已预约' },
        leave: { tone: 'red', text: '请假' },
        done: { tone: 'slate', text: '已完成' },
    };

    return (
        <div className="space-y-5">
            <h1 className="text-xl font-bold">时段管理</h1>

            <Card className="p-4">
                <h2 className="text-base font-bold mb-3">开放新时段</h2>
                <ErrorBanner error={error} />
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">开始时间</label>
                        <input type="datetime-local" value={start} onChange={(e) => setStart(e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">时长（分钟）</label>
                        <select value={duration} onChange={(e) => setDuration(e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            {[30, 45, 60, 90, 120].map((m) => <option key={m} value={m}>{m} 分钟</option>)}
                        </select>
                    </div>
                    <Button onClick={createSlot} disabled={busy}>开放时段</Button>
                </div>
            </Card>

            <div className="space-y-2">
                {slots.length === 0 && <Card className="p-4 text-sm text-slate-500">还没有时段。</Card>}
                {slots.map((s) => {
                    const meta = statusMeta[s.status] || statusMeta.open;
                    return (
                        <Card key={s.id} className="p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">{fmt(s.start_at)}</span>
                                        <span className="text-slate-400 text-sm">— {fmt(s.end_at)}</span>
                                        <Badge tone={meta.tone}>{meta.text}</Badge>
                                    </div>
                                    {s.booking && (
                                        <div className="mt-2 text-sm text-slate-600">
                                            学生：<span className="font-medium">{s.booking.student?.name}</span>（{s.booking.student?.student_no}）
                                            {s.booking.questions && <div className="text-xs text-slate-500 mt-1">讨论问题：{s.booking.questions}</div>}
                                        </div>
                                    )}
                                    {s.status === 'open' && (
                                        <input
                                            className="mt-2 w-64 rounded-lg border border-slate-200 px-2 py-1 text-xs"
                                            placeholder="请假原因（可选，通知给学生）"
                                            value={leaveReason[s.id] || ''}
                                            onChange={(e) => setLeaveReason((r) => ({ ...r, [s.id]: e.target.value }))}
                                        />
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    {s.status === 'booked' && (
                                        <>
                                            {s.booking?.project_id && (
                                                <a href={`/projects/${s.booking.project_id}`}>
                                                    <Button variant="secondary" className="text-xs py-1">进入课题</Button>
                                                </a>
                                            )}
                                            <Button variant="ghost" className="text-xs py-1" onClick={() => cancelBooking(s.booking)}>取消预约并释放</Button>
                                        </>
                                    )}
                                    {s.status === 'open' && (
                                        <Button variant="danger" className="text-xs py-1" onClick={() => leave(s)}>请假 / 释放</Button>
                                    )}
                                    {s.status === 'leave' && (
                                        <Button variant="secondary" className="text-xs py-1" onClick={() => reopen(s)}>重新开放</Button>
                                    )}
                                </div>
                            </div>
                        </Card>
                    );
                })}
            </div>
        </div>
    );
}
