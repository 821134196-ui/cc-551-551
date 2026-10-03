import React, { useMemo, useRef, useState } from 'react';
import api from '../../api.js';
import { Button, Card, CommentStatusBadge, ErrorBanner } from '../../ui.jsx';

/**
 * 学生上传新稿：
 * - 自动基于当前最新版本号 +1；
 * - 可勾选未关闭的导师意见并逐条填写修改说明，回应会绑定到新稿版本。
 */
export default function StudentUpload({ project, onDone }) {
    const [title, setTitle] = useState('');
    const [note, setNote] = useState('');
    const [isFinal, setIsFinal] = useState(false);
    const [files, setFiles] = useState([]);
    const [responses, setResponses] = useState({}); // comment_id -> content
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);
    const [msg, setMsg] = useState(null);
    const fileRef = useRef();

    const openComments = useMemo(() => {
        const list = [];
        for (const r of project.guidance_records || []) {
            for (const c of r.comments || []) {
                if (c.status !== 'closed') {
                    list.push({ ...c, version_no: r.manuscript_version.version_no });
                }
            }
        }
        return list;
    }, [project]);

    const nextNo = Math.max(0, ...project.manuscript_versions.map((v) => v.version_no)) + 1;

    const submit = async () => {
        setError(null);
        setMsg(null);
        if (!title.trim()) { setError({ response: { data: { message: '请填写稿件标题' } } }); return; }
        if (files.length === 0) { setError({ response: { data: { message: '请至少选择一个稿件文件' } } }); return; }

        const fd = new FormData();
        fd.append('title', title);
        fd.append('note', note);
        fd.append('is_final', isFinal ? '1' : '0');
        files.forEach((f) => fd.append('files[]', f));

        Object.entries(responses).forEach(([commentId, content], i) => {
            if (content.trim()) {
                fd.append(`responses[${i}][comment_id]`, commentId);
                fd.append(`responses[${i}][content]`, content.trim());
            }
        });

        setBusy(true);
        try {
            await api.post(`/projects/${project.id}/manuscripts`, fd);
            setMsg(`第 ${nextNo} 版已提交，导师会收到通知。`);
            setTitle(''); setNote(''); setIsFinal(false); setResponses({}); setFiles([]);
            if (fileRef.current) fileRef.current.value = '';
            onDone();
        } catch (e) {
            setError(e);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Card className="p-4">
            <h2 className="text-base font-bold mb-3">提交新稿（第 {nextNo} 版）</h2>
            <ErrorBanner error={error} />
            {msg && <div className="mb-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs px-3 py-2">{msg}</div>}

            <div className="space-y-3">
                <input
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="稿件标题，如：毕业论文 v3"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
                <textarea
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    rows={2}
                    placeholder="本版修改说明（可选）"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">稿件 / 附件（旧版本会完整保留）</label>
                    <input
                        ref={fileRef}
                        type="file"
                        multiple
                        onChange={(e) => setFiles(Array.from(e.target.files))}
                        className="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={isFinal} onChange={(e) => setIsFinal(e.target.checked)} />
                    标记为最终稿（导师据此确认答辩资格）
                </label>

                {openComments.length > 0 && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50/50 p-3">
                        <div className="text-xs font-medium text-amber-800 mb-2">逐条回应未关闭的导师意见（回应将挂在本版）：</div>
                        <div className="space-y-3">
                            {openComments.map((c) => (
                                <div key={c.id}>
                                    <div className="flex items-start gap-2 mb-1">
                                        <CommentStatusBadge status={c.status} />
                                        <span className="text-xs text-slate-700 flex-1">
                                            <span className="text-slate-400">[针对 v{c.version_no}]</span> {c.content}
                                        </span>
                                    </div>
                                    <textarea
                                        rows={2}
                                        value={responses[c.id] || ''}
                                        onChange={(e) => setResponses((r) => ({ ...r, [c.id]: e.target.value }))}
                                        placeholder="说明本版如何处理该意见…"
                                        className="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs"
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <Button onClick={submit} disabled={busy} className="w-full justify-center">
                    {busy ? '上传中…' : `提交第 ${nextNo} 版`}
                </Button>
            </div>
        </Card>
    );
}
