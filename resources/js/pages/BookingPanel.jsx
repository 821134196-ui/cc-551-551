import React, { useEffect, useState } from 'react';
import api from '../api.js';
import { Button, Card, ErrorBanner, fmt } from '../ui.jsx';

/** 学生预约导师时段：选导师 → 查看开放时段 → 填写讨论问题 → 预约 */
export default function BookingPanel({ onBooked }) {
    const [advisors, setAdvisors] = useState([]);
    const [advisorId, setAdvisorId] = useState('');
    const [slots, setSlots] = useState([]);
    const [selectedSlot, setSelectedSlot] = useState(null);
    const [questions, setQuestions] = useState('');
    const [projectId, setProjectId] = useState('');
    const [projects, setProjects] = useState([]);
    const [error, setError] = useState(null);
    const [msg, setMsg] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        (async () => {
            const [a, p] = await Promise.all([
                api.get('/directory/advisors'),
                api.get('/projects'),
            ]);
            setAdvisors(a.data);
            setProjects(p.data);
        })();
    }, []);

    const loadSlots = async (id) => {
        setAdvisorId(id);
        setSelectedSlot(null);
        if (!id) { setSlots([]); return; }
        const { data } = await api.get('/slots/open', { params: { advisor_id: id } });
        setSlots(data);
    };

    const book = async () => {
        setError(null);
        setMsg(null);
        setLoading(true);
        try {
            await api.post('/bookings', {
                slot_id: selectedSlot,
                questions,
                project_id: projectId || undefined,
            });
            setMsg('预约成功！导师已收到本地模拟通知。');
            setSelectedSlot(null);
            setQuestions('');
            const { data } = await api.get('/slots/open', { params: { advisor_id: advisorId } });
            setSlots(data);
            onBooked?.();
        } catch (e) {
            setError(e);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Card className="p-4">
            <h2 className="text-lg font-bold mb-3">预约导师时段</h2>
            <ErrorBanner error={error} />
            {msg && <div className="mb-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-3 py-2">{msg}</div>}

            <div className="space-y-3">
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">选择导师</label>
                    <select
                        value={advisorId}
                        onChange={(e) => loadSlots(e.target.value)}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >
                        <option value="">— 请选择 —</option>
                        {advisors.map((a) => <option key={a.id} value={a.id}>{a.name}（{a.title || '导师'}）</option>)}
                    </select>
                </div>

                {advisorId && (
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">可预约时段</label>
                        {slots.length === 0 ? (
                            <div className="text-xs text-slate-400 py-2">该导师近期没有开放时段。</div>
                        ) : (
                            <div className="grid grid-cols-2 gap-2">
                                {slots.map((s) => (
                                    <button
                                        key={s.id}
                                        type="button"
                                        onClick={() => setSelectedSlot(s.id)}
                                        className={`text-left text-xs px-2.5 py-2 rounded-lg border transition ${
                                            selectedSlot === s.id
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                                : 'border-slate-200 hover:border-indigo-300'
                                        }`}
                                    >
                                        {fmt(s.start_at)}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {selectedSlot && (
                    <>
                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">关联课题（可选）</label>
                            <select value={projectId} onChange={(e) => setProjectId(e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">不关联</option>
                                {projects.map((p) => <option key={p.id} value={p.id}>{p.title}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">准备讨论的问题</label>
                            <textarea
                                value={questions}
                                onChange={(e) => setQuestions(e.target.value)}
                                rows={3}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="例如：实验基线如何选择？"
                            />
                        </div>
                        <Button onClick={book} disabled={loading} className="w-full justify-center">确认预约</Button>
                    </>
                )}
            </div>
        </Card>
    );
}
