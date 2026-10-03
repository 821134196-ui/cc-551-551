import React, { useEffect, useState } from 'react';
import { api, fmt, getToken } from '../api';
import { useAuth } from '../app';
import Modal from './Modal';

export default function Submissions({ notify }) {
    const { user } = useAuth();
    const [versions, setVersions] = useState([]);
    const [studentFilter, setStudentFilter] = useState('');
    const [uploadOpen, setUploadOpen] = useState(false);
    const [detailId, setDetailId] = useState(null);
    const [detail, setDetail] = useState(null);

    const load = async () => {
        const path = studentFilter ? `/submissions?student_id=${studentFilter}` : '/submissions';
        setVersions((await api(path)).data);
    };

    useEffect(() => { load(); }, [studentFilter]);

    const openDetail = async (id) => {
        setDetailId(id);
        setDetail((await api(`/submissions/${id}`)).data);
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center gap-3 flex-wrap">
                <h2 className="text-lg font-bold text-slate-800">稿件版本与指导评语</h2>
                {user.role === 'student' && (
                    <button onClick={() => setUploadOpen(true)}
                        className="ml-auto bg-indigo-600 text-white text-sm rounded-lg px-4 py-2 hover:bg-indigo-700">
                        ＋ 提交新版本（可逐条回应意见）
                    </button>
                )}
                {user.role === 'advisor' && <AdvisorStudentPicker onPick={setStudentFilter} current={studentFilter} />}
            </div>

            {user.role === 'advisor' && studentFilter && (
                <AdvisorDefense studentId={studentFilter} notify={notify} />
            )}

            {/* 版本时间线 */}
            <div className="relative pl-8">
                <div className="absolute left-3 top-2 bottom-2 w-0.5 bg-slate-200" />
                {versions.map((s) => (
                    <div key={s.id} className="relative mb-6">
                        <span className={`absolute -left-[21px] top-1.5 w-3 h-3 rounded-full ${s.status === 'withdrawn' ? 'bg-red-400' : 'bg-indigo-500'}`} />
                        <div className="bg-white border rounded-xl p-4">
                            <div className="flex items-center gap-3 flex-wrap">
                                <span className="font-bold text-slate-700">第 {s.version} 版</span>
                                <span className="text-slate-800">《{s.title}》</span>
                                {s.status === 'withdrawn'
                                    ? <span className="text-xs bg-red-100 text-red-600 rounded-full px-2 py-0.5">已撤回（记录保留）</span>
                                    : <span className="text-xs bg-emerald-100 text-emerald-700 rounded-full px-2 py-0.5">有效版本</span>}
                                <span className="text-xs text-slate-400 ml-auto">{fmt(s.created_at)}</span>
                            </div>
                            {s.status === 'withdrawn' && s.withdraw_reason && (
                                <p className="text-xs text-red-500 mt-1">撤回原因：{s.withdraw_reason}（{fmt(s.withdrawn_at)}）</p>
                            )}
                            {s.note && <p className="text-sm text-slate-500 mt-2">{s.note}</p>}

                            <div className="mt-3 flex flex-wrap gap-3 text-xs text-slate-500">
                                <span>📎 {s.attachments?.length || 0} 个附件</span>
                                {s.responses?.length > 0 && <span>💬 {s.responses.length} 条意见回应</span>}
                                {s.guidance_sessions?.length > 0 && (
                                    <span className="text-indigo-600">📝 {s.guidance_sessions.length} 次指导评语引用本版</span>
                                )}
                            </div>

                            <div className="mt-3 flex gap-2 flex-wrap">
                                <button onClick={() => openDetail(s.id)} className="text-sm border rounded-lg px-3 py-1 hover:bg-slate-50">查看版本追溯</button>
                                {user.role === 'student' && s.status === 'submitted' && (
                                    <WithdrawButton submission={s} onDone={() => { notify('稿件已撤回，引用它的指导记录不受影响。'); load(); }} onError={(m) => notify(m, false)} />
                                )}
                            </div>
                        </div>
                    </div>
                ))}
                {versions.length === 0 && <p className="text-slate-400 text-sm">还没有稿件。</p>}
            </div>

            {user.role === 'student' && <DefenseBadge />}

            {uploadOpen && (
                <UploadModal
                    onClose={() => setUploadOpen(false)}
                    onDone={() => { setUploadOpen(false); notify('新版本已提交，回应已附在本版上。'); load(); }}
                    onError={(m) => notify(m, false)}
                />
            )}

            {detail && (
                <VersionDetail
                    detail={detail}
                    onClose={() => { setDetail(null); setDetailId(null); }}
                    onChanged={() => openDetail(detailId).then(load)}
                    notify={notify}
                />
            )}
        </div>
    );
}

