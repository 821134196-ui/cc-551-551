import React, { useMemo, useRef, useState } from 'react';
import api from '../../api.js';
import { Badge, Button, Card, ErrorBanner, fmt } from '../../ui.jsx';

/**
 * 导师约谈后针对某一稿件版本创建指导记录。
 * 记录一经创建不可修改 —— 界面上也没有编辑入口。
 */
export default function AdvisorGuidance({ project, onDone }) {
    const versions = useMemo(
        () => [...(project.manuscript_versions || [])].sort((a, b) => b.version_no - a.version_no),
        [project.manuscript_versions],
    );
    const [versionId, setVersionId] = useState(versions[0]?.id || '');
    const [summary, setSummary] = useState('');
    const [commentTexts, setCommentTexts] = useState(['']);
    const [files, setFiles] = useState([]);
    const [error, setError] = useState(null);
    const [msg, setMsg] = useState(null);
    const [busy, setBusy] = useState(false);
    const fileRef = useRef();

    const bookings = (project.bookings || []).filter((b) => b.status === 'booked');
    const [bookingId, setBookingId] = useState('');

    const submit = async () => {
        setError(null);
        setMsg(null);
        const comments = commentTexts.map((t) => t.trim()).filter(Boolean);
        if (!versionId) { setError({ response: { data: { message: '请选择指导针对的稿件版本' } } }); return; }
        if (!summary.trim() || comments.length === 0) {
            setError({ response: { data: { message: '请填写总体意见和至少一条评语' } } });
            return;
        }

        const fd = new FormData();
        fd.append('manuscript_version_id', versionId);
        fd.append('summary', summary);
        if (bookingId) fd.append('booking_id', bookingId);
        comments.forEach((c, i) => fd.append(`comments[${i}]`, c));
        files.forEach((f) => fd.append('files[]', f));

        setBusy(true);
        try {
            await api.post(`/projects/${project.id}/guidance`, fd);
            setMsg('指导记录已创建（不可修改），学生已收到通知。');
            setSummary(''); setCommentTexts(['']); setFiles([]); setBookingId('');
            if (fileRef.current) fileRef.current.value = '';
            onDone();
        } catch (e) {
            setError(e);
        } finally {
            setBusy(false);
        }
    };

    if (versions.length === 0) {
        return <Card className="p-4 text-sm text-slate-500">学生尚未提交稿件，无法创建指导记录。</Card>;
    }

    return (
        <Card className="p-4">
            <h2 className="text-base font-bold mb-3">约谈后填写指导意见</h2>
            <ErrorBanner error={error} />
            {msg && <div className="mb-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs px-3 py-2">{msg}</div>}

            <div className="space-y-3">
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">本次指导针对的版本</label>
                    <select value={versionId} onChange={(e) => setVersionId(Number(e.target.value))} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        {versions.map((v) => (
                            <option key={v.id} value={v.id} disabled={v.status === 'withdrawn'}>
                                第 {v.version_no} 版《{v.title}》{v.status === 'withdrawn' ? '（已撤回）' : ''}{v.is_final ? ' · 最终稿' : ''}
                            </option>
                        ))}
                    </select>
                    <p className="text-[11px] text-slate-400 mt-1">记录会永久绑定该版本；即使学生撤回误传稿件，本记录也不会改变。</p>
                </div>

                {bookings.length > 0 && (
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">关联本次约谈（提交后自动标记完成）</label>
                        <select value={bookingId} onChange={(e) => setBookingId(e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">不关联具体约谈</option>
                            {bookings.map((b) => (
                                <option key={b.id} value={b.id}>{fmt(b.slot.start_at)} · {b.student?.name || ''}</option>
                            ))}
                        </select>
                    </div>
                )}

                <textarea
                    value={summary}
                    onChange={(e) => setSummary(e.target.value)}
                    rows={3}
                    placeholder="总体指导意见 / 约谈小结"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />

                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">逐条评语（学生需在下一版逐条回应）</label>
                    <div className="space-y-2">
                        {commentTexts.map((t, i) => (
                            <div key={i} className="flex gap-2">
                                <textarea
                                    value={t}
                                    rows={2}
                                    onChange={(e) => setCommentTexts((arr) => arr.map((x, j) => j === i ? e.target.value : x))}
                                    placeholder={`评语 ${i + 1}`}
                                    className="flex-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm"
                                />
                                {commentTexts.length > 1 && (
                                    <Button variant="ghost" onClick={() => setCommentTexts((arr) => arr.filter((_, j) => j !== i))}>✕</Button>
                                )}
                            </div>
                        ))}
                    </div>
                    <button type="button" onClick={() => setCommentTexts((arr) => [...arr, ''])} className="text-xs text-indigo-600 hover:underline mt-1">
                        + 添加一条评语
                    </button>
                </div>

                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">批注附件（可选，保留在本地）</label>
                    <input
                        ref={fileRef}
                        type="file"
                        multiple
                        onChange={(e) => setFiles(Array.from(e.target.files))}
                        className="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-violet-50 file:text-violet-700"
                    />
                </div>

                <Button variant="success" onClick={submit} disabled={busy} className="w-full justify-center">
                    {busy ? '提交中…' : '提交指导记录'}
                </Button>
            </div>
        </Card>
    );
}
