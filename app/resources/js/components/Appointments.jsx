import React, { useEffect, useState } from 'react';
import { api, fmt } from '../api';
import { useAuth } from '../app';
import Modal from './Modal';

export default function Appointments({ notify }) {
    const { user } = useAuth();
    const [items, setItems] = useState([]);
    const [submissions, setSubmissions] = useState([]);
    const [detail, setDetail] = useState(null);
    const [deadline, setDeadline] = useState({});

    const load = async () => setItems((await api('/appointments')).data);
    useEffect(() => { load(); }, []);

    const openDetail = async (id) => {
        const d = await api(`/appointments/${id}`);
        setDetail(d.data);
        if (user.role === 'advisor') {
            const subs = await api('/submissions');
            setSubmissions(subs.data.filter((s) => s.student?.id === d.data.student?.id && s.status === 'submitted'));
        }
    };

    const cancel = async (id) => {
        try {
            await api(`/appointments/${id}/cancel`, { method: 'POST' });
            notify('预约已取消，时段已释放给其他学生。');
            setDetail(null);
            load();
        } catch (e) { notify(e.message, false); }
    };

    const saveDeadline = async (id) => {
        try {
            await api(`/appointments/${id}/deadline`, { method: 'POST', body: { submit_deadline: deadline[id] } });
            notify('提交截止日已设置，学生首页将按此时间排序提醒。');
            setDetail(null);
            load();
        } catch (e) { notify(e.message, false); }
    };

    return (
        <div className="bg-white border rounded-xl">
            <h3 className="px-5 py-3 border-b font-semibold text-slate-700">
                {user.role === 'advisor' ? '学生预约（约谈后在此写评语）' : '我的预约'}
            </h3>
            <ul className="divide-y">
                {items.map((a) => (
                    <li key={a.id} className="px-5 py-3 flex items-center gap-4 flex-wrap">
                        <div className="flex-1">
                            <p className="text-slate-800">
                                {user.role === 'advisor' ? a.student?.name : ''}
                                {a.topic ? ` · ${a.topic}` : ''}
                            </p>
                            <p className="text-xs text-slate-400">
                                {a.time_slot ? fmt(a.time_slot.start_at) : ''} · {a.time_slot?.location}
                                {a.submit_deadline ? ` ｜截稿 ${fmt(a.submit_deadline)}` : ''}
                            </p>
                        </div>
                        <StatusTag status={a.status} />
                        {a.guidance && (
                            <span className="text-xs text-indigo-600">已评：第 {a.guidance.submission_version} 版</span>
                        )}
                        <button onClick={() => openDetail(a.id)} className="text-sm border rounded-lg px-3 py-1 hover:bg-slate-50">详情</button>
                    </li>
                ))}
            </ul>

            {detail && (
                <Modal onClose={() => setDetail(null)} title="预约详情">
                    <p className="text-sm text-slate-600">时间：{detail.time_slot ? fmt(detail.time_slot.start_at) : ''}</p>
                    <p className="text-sm text-slate-600">学生：{detail.student?.name}</p>
                    {detail.questions?.length > 0 && (
                        <div className="mt-3">
                            <p className="text-sm font-medium text-slate-700">学生准备讨论的问题：</p>
                            <ul className="list-disc pl-6 text-sm text-slate-600 mt-1 space-y-1">
                                {detail.questions.map((q, i) => <li key={i}>{q}</li>)}
                            </ul>
                        </div>
                    )}

                    {user.role === 'student' && detail.status === 'booked' && (
                        <button onClick={() => cancel(detail.id)}
                            className="mt-4 text-sm border border-red-300 text-red-600 rounded-lg px-3 py-1.5 hover:bg-red-50">
                            取消预约（释放时段）
                        </button>
                    )}

                    {user.role === 'advisor' && (
                        <div className="mt-4 space-y-4">
                            <div className="flex gap-2 items-center">
                                <label className="text-sm text-slate-600 whitespace-nowrap">提交截止日</label>
                                <input type="datetime-local" className="border rounded-lg px-2 py-1 text-sm"
                                    value={deadline[detail.id] || ''}
                                    onChange={(e) => setDeadline({ ...deadline, [detail.id]: e.target.value })} />
                                <button onClick={() => saveDeadline(detail.id)} className="text-sm border rounded-lg px-3 py-1 hover:bg-slate-50">保存</button>
                            </div>
                            {detail.guidance ? (
                                <p className="text-sm text-slate-500 bg-slate-50 rounded-lg p-3">
                                    该约谈已针对第 <b>{detail.guidance.submission_version}</b> 版留下指导记录（不可修改），
                                    可在"学生稿件"页查看版本追溯。
                                </p>
                            ) : detail.status === 'booked' ? (
                                <GuidanceForm
                                    appointmentId={detail.id}
                                    submissions={submissions}
                                    onDone={() => { notify('指导记录已保存，学生将收到通知。'); setDetail(null); load(); }}
                                    onError={(m) => notify(m, false)}
                                />
                            ) : null}
                        </div>
                    )}
                </Modal>
            )}
        </div>
    );
}

