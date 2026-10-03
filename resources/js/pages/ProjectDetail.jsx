import React, { useCallback, useEffect, useState } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import api from '../api.js';
import { useAuth } from '../auth.jsx';
import { Badge, Button, Card, CommentStatusBadge, ErrorBanner, fmt, Spinner, downloadAttachment } from '../ui.jsx';
import StudentUpload from './student/StudentUpload.jsx';
import AdvisorGuidance from './advisor/AdvisorGuidance.jsx';
import EligibilityPanel from './advisor/EligibilityPanel.jsx';

export default function ProjectDetail() {
    const { id } = useParams();
    const [searchParams] = useSearchParams();
    const focusComment = searchParams.get('focus');
    const { user } = useAuth();
    const [project, setProject] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const load = useCallback(async () => {
        try {
            const { data } = await api.get(`/projects/${id}`);
            setProject(data);
            setError(null);
        } catch (e) {
            setError(e);
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => { load(); }, [load]);

    if (loading) return <Spinner />;
    if (error) return <ErrorBanner error={error} />;
    if (!project) return null;

    const isStudent = user.role === 'student';
    const sortedVersions = [...project.manuscript_versions].sort((a, b) => b.version_no - a.version_no);

    return (
        <div className="space-y-5">
            {/* 课题头部 */}
            <Card className="p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold">{project.title}</h1>
                        <div className="mt-1 text-sm text-slate-500 flex flex-wrap gap-x-4">
                            <span>学生：{project.student.name}（{project.student.student_no}）</span>
                            <span>导师：{project.advisor?.name || '未分配'}</span>
                            <span>截止日：{project.deadline_at ? fmt(project.deadline_at) : '未设置'}</span>
                        </div>
                        {project.description && <p className="mt-2 text-sm text-slate-600">{project.description}</p>}
                    </div>
                    <div>
                        {project.defense_eligible === true && <Badge tone="green">✅ 已确认具备答辩资格</Badge>}
                        {project.defense_eligible === false && <Badge tone="red">❌ 暂不具备答辩资格</Badge>}
                        {project.defense_eligible === null && <Badge tone="slate">答辩资格未确认</Badge>}
                    </div>
                </div>
            </Card>

            <div className="grid lg:grid-cols-3 gap-5">
                {/* 左：版本与指导时间线 */}
                <div className="lg:col-span-2 space-y-5">
                    <h2 className="text-lg font-bold">稿件版本与指导时间线</h2>
                    {sortedVersions.map((v) => <VersionCard key={v.id} version={v} project={project} isStudent={isStudent} onChanged={load} focusComment={focusComment} />)}

                    {/* 历史指导记录（同样在版本卡内展示，这里单独汇总不可变记录） */}
                    <h2 className="text-lg font-bold pt-2">指导记录（创建后不可修改）</h2>
                    {project.guidance_records.length === 0 && <Card className="p-4 text-sm text-slate-500">暂无指导记录。</Card>}
                    {project.guidance_records.map((r) => (
                        <Card key={r.id} className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500 mb-2">
                                <span>🧑‍🏫 {r.advisor.name} · {fmt(r.created_at)}</span>
                                <Badge tone="violet">针对第 {r.manuscript_version.version_no} 版《{r.manuscript_version.title}》{r.manuscript_version.status === 'withdrawn' && '（该版已撤回，记录保留）'}</Badge>
                            </div>
                            <p className="text-sm text-slate-800 mb-3">{r.summary}</p>
                            <div className="space-y-2">
                                {r.comments.map((c) => <CommentRow key={c.id} comment={c} isStudent={isStudent} onChanged={load} focused={String(c.id) === focusComment} />)}
                            </div>
                            {r.attachments.length > 0 && (
                                <div className="mt-3 pt-3 border-t border-slate-100">
                                    <div className="text-xs text-slate-500 mb-1">指导附件：</div>
                                    {r.attachments.map((a) => (
                                        <button key={a.id} onClick={() => downloadAttachment(api, a)} className="text-xs text-indigo-600 hover:underline mr-3">📎 {a.original_name}</button>
                                    ))}
                                </div>
                            )}
                        </Card>
                    ))}
                </div>

                {/* 右：操作区 */}
                <div className="space-y-5">
                    {isStudent
                        ? <StudentUpload project={project} onDone={load} />
                        : <AdvisorGuidance project={project} onDone={load} />}
                    {!isStudent && <EligibilityPanel project={project} onDone={load} />}
                </div>
            </div>
        </div>
    );
}

function VersionCard({ version: v, project, isStudent, onChanged, focusComment }) {
    const [busy, setBusy] = useState(false);
    const [err, setErr] = useState(null);

    const withdraw = async () => {
        const reason = prompt('请输入撤回原因（撤回后该版标记为误传，但记录仍保留）：');
        if (!reason) return;
        setBusy(true);
        setErr(null);
        try {
            await api.post(`/manuscripts/${v.id}/withdraw`, { reason });
            onChanged();
        } catch (e) { setErr(e); } finally { setBusy(false); }
    };

    const referenced = project.guidance_records.some((r) => r.manuscript_version_id === v.id);

    return (
        <Card className={`p-4 ${v.status === 'withdrawn' ? 'opacity-70' : ''}`}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-bold text-slate-800">v{v.version_no}</span>
                        <span className="text-sm text-slate-700">{v.title}</span>
                        {v.is_final && <Badge tone="green">最终稿</Badge>}
                        {v.status === 'withdrawn' && <Badge tone="amber">已撤回（误传）</Badge>}
                        {referenced && <Badge tone="slate">已被指导记录引用</Badge>}
                    </div>
                    <div className="text-xs text-slate-400 mt-1">{fmt(v.created_at)} · {v.note}</div>
                    {v.status === 'withdrawn' && v.withdraw_reason && (
                        <div className="text-xs text-amber-700 mt-1">撤回原因：{v.withdraw_reason}</div>
                    )}
                </div>
                {isStudent && v.status === 'active' && !referenced && (
                    <Button variant="ghost" className="text-xs text-red-600" disabled={busy} onClick={withdraw}>撤回误传</Button>
                )}
            </div>

            <ErrorBanner error={err} />

            {v.attachments.length > 0 && (
                <div className="mt-2">
                    {v.attachments.map((a) => (
                        <button key={a.id} onClick={() => downloadAttachment(api, a)} className="text-xs text-indigo-600 hover:underline mr-3">📄 {a.original_name}</button>
                    ))}
                </div>
            )}

            {/* 该版中对旧评语的回应 */}
            {v.responses?.length > 0 && (
                <div className="mt-3 rounded-lg bg-slate-50 p-3 space-y-2">
                    <div className="text-xs font-medium text-slate-500">本版对导师评语的逐条回应：</div>
                    {v.responses.map((resp) => (
                        <div key={resp.id} className={`text-xs ${String(resp.comment?.id) === focusComment ? 'ring-2 ring-amber-300 rounded p-1' : ''}`}>
                            <span className="text-slate-400 line-through block">原意见：{resp.comment?.content}</span>
                            <span className="text-emerald-700">↳ 修改说明：{resp.content}</span>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

function CommentRow({ comment: c, isStudent, onChanged, focused }) {
    const [busy, setBusy] = useState(false);

    const act = async (url) => {
        setBusy(true);
        try {
            await api.post(url);
            onChanged();
        } finally { setBusy(false); }
    };

    return (
        <div className={`rounded-lg border p-3 ${focused ? 'border-amber-400 bg-amber-50/50' : 'border-slate-200'}`}>
            <div className="flex items-start justify-between gap-2">
                <p className="text-sm text-slate-800">💬 {c.content}</p>
                <CommentStatusBadge status={c.status} />
            </div>
            {c.responses?.length > 0 && (
                <div className="mt-2 space-y-1">
                    {c.responses.map((r) => (
                        <div key={r.id} className="text-xs text-slate-600 bg-white rounded p-2 border border-slate-100">
                            <span className="text-slate-400">{r.student?.name} 在新稿中回应：</span>{r.content}
                        </div>
                    ))}
                </div>
            )}
            {!isStudent && c.status !== 'closed' && (
                <div className="mt-2 flex gap-2">
                    <Button variant="success" className="text-xs py-1" disabled={busy} onClick={() => act(`/comments/${c.id}/close`)}>确认修改到位·关闭</Button>
                    {c.status === 'resolved' && (
                        <Button variant="danger" className="text-xs py-1" disabled={busy} onClick={() => act(`/comments/${c.id}/reopen`)}>仍需修改·退回</Button>
                    )}
                </div>
            )}
            {c.status === 'closed' && !isStudent && (
                <div className="mt-2">
                    <Button variant="ghost" className="text-xs py-1" disabled={busy} onClick={() => act(`/comments/${c.id}/reopen`)}>重新打开</Button>
                </div>
            )}
        </div>
    );
}
