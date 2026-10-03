import React, { useEffect, useState } from 'react';
import { api, fmt } from '../api';

const KIND = {
    appointment: { label: '即将约谈', cls: 'bg-indigo-100 text-indigo-700' },
    guidance_due: { label: '待写评语', cls: 'bg-red-100 text-red-700' },
    deadline: { label: '提交截止', cls: 'bg-amber-100 text-amber-700' },
    deadline_overdue: { label: '截止已过', cls: 'bg-red-100 text-red-700' },
    feedback_to_review: { label: '学生已回应·待确认', cls: 'bg-emerald-100 text-emerald-700' },
};

export default function AdvisorDashboard({ goto }) {
    const [data, setData] = useState(null);
    useEffect(() => { api('/dashboard').then(setData); }, []);

    if (!data) return <p className="text-slate-400">加载中…</p>;

    return (
        <div className="space-y-6">
            <section className="grid grid-cols-3 gap-4">
                <Stat label="待办事项" value={data.todo_count} accent="text-indigo-600" />
                <Stat label="未来开放时段" value={data.open_slot_count} />
                <Stat label="指导学生" value={data.student_count} />
            </section>

            <section className="bg-white border rounded-xl">
                <div className="px-5 py-3 border-b flex items-center justify-between">
                    <h2 className="font-bold text-slate-800">待办（按预约时间 / 提交截止日排序）</h2>
                    <button onClick={() => goto('slots')} className="text-sm text-indigo-600 hover:underline">＋ 开放新时段</button>
                </div>
                {data.todos.length === 0 && <p className="p-6 text-center text-slate-400">暂无待办</p>}
                <ul className="divide-y">
                    {data.todos.map((t, i) => {
                        const k = KIND[t.kind];
                        return (
                            <li key={i} className="px-5 py-3 flex items-center gap-4">
                                <span className={`text-xs font-medium rounded-full px-2.5 py-1 whitespace-nowrap ${k.cls}`}>{k.label}</span>
                                <div className="flex-1">
                                    <p className="text-sm text-slate-800">
                                        <b>{t.student_name}</b>
                                        {t.kind === 'feedback_to_review' && (
                                            <>：意见 #{t.sequence} 已在第 {t.answered_in_version} 版回应 — “{t.reply}”</>
                                        )}
                                        {t.topic && <> · {t.topic}</>}
                                    </p>
                                    {t.kind !== 'feedback_to_review' && (
                                        <p className="text-xs text-slate-400 mt-0.5">约谈 {fmt(t.start_at)}{t.submit_deadline ? ` ｜ 截稿 ${fmt(t.submit_deadline)}` : ''}</p>
                                    )}
                                </div>
                                {t.appointment_id && (
                                    <button onClick={() => goto('appointments')}
                                        className="text-sm border rounded-lg px-3 py-1 hover:bg-slate-50">
                                        处理
                                    </button>
                                )}
                            </li>
                        );
                    })}
                </ul>
            </section>
        </div>
    );
}

function Stat({ label, value, accent = 'text-slate-800' }) {
    return (
        <div className="bg-white border rounded-xl p-5 text-center">
            <div className={`text-3xl font-bold ${accent}`}>{value}</div>
            <div className="text-sm text-slate-500 mt-1">{label}</div>
        </div>
    );
}
