import React, { useEffect, useState } from 'react';
import { api, fmt } from '../api';
import { useAuth } from '../app';

const STATUS_STYLE = {
    open: 'border-red-300 bg-red-50',
    answered: 'border-amber-300 bg-amber-50',
    closed: 'border-emerald-300 bg-emerald-50',
};

export default function StudentDashboard({ goto }) {
    const { user } = useAuth();
    const [data, setData] = useState(null);

    const load = async () => setData(await api('/dashboard'));
    useEffect(() => { load(); }, []);

    if (!data) return <p className="text-slate-400">加载中…</p>;

    return (
        <div className="space-y-6">
            <section>
                <div className="flex items-center gap-3 mb-3">
                    <h2 className="text-lg font-bold text-slate-800">尚未处理的意见</h2>
                    <span className="bg-red-100 text-red-700 text-sm font-semibold rounded-full px-3 py-0.5">
                        {data.pending_feedback_count} 条
                    </span>
                </div>

                {data.pending_feedback.length === 0 && (
                    <div className="bg-white border rounded-xl p-6 text-center text-slate-400">
                        🎉 当前没有待处理意见，等待导师下一次指导或提交新稿。
                    </div>
                )}

                <div className="space-y-3">
                    {data.pending_feedback.map((f) => (
                        <div key={f.id} className={`border-l-4 rounded-xl bg-white p-4 shadow-sm ${STATUS_STYLE[f.status] || ''}`}>
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <div className="flex items-center gap-2 text-xs text-slate-500">
                                        <span className="font-medium">#{f.sequence}</span>
                                        <span className="px-2 py-0.5 rounded-full bg-slate-100">{f.status_label}</span>
                                        <span>来自 {f.advisor_name}</span>
                                    </div>
                                    <p className="mt-2 text-slate-800">{f.content}</p>
                                    <p className="mt-2 text-xs text-slate-400">
                                        📌 该意见针对第 <b className="text-slate-600">{f.submission_version}</b> 版《{f.submission_title}》
                                    </p>
                                    {f.my_responses?.length > 0 && (
                                        <div className="mt-2 text-sm text-slate-600 bg-slate-50 rounded-lg p-2">
                                            我的回应（第 {f.my_responses[f.my_responses.length - 1].submission_version} 版）：
                                            {f.my_responses[f.my_responses.length - 1].reply}
                                        </div>
                                    )}
                                </div>
                                <button onClick={() => goto('submissions')}
                                    className="shrink-0 text-sm bg-indigo-600 text-white rounded-lg px-3 py-1.5 hover:bg-indigo-700">
                                    提交新稿回应
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            <section className="grid md:grid-cols-2 gap-6">
                <div className="bg-white rounded-xl border p-5">
                    <h3 className="font-semibold text-slate-700 mb-3">即将到来的约谈</h3>
                    {data.upcoming_appointments.length === 0 && <p className="text-sm text-slate-400">暂无预约</p>}
                    <ul className="space-y-2 text-sm">
                        {data.upcoming_appointments.map((a) => (
                            <li key={a.id} className="flex justify-between border-b last:border-0 pb-2">
                                <span>{fmt(a.start_at)} · {a.location}</span>
                                {a.submit_deadline && (
                                    <span className="text-amber-600">截稿 {fmt(a.submit_deadline)}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
                <div className="bg-white rounded-xl border p-5">
                    <h3 className="font-semibold text-slate-700 mb-3">进度概览</h3>
                    <dl className="text-sm space-y-2">
                        <div className="flex justify-between"><dt className="text-slate-500">当前最新版本</dt><dd>第 {data.latest_version} 版</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">待处理意见</dt><dd className="text-red-600 font-semibold">{data.pending_feedback_count} 条</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">答辩资格</dt>
                            <dd>{data.defense ? (data.defense.eligible
                                ? <span className="text-emerald-600 font-semibold">已确认 ✓</span>
                                : <span className="text-amber-600">暂未通过</span>)
                                : <span className="text-slate-400">导师尚未确认</span>}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>
    );
}
