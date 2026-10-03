import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api.js';
import { Badge, Button, Card, CommentStatusBadge, fmt, Spinner } from '../../ui.jsx';

export default function AdvisorDashboard() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    const load = async () => {
        const { data } = await api.get('/dashboard/advisor');
        setData(data);
        setLoading(false);
    };

    useEffect(() => { load(); }, []);

    if (loading) return <Spinner />;

    return (
        <div className="space-y-6">
            {/* 待办计数概览 */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                <StatCard label="即将到来的预约" count={data.counts.upcoming_bookings} tone="blue" icon="📅" />
                <StatCard label="待确认的评语回应" count={data.counts.pending_confirm_comments} tone="amber" icon="✍️" />
                <StatCard label="临近提交截止" count={data.counts.deadlines} tone="violet" icon="⏰" />
                <StatCard label="待确认答辩资格" count={data.counts.final_pending} tone="green" icon="🎓" />
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
                {/* 待办 1：预约（按预约时间升序） */}
                <section>
                    <h2 className="text-lg font-bold mb-3">即将到来的预约（按时间排序）</h2>
                    <div className="space-y-2">
                        {data.upcoming_bookings.length === 0 && <Card className="p-4 text-sm text-slate-500">暂无预约。</Card>}
                        {data.upcoming_bookings.map((b) => (
                            <Card key={b.id} className="p-3">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <div className="text-sm font-medium">{fmt(b.slot.start_at)} — {new Date(b.slot.end_at).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', hour12: false })}</div>
                                        <div className="text-xs text-slate-500 mt-1">
                                            {b.student?.name}（{b.student?.student_no}）· {b.project?.title}
                                        </div>
                                        {b.questions && <div className="text-xs text-slate-400 mt-1.5 bg-slate-50 rounded p-2">学生问题：{b.questions}</div>}
                                    </div>
                                    {b.project && (
                                        <Link to={`/projects/${b.project.id}`}>
                                            <Button variant="secondary" className="text-xs py-1">进入指导</Button>
                                        </Link>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                </section>

                {/* 待办 2：学生回应、等待确认关闭 */}
                <section>
                    <h2 className="text-lg font-bold mb-3">学生已回应 · 待我确认</h2>
                    <div className="space-y-2">
                        {data.pending_confirm_comments.length === 0 && <Card className="p-4 text-sm text-slate-500">暂无待确认的回应。</Card>}
                        {data.pending_confirm_comments.map((c) => (
                            <Card key={c.id} className="p-3">
                                <div className="flex items-start justify-between gap-2 mb-1">
                                    <div className="text-xs text-slate-500">
                                        {c.guidance_record?.project?.student?.name} · {c.guidance_record?.project?.title}
                                    </div>
                                    <CommentStatusBadge status={c.status} />
                                </div>
                                <p className="text-sm text-slate-800">原意见：{c.content}</p>
                                {c.responses?.[0] && (
                                    <p className="text-xs text-emerald-700 mt-1 bg-emerald-50 rounded p-2">学生回应：{c.responses[0].content}</p>
                                )}
                                <Link to={`/projects/${c.guidance_record?.project_id}`}>
                                    <Button variant="secondary" className="text-xs py-1 mt-2">去确认 / 退回</Button>
                                </Link>
                            </Card>
                        ))}
                    </div>
                </section>

                {/* 待办 3：截止日 */}
                <section>
                    <h2 className="text-lg font-bold mb-3">提交截止日（按临近程度排序）</h2>
                    <div className="space-y-2">
                        {data.deadlines.length === 0 && <Card className="p-4 text-sm text-slate-500">暂无待办截止日。</Card>}
                        {data.deadlines.map((d) => (
                            <Card key={d.project_id} className="p-3 flex items-center justify-between">
                                <div>
                                    <div className="text-sm font-medium">{d.title}</div>
                                    <div className="text-xs text-slate-500">{d.student?.name} · 截止 {fmt(d.deadline_at)}</div>
                                </div>
                                <Badge tone={d.days_left <= 3 ? 'red' : 'violet'}>还剩 {d.days_left} 天</Badge>
                            </Card>
                        ))}
                    </div>
                </section>

                {/* 待办 4：资格确认 */}
                <section>
                    <h2 className="text-lg font-bold mb-3">最终稿已提交 · 待确认答辩资格</h2>
                    <div className="space-y-2">
                        {data.final_pending.length === 0 && <Card className="p-4 text-sm text-slate-500">暂无待确认资格的课题。</Card>}
                        {data.final_pending.map((p) => (
                            <Card key={p.project_id} className="p-3 flex items-center justify-between">
                                <div>
                                    <div className="text-sm font-medium">{p.title}</div>
                                    <div className="text-xs text-slate-500">{p.student?.name}</div>
                                    {p.open_comment_count > 0 && <div className="mt-1"><Badge tone="amber">{p.open_comment_count} 条意见未关闭</Badge></div>}
                                </div>
                                <Link to={`/projects/${p.project_id}`}>
                                    <Button variant="success" className="text-xs py-1">审核最终稿</Button>
                                </Link>
                            </Card>
                        ))}
                    </div>
                </section>
            </div>
        </div>
    );
}

function StatCard({ label, count, tone, icon }) {
    const tones = {
        blue: 'border-sky-200 bg-sky-50',
        amber: 'border-amber-200 bg-amber-50',
        violet: 'border-violet-200 bg-violet-50',
        green: 'border-emerald-200 bg-emerald-50',
    };
    return (
        <Card className={`p-4 border ${tones[tone]}`}>
            <div className="text-2xl">{icon}</div>
            <div className="text-2xl font-bold mt-1">{count}</div>
            <div className="text-xs text-slate-600">{label}</div>
        </Card>
    );
}