function WithdrawButton({ submission, onDone, onError }) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const doIt = async () => {
        try {
            await api(`/submissions/${submission.id}/withdraw`, { method: 'POST', body: { reason } });
            setOpen(false);
            onDone();
        } catch (e) { onError(e.message); }
    };
    return (
        <>
            <button onClick={() => setOpen(true)} className="text-sm border border-red-300 text-red-600 rounded-lg px-3 py-1 hover:bg-red-50">撤回误传稿件</button>
            {open && (
                <Modal title={`撤回第 ${submission.version} 版`} onClose={() => setOpen(false)}>
                    <p className="text-sm text-slate-500">撤回后文件、附件及引用本版的指导记录全部保留，仅状态变为已撤回。</p>
                    <textarea rows={2} className="w-full border rounded-lg px-3 py-2 text-sm mt-3" placeholder="撤回原因（可选）" value={reason} onChange={(e) => setReason(e.target.value)} />
                    <div className="flex gap-2 mt-3 justify-end">
                        <button onClick={() => setOpen(false)} className="text-sm px-3 py-1.5">再想想</button>
                        <button onClick={doIt} className="text-sm bg-red-600 text-white rounded-lg px-4 py-1.5">确认撤回</button>
                    </div>
                </Modal>
            )}
        </>
    );
}

function UploadModal({ onClose, onDone, onError }) {
    const { user } = useAuth();
    const [title, setTitle] = useState('');
    const [note, setNote] = useState('');
    const [files, setFiles] = useState([]);
    const [pending, setPending] = useState([]);
    const [replies, setReplies] = useState({});

    useEffect(() => {
        api('/dashboard').then((d) => {
            setPending(d.pending_feedback);
        });
    }, []);

    const submit = async () => {
        const fd = new FormData();
        fd.append('title', title);
        if (note) fd.append('note', note);
        Array.from(files).forEach((f) => fd.append('files[]', f));
        pending.filter((f) => replies[f.id]?.trim()).forEach((f, i) => {
            fd.append(`responses[${i}][feedback_item_id]`, f.id);
            fd.append(`responses[${i}][reply]`, replies[f.id]);
        });
        try {
            await api('/submissions', { method: 'POST', body: fd, isForm: true });
            onDone();
        } catch (e) { onError(e.message); }
    };

    return (
        <Modal title="提交新稿件版本" onClose={onClose}>
            <div className="space-y-3">
                <input className="w-full border rounded-lg px-3 py-2 text-sm" placeholder="稿件标题" value={title} onChange={(e) => setTitle(e.target.value)} />
                <textarea rows={2} className="w-full border rounded-lg px-3 py-2 text-sm" placeholder="版本说明（改了什么）" value={note} onChange={(e) => setNote(e.target.value)} />
                <input type="file" multiple onChange={(e) => setFiles(e.target.files)} className="text-sm" />

                {pending.length > 0 && (
                    <div className="border-t pt-3">
                        <p className="text-sm font-medium text-slate-700">随本版逐条回应未处理意见（回应后由导师确认关闭）：</p>
                        <div className="space-y-3 mt-2">
                            {pending.map((f) => (
                                <div key={f.id} className="bg-slate-50 rounded-lg p-3">
                                    <p className="text-xs text-slate-500">针对第 {f.submission_version} 版 · #{f.sequence}（{f.status_label}）</p>
                                    <p className="text-sm text-slate-800 mt-0.5">{f.content}</p>
                                    <textarea rows={2} className="w-full border rounded-lg px-2 py-1 text-sm mt-2 bg-white"
                                        placeholder="说明本版如何修改…"
                                        value={replies[f.id] || ''} onChange={(e) => setReplies({ ...replies, [f.id]: e.target.value })} />
                                </div>
                            ))}
                        </div>
                    </div>
                )}
                <button onClick={submit} disabled={!title || files.length === 0}
                    className="w-full bg-indigo-600 text-white rounded-lg py-2 disabled:opacity-40">提交新版本</button>
            </div>
        </Modal>
    );
}

