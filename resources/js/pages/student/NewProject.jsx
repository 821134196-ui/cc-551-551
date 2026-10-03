import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../api.js';
import { Button, Card, ErrorBanner } from '../../ui.jsx';

export default function NewProject() {
    const navigate = useNavigate();
    const [advisors, setAdvisors] = useState([]);
    const [form, setForm] = useState({ title: '', description: '', advisor_id: '', deadline_at: '' });
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        api.get('/directory/advisors').then(({ data }) => setAdvisors(data));
    }, []);

    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

    const submit = async (e) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        try {
            const payload = {
                title: form.title,
                description: form.description || undefined,
                advisor_id: Number(form.advisor_id),
                deadline_at: form.deadline_at ? new Date(form.deadline_at).toISOString() : undefined,
            };
            const { data } = await api.post('/projects', payload);
            navigate(`/projects/${data.id}`);
        } catch (err) {
            setError(err);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-xl mx-auto">
            <h1 className="text-xl font-bold mb-4">申报课题 / 选择导师</h1>
            <Card className="p-6">
                <form onSubmit={submit} className="space-y-4">
                    <ErrorBanner error={error} />
                    <div>
                        <label className="block text-sm font-medium mb-1">课题题目</label>
                        <input value={form.title} onChange={set('title')} required className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">指导导师</label>
                        <select value={form.advisor_id} onChange={set('advisor_id')} required className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">— 请选择 —</option>
                            {advisors.map((a) => <option key={a.id} value={a.id}>{a.name}（{a.title || '导师'}）</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">课题简介</label>
                        <textarea value={form.description} onChange={set('description')} rows={4} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">期望完成时间（可选）</label>
                        <input type="datetime-local" value={form.deadline_at} onChange={set('deadline_at')} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <Button type="submit" disabled={loading}>{loading ? '提交中…' : '提交申报'}</Button>
                </form>
            </Card>
        </div>
    );
}
