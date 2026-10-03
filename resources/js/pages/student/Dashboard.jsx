import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api.js';
import { Badge, Button, Card, CommentStatusBadge, ErrorBanner, fmt, Spinner } from '../../ui.jsx';
import BookingPanel from '../BookingPanel.jsx';

export default function StudentDashboard() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    const load = async () => {
        const { data } = await api.get('/dashboard/student');
        setData(data);
        setLoading(false);
    };

    useEffect(() => { load(); }, []);

    if (loading) return <Spinner />;

    const openComments = data.projects.flatMap((p) =>
        p.open_comments.map((c) => ({ ...c, project: p }))
    );

    return (
        <div className="space-y-6">
            {/* 未处理意见 —— 学生首页最突出位置 */}
            <section>
                <div className="flex items-center justify-between mb-3">
                    <h2 className="text-lg font-bold flex items-center gap-2">
                        待我处理的导师意见
                        <Badge tone={data.total_open_comments > 0 ? 'red' : 'green'}>
                            {data.total_open_comments} 条未关闭
                        </Badge>
                    </h2>
                </div>

                {data.total_open_comments === 0 ? (
                    <Card className="p-6 text-center text-sm text-slate-500">
                        🎉 当前没有待处理的意见，等待导师反馈或享受你的进度吧。
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {openComments.map((c) => (
                            <Card key={c.id} className={`p-4 border-l-4 ${c.status === 'open' ? 'border-l-red-400' : 'border-l-amber-400'}`}>
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2 mb-1 text-xs text-slate-500">
                                            <Link to={`/projects/${c.project.id}`} className="font-medium text-indigo-600 hover:underline">
                                                {c.project.title}
                                            </Link>
                                            <span>·</span>
                                            <span>针对第 {c.guidance_record?.manuscript_version?.version_no ?? '?'} 版《{c.guidance_record?.manuscript_version?.title ?? ''}》</span>
                                        </div>
                                        <p className="text-sm text-slate-800">{c.content}</p>
                                        {c.responses?.length > 0 && (
                                            <div className="mt-2 text-xs text-slate-500 bg-slate-50 rounded-lg p-2">
                                                我最近的回应：{c.responses[c.responses.length - 1]?.content}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex flex-col items-end gap-2 shrink-0">
                                        <CommentStatusBadge status={c.status} />
                                        <Link to={`/projects/${c.project.id}?focus=${c.id}`}>
                                            <Button variant="secondary" className="text-xs">去修改 / 回应</Button>
                                        </Link>
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </section>

            <div className="grid lg:grid-cols-2 gap-6">
                {/* 我的课题 */}
                <section>
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-lg font-bold">我的课题</h2>
                        <Link to="/student/new-project"><Button variant="secondary" className="text-xs">+ 申报课题 / 选导师</Button></Link>
                    </div>
                    <div className="space-y-3">
                        {data.projects.length === 0 && <Card className="p-5 text-sm text-slate-500">还没有课题，先申报一个并选择导师。</Card>}
                        {data.projects.map((p) => (
                            <Card key={p.id} className="p-4">
                                <div className="flex items-start justify-between">
                                    <Link to={`/projects/${p.id}`} className="font-medium text-slate-800 hover:text-indigo-600">{p.title}</Link>
                                    {p.defense_eligible === true && <Badge tone="green">具备答辩资格</Badge>}
                                    {p.defense_eligible === false && <Badge tone="red">资格未通过</Badge>}
                                    {p.defense_eligible === null && <Badge tone="slate">资格待定</Badge>}
                                </div>
                                <div className="mt-2 text-xs text-slate-500 flex flex-wrap gap-x-4 gap-y-1">
                                    <span>导师：{p.advisor?.name || '未分配'}</span>
                                    <span>最新稿：{p.latest_version ? `v${p.latest_version.version_no}` : '未提交'}{p.latest_version?.status === 'withdrawn' && '（已撤回）'}</span>
                                    <span>截止：{p.deadline_at ? fmt(p.deadline_at) : '未设置'}</span>
                                </div>
                                {p.open_comment_count > 0 && (
                                    <div className="mt-2"><Badge tone="red">{p.open_comment_count} 条意见待处理</Badge></div>
                                )}
                            </Card>
                        ))}
                    </div>
                </section>

                {/* 预约 + 近期约谈 */}
                <section className="space-y-6">
                    <BookingPanel onBooked={load} />

                    <div>
                        <h2 className="text-lg font-bold mb-3">近期约谈</h2>
                        <div className="space-y-2">
                            {data.upcoming_bookings.length === 0 && <Card className="p-4 text-sm text-slate-500">暂无近期约谈。</Card>}
                            {data.upcoming_bookings.map((b) => (
                                <Card key={b.id} className="p-3 flex items-center justify-between">
                                    <div className="text-sm">
                                        <div className="font-medium">{fmt(b.slot.start_at)}</div>
                                        <div className="text-xs text-slate-500">{b.project?.title || '未关联课题'}</div>
                                        {b.questions && <div className="text-xs text-slate-400 mt-1">讨论问题：{b.questions}</div>}
                                    </div>
                                    <Badge tone={b.status === 'completed' ? 'green' : 'blue'}>
                                        {b.status === 'completed' ? '已完成' : '已预约'}
                                    </Badge>
                                </Card>
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </div>
    );
}
