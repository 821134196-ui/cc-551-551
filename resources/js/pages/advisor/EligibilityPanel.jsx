import React, { useState } from 'react';
import api from '../../api.js';
import { Badge, Button, Card, ErrorBanner } from '../../ui.jsx';

/**
 * 答辩资格确认：导师依据最终稿 + 未解决问题决定。
 * - 没有最终稿或仍有未关闭意见时，后端会阻止并提示；
 * - 学生端完全没有此面板（路由与策略双重限制）。
 */
export default function EligibilityPanel({ project, onDone }) {
    const [note, setNote] = useState('');
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);

    const finalVersion = (project.manuscript_versions || []).find((v) => v.is_final && v.status === 'active');
    const openCount = (project.guidance_records || [])
        .flatMap((r) => r.comments)
        .filter((c) => c.status !== 'closed').length;

    const decide = async (eligible, force = false) => {
        setError(null);
        setBusy(true);
        try {
            await api.post(`/projects/${project.id}/eligibility`, {
                defense_eligible: eligible,
                note: note || undefined,
                force,
            });
            onDone();
        } catch (e) {
            setError(e);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Card className="p-4">
            <h2 className="text-base font-bold mb-3">答辩资格确认</h2>
            <ErrorBanner error={error} />

            <div className="space-y-2 text-sm mb-3">
                <div className="flex items-center gap-2">
                    最终稿：{finalVersion
                        ? <Badge tone="green">已提交（第 {finalVersion.version_no} 版）</Badge>
                        : <Badge tone="red">尚未提交最终稿</Badge>}
                </div>
                <div className="flex items-center gap-2">
                    未关闭意见：
                    {openCount === 0 ? <Badge tone="green">0 条</Badge> : <Badge tone="amber">{openCount} 条待解决</Badge>}
                </div>
                <div>
                    当前结论：
                    {project.defense_eligible === true && <Badge tone="green">具备资格</Badge>}
                    {project.defense_eligible === false && <Badge tone="red">不具备资格</Badge>}
                    {project.defense_eligible === null && <Badge tone="slate">尚未确认</Badge>}
                </div>
            </div>

            <textarea
                value={note}
                onChange={(e) => setNote(e.target.value)}
                rows={2}
                placeholder="资格审核备注（可选）"
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm mb-3"
            />

            <div className="flex gap-2">
                <Button variant="success" className="flex-1 justify-center" disabled={busy} onClick={() => decide(true, false)}>确认具备资格</Button>
                <Button variant="danger" className="flex-1 justify-center" disabled={busy} onClick={() => decide(false, false)}>暂不具备</Button>
            </div>
            {openCount > 0 && finalVersion && (
                <Button variant="secondary" className="w-full justify-center mt-2 text-xs" disabled={busy} onClick={() => decide(true, true)}>
                    知悉仍有 {openCount} 条未关闭意见，仍确认资格
                </Button>
            )}
        </Card>
    );
}