function GuidanceForm({ appointmentId, submissions, onDone, onError }) {
    const [submissionId, setSubmissionId] = useState(submissions[0]?.id || '');
    const [comment, setComment] = useState('');
    const [items, setItems] = useState(['']);
    const [files, setFiles] = useState([]);

    const submit = async () => {
        const fd = new FormData();
        fd.append('submission_id', submissionId);
        fd.append('overall_comment', comment);
        items.filter(Boolean).forEach((it) => fd.append('feedback_items[]', it));
        Array.from(files).forEach((f) => fd.append('files[]', f));
        try {
            await api(`/appointments/${appointmentId}/guidance`, { method: 'POST', body: fd, isForm: true });
            onDone();
        } catch (e) { onError(e.message); }
    };

    return (
        <div className="border-t pt-4 space-y-3">
            <p className="text-sm font-medium text-slate-700">约谈后评语（将永久固定到所选版本，提交后不可修改）</p>
            <select className="w-full border rounded-lg px-3 py-2 text-sm" value={submissionId} onChange={(e) => setSubmissionId(e.target.value)}>
                <option value="" disabled>选择本次指导针对的稿件版本…</option>
                {submissions.map((s) => (
                    <option key={s.id} value={s.id}>第 {s.version} 版 · {s.title}（{s.status === 'withdrawn' ? '已撤回' : '有效'}）</option>
                ))}
            </select>
            <textarea rows={3} className="w-full border rounded-lg px-3 py-2 text-sm" placeholder="总体评语" value={comment} onChange={(e) => setComment(e.target.value)} />
            <div className="space-y-2">
                <p className="text-sm text-slate-600">逐条修改意见：</p>
                {items.map((it, i) => (
                    <div key={i} className="flex gap-2">
                        <span className="text-sm text-slate-400 pt-2 w-6">#{i + 1}</span>
                        <textarea rows={2} className="flex-1 border rounded-lg px-2 py-1 text-sm" value={it}
                            onChange={(e) => setItems(items.map((v, j) => j === i ? e.target.value : v))} />
                        {items.length > 1 && (
                            <button onClick={() => setItems(items.filter((_, j) => j !== i))} className="text-red-400 text-sm">删</button>
                        )}
                    </div>
                ))}
                <button onClick={() => setItems([...items, ''])} className="text-sm text-indigo-600">＋ 添加一条</button>
            </div>
            <input type="file" multiple onChange={(e) => setFiles(e.target.files)} className="text-sm" />
            <button onClick={submit}
                disabled={!submissionId || !comment || items.every((i) => !i.trim())}
                className="w-full bg-indigo-600 text-white rounded-lg py-2 disabled:opacity-40 hover:bg-indigo-700">
                保存指导记录
            </button>
        </div>
    );
}

function StatusTag({ status }) {
    const map = {
        booked: ['已预约', 'bg-indigo-100 text-indigo-700'],
        completed: ['已完成', 'bg-emerald-100 text-emerald-700'],
        cancelled: ['学生已取消', 'bg-slate-200 text-slate-500'],
        released_by_leave: ['导师请假释放', 'bg-red-100 text-red-600'],
    };
    const [label, cls] = map[status] || [status, 'bg-slate-100'];
    return <span className={`text-xs rounded-full px-2.5 py-1 ${cls}`}>{label}</span>;
}