function VersionDetail({ detail, onClose, onChanged, notify }) {
    const { user } = useAuth();
    const token = getToken();

    const act = async (path) => {
        try {
            await api(path, { method: 'POST' });
            notify('操作成功。');
            onChanged();
        } catch (e) { notify(e.message, false); }
    };

    return (
        <Modal title={`第 ${detail.version} 版 · 版本追溯`} onClose={onClose}>
            <div className="space-y-4">
                <div>
                    <h4 className="text-sm font-semibold text-slate-700">附件（旧稿永久保留）</h4>
                    <ul className="mt-1 space-y-1">
                        {detail.attachments.map((a) => (
                            <li key={a.id}>
                                <a className="text-sm text-indigo-600 hover:underline"
                                    href={`${a.download_url}?api_token=${token}`}>
                                    📎 {a.name} <span className="text-slate-400">({Math.round(a.size / 1024)} KB)</span>
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>

                <div>
                    <h4 className="text-sm font-semibold text-slate-700">本版中的意见回应</h4>
                    {detail.responses.length === 0 ? <p className="text-xs text-slate-400 mt-1">无</p> : (
                        <ul className="mt-1 space-y-1">
                            {detail.responses.map((r) => (
                                <li key={r.id} className="text-sm text-slate-600">回应意见 #{r.feedback_sequence}：{r.reply}</li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="border-t pt-3">
                    <h4 className="text-sm font-semibold text-slate-700">
                        针对本版的指导评语
                        <span className="text-xs font-normal text-slate-400 ml-2">评语一经生成不可修改</span>
                    </h4>
                    {detail.guidance_sessions.length === 0 && <p className="text-xs text-slate-400 mt-1">本版暂无指导评语。</p>}
                    <div className="space-y-4 mt-2">
                        {detail.guidance_sessions.map((g) => (
                            <div key={g.id} className="border rounded-lg p-3">
                                <p className="text-xs text-slate-400">约谈时间 {fmt(g.appointment_at)}</p>
                                <p className="text-sm text-slate-800 mt-1">{g.overall_comment}</p>
                                <ul className="mt-2 space-y-2">
                                    {g.feedback_items.map((f) => (
                                        <li key={f.id} className="bg-slate-50 rounded-lg p-2.5">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="text-xs font-medium text-slate-500">#{f.sequence}</span>
                                                <FeedbackStatus status={f.status} />
                                                {user.role === 'advisor' && (
                                                    <span className="ml-auto flex gap-2">
                                                        {f.status !== 'closed' && (
                                                            <button onClick={() => act(`/feedback/${f.id}/close`)}
                                                                className="text-xs bg-emerald-600 text-white rounded px-2 py-0.5">确认关闭</button>
                                                        )}
                                                        {f.status === 'closed' && (
                                                            <button onClick={() => act(`/feedback/${f.id}/reopen`)}
                                                                className="text-xs border rounded px-2 py-0.5">重新打开</button>
                                                        )}
                                                    </span>
                                                )}
                                                {user.role === 'student' && f.status !== 'closed' && (
                                                    <span className="ml-auto text-[11px] text-slate-400">（仅导师可关闭）</span>
                                                )}
                                            </div>
                                            <p className="text-sm text-slate-800 mt-1">{f.content}</p>
                                            {f.responses.length > 0 && (
                                                <div className="mt-1.5 space-y-1">
                                                    {f.responses.map((r) => (
                                                        <p key={r.id} className="text-xs text-slate-600 bg-white rounded px-2 py-1 border">
                                                            学生回应（随第 {r.submission_version} 版提交，{fmt(r.created_at)}）：{r.reply}
                                                        </p>
                                                    ))}
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </Modal>
    );
}

function FeedbackStatus({ status }) {
    const map = {
        open: ['待处理', 'bg-red-100 text-red-700'],
        answered: ['已回应·待确认', 'bg-amber-100 text-amber-700'],
        closed: ['已关闭', 'bg-emerald-100 text-emerald-700'],
    };
    const [label, cls] = map[status];
    return <span className={`text-[11px] rounded-full px-2 py-0.5 ${cls}`}>{label}</span>;
}

function AdvisorStudentPicker({ onPick, current }) {
    const [list, setList] = useState([]);
    useEffect(() => {
        // 导师仪表盘有 student_count，但学生清单从 submissions 聚合；直接用 appointments
        api('/appointments').then((d) => {
            const map = new Map();
            d.data.forEach((a) => a.student && map.set(a.student.id, a.student.name));
            setList([...map.entries()].map(([id, name]) => ({ id, name })));
        });
    }, []);
    return (
        <select className="ml-auto border rounded-lg px-3 py-1.5 text-sm" value={current} onChange={(e) => onPick(e.target.value)}>
            <option value="">全部学生</option>
            {list.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </select>
    );
}

function AdvisorDefense({ studentId, notify }) {
    const [d, setD] = useState(null);
    const [eligible, setEligible] = useState(true);
    const [comment, setComment] = useState('');
    const versionsRef = React.useRef([]);

    const load = async () => {
        const r = await api(`/students/${studentId}/defense`);
        setD(r);
        const subs = await api(`/submissions?student_id=${studentId}`);
        versionsRef.current = subs.data.filter((s) => s.status === 'submitted');
        setFinalId(versionsRef.current.at(-1)?.id || '');
    };
    const [finalId, setFinalId] = useState('');
    useEffect(() => { load(); }, [studentId]);

    if (!d) return null;

    const decide = async () => {
        try {
            await api(`/students/${studentId}/defense`, {
                method: 'POST',
                body: { final_submission_id: Number(finalId), eligible, comment },
            });
            notify('答辩资格已确认（学生已收到通知）。');
            load();
        } catch (e) { notify(e.message, false); }
    };

    return (
        <div className="bg-white border rounded-xl p-4">
            <h3 className="text-sm font-semibold text-slate-700">答辩资格确认（仅导师可操作）</h3>
            <p className="text-xs text-slate-400 mt-1">
                系统将以所选最终稿 + 当前未关闭意见数（{d.current_unresolved_count}）作为判定依据记录。
            </p>
            {d.data && (
                <p className="text-sm mt-2">
                    当前记录：
                    {d.data.eligible
                        ? <span className="text-emerald-600 font-semibold">具备资格</span>
                        : <span className="text-amber-600 font-semibold">暂不具备</span>}
                    （第 {d.data.final_version} 版，未解决 {d.data.unresolved_count} 条，{fmt(d.data.decided_at)}）
                </p>
            )}
            <div className="flex gap-2 items-center mt-3 flex-wrap">
                <select className="border rounded-lg px-2 py-1.5 text-sm" value={finalId} onChange={(e) => setFinalId(e.target.value)}>
                    {versionsRef.current.map((s) => (
                        <option key={s.id} value={s.id}>最终稿：第 {s.version} 版《{s.title}》</option>
                    ))}
                </select>
                <select className="border rounded-lg px-2 py-1.5 text-sm" value={eligible ? 1 : 0}
                    onChange={(e) => setEligible(e.target.value === '1')}>
                    <option value={1}>确认具备资格</option>
                    <option value={0}>暂不具备</option>
                </select>
                <input className="border rounded-lg px-2 py-1.5 text-sm flex-1 min-w-40" placeholder="备注（可选）"
                    value={comment} onChange={(e) => setComment(e.target.value)} />
                <button onClick={decide} disabled={!finalId}
                    className="bg-indigo-600 text-white text-sm rounded-lg px-4 py-1.5 disabled:opacity-40">提交确认</button>
            </div>
        </div>
    );
}

function DefenseBadge() {
    const { user } = useAuth();
    const [d, setD] = useState(null);
    useEffect(() => { api(`/students/${user.id}/defense`).then((r) => setD(r)); }, []);
    if (!d) return null;
    return (
        <div className="bg-white border rounded-xl p-4 text-sm">
            <b className="text-slate-700">答辩资格：</b>
            {d.data ? (d.data.eligible
                ? <span className="text-emerald-600 font-semibold">已确认具备（依据第 {d.data.final_version} 版最终稿，{fmt(d.data.decided_at)}）</span>
                : <span className="text-amber-600">暂未通过（未解决意见记录数：{d.data.unresolved_count}）</span>)
                : <span className="text-slate-400">导师尚未确认</span>}
            <span className="text-slate-400 ml-3">当前未关闭意见：{d.current_unresolved_count}</span>
        </div>
    );
}
