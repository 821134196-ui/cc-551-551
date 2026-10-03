import React from 'react';

export function Card({ children, className = '' }) {
    return <div className={`bg-white rounded-xl border border-slate-200 shadow-sm ${className}`}>{children}</div>;
}

export function Badge({ tone = 'slate', children }) {
    const tones = {
        slate: 'bg-slate-100 text-slate-700',
        red: 'bg-red-100 text-red-700',
        amber: 'bg-amber-100 text-amber-800',
        green: 'bg-emerald-100 text-emerald-700',
        blue: 'bg-sky-100 text-sky-700',
        violet: 'bg-violet-100 text-violet-700',
    };
    return <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${tones[tone]}`}>{children}</span>;
}

export function Button({ variant = 'primary', className = '', ...props }) {
    const variants = {
        primary: 'bg-indigo-600 text-white hover:bg-indigo-700',
        secondary: 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50',
        danger: 'bg-red-600 text-white hover:bg-red-700',
        success: 'bg-emerald-600 text-white hover:bg-emerald-700',
        ghost: 'text-slate-600 hover:bg-slate-100',
    };
    return (
        <button
            className={`inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed ${variants[variant]} ${className}`}
            {...props}
        />
    );
}

export function Spinner() {
    return (
        <div className="flex justify-center py-10">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-600" />
        </div>
    );
}

export function ErrorBanner({ error }) {
    if (!error) return null;
    const text = typeof error === 'string'
        ? error
        : error?.response?.data?.message
          || Object.values(error?.response?.data?.errors || {}).flat().join('；')
          || '请求失败，请重试。';
    return <div className="mb-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">{text}</div>;
}

export function fmt(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('zh-CN', { hour12: false });
}

export function fmtDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleDateString('zh-CN');
}

export function CommentStatusBadge({ status }) {
    if (status === 'closed') return <Badge tone="green">已关闭</Badge>;
    if (status === 'resolved') return <Badge tone="amber">学生已回应·待确认</Badge>;
    return <Badge tone="red">待处理</Badge>;
}

/** 带鉴权头的附件下载地址（用隐藏 a 标签携带 token 不便，这里走 fetch+blob） */
export async function downloadAttachment(api, att) {
    const res = await api.get(`/attachments/${att.id}/download`, { responseType: 'blob' });
    const url = URL.createObjectURL(res.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = att.original_name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}
