'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState, type ReactNode } from 'react';
import api from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { formatDate, OFFICE_LABELS, ROLE_LABELS } from '@/lib/format';
import type { NotificationItem, Role } from '@/lib/types';

interface NavItem {
    href: string;
    label: string;
    icon: string;
    roles?: Role[];
    adminOnly?: boolean;
}

const NAV: { section?: string; items: NavItem[] }[] = [
    {
        items: [
            { href: '/dashboard', label: 'Dashboard', icon: '▦' },
            { href: '/projects?scope=mine', label: 'My Records', icon: '★', roles: ['faculty', 'head'] },
            { href: '/projects/new', label: 'Submit Proposal', icon: '+', roles: ['faculty', 'head'] },
            { href: '/projects', label: 'Search Records', icon: '⌕' },
            { href: '/verification', label: 'Verification Queue', icon: '✓', roles: ['staff', 'director'] },
            { href: '/certificates', label: 'Certificates', icon: '✪' },
            { href: '/reports', label: 'Reports', icon: '▤' },
            { href: '/org-chart', label: 'ORIES Org Chart', icon: '⧉' },
        ],
    },
    {
        section: 'Administration',
        items: [
            { href: '/admin/users', label: 'User Accounts', icon: '◉', adminOnly: true },
            { href: '/admin/targets', label: 'Office Targets', icon: '◎', roles: ['staff', 'director'] },
            { href: '/admin/storage', label: 'Storage & Drive Import', icon: '☁', roles: ['staff'] },
        ],
    },
];

export default function AppShell({ children }: { children: ReactNode }) {
    const { user, logout, hasRole } = useAuth();
    const pathname = usePathname();
    const [menuOpen, setMenuOpen] = useState(false);

    const isActive = (href: string) => {
        const path = href.split('?')[0];
        if (href.includes('scope=mine')) return false;
        return path === '/projects' ? pathname === '/projects' : pathname.startsWith(path);
    };

    const sidebar = (
        <nav className="flex h-full flex-col">
            <div className="border-b border-brand-800 px-5 py-4">
                <Link href="/dashboard" className="block">
                    <span className="block text-lg font-bold tracking-wide text-white">ORIES-IS</span>
                    <span className="block text-xs text-brand-100">Eastern Visayas State University</span>
                </Link>
            </div>
            <div className="flex-1 overflow-y-auto px-3 py-4">
                {NAV.map((group, i) => {
                    const items = group.items.filter((item) =>
                        item.adminOnly ? user.can_manage_users : !item.roles || hasRole(...item.roles),
                    );
                    if (items.length === 0) return null;
                    return (
                        <div key={i} className="mb-4">
                            {group.section && <p className="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-brand-200">{group.section}</p>}
                            {items.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setMenuOpen(false)}
                                    className={`mb-0.5 flex items-center gap-3 rounded-md px-3 py-2 text-sm ${
                                        isActive(item.href) ? 'bg-white/15 font-semibold text-white' : 'text-brand-50 hover:bg-white/10'
                                    }`}
                                >
                                    <span className="w-4 text-center" aria-hidden>{item.icon}</span>
                                    {item.label}
                                </Link>
                            ))}
                        </div>
                    );
                })}
            </div>
            <div className="border-t border-brand-800 px-5 py-4 text-xs text-brand-100">
                <p className="font-semibold text-white">{user.name}</p>
                <p>{ROLE_LABELS[user.role]}{user.office ? ` · ${OFFICE_LABELS[user.office]}` : ''}</p>
                {user.unit && <p className="truncate">{user.unit.name}</p>}
                <div className="mt-3 flex gap-3">
                    <Link href="/account" className="underline hover:text-white">Account</Link>
                    <button onClick={logout} className="underline hover:text-white">Log out</button>
                </div>
            </div>
        </nav>
    );

    return (
        <div className="flex min-h-screen">
            <aside className="no-print hidden w-64 shrink-0 bg-brand-700 lg:block"><div className="sticky top-0 h-screen">{sidebar}</div></aside>
            {menuOpen && (
                <div className="no-print fixed inset-0 z-40 lg:hidden" onClick={() => setMenuOpen(false)}>
                    <div className="absolute inset-0 bg-black/40" />
                    <aside className="absolute inset-y-0 left-0 w-64 bg-brand-700" onClick={(e) => e.stopPropagation()}>{sidebar}</aside>
                </div>
            )}
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="no-print sticky top-0 z-30 flex items-center justify-between border-b border-gray-200 bg-white/95 px-4 py-2 backdrop-blur sm:px-6">
                    <button className="rounded p-2 text-gray-700 lg:hidden" onClick={() => setMenuOpen(true)} aria-label="Open menu">☰</button>
                    <p className="hidden text-sm text-gray-500 sm:block">Office of Research, Innovation, and Extension Services</p>
                    <NotificationBell />
                </header>
                <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6">{children}</main>
            </div>
        </div>
    );
}

function NotificationBell() {
    const router = useRouter();
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<NotificationItem[]>([]);
    const [unread, setUnread] = useState(0);

    const load = () =>
        api.get('/notifications').then((res) => {
            setItems(res.data.items);
            setUnread(res.data.unread);
        }).catch(() => undefined);

    useEffect(() => {
        load();
        const timer = setInterval(load, 60_000);
        return () => clearInterval(timer);
    }, []);

    const openItem = async (item: NotificationItem) => {
        if (!item.read_at) await api.post(`/notifications/${item.id}/read`).catch(() => undefined);
        setOpen(false);
        load();
        if (item.link) router.push(item.link);
    };

    return (
        <div className="relative">
            <button onClick={() => setOpen(!open)} className="relative rounded-full p-2 text-gray-600 hover:bg-gray-100" aria-label="Notifications">
                🔔
                {unread > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 rounded-full bg-brand-600 px-1.5 text-[10px] font-bold text-white">{unread}</span>
                )}
            </button>
            {open && (
                <div className="absolute right-0 mt-2 w-80 rounded-lg border bg-white shadow-xl">
                    <div className="flex items-center justify-between border-b px-4 py-2">
                        <span className="text-sm font-semibold">Notifications</span>
                        {unread > 0 && (
                            <button className="text-xs text-brand-700 hover:underline" onClick={() => api.post('/notifications/read-all').then(load)}>
                                Mark all read
                            </button>
                        )}
                    </div>
                    <ul className="max-h-96 overflow-y-auto">
                        {items.length === 0 && <li className="px-4 py-6 text-center text-sm text-gray-500">No notifications yet.</li>}
                        {items.map((item) => (
                            <li key={item.id}>
                                <button onClick={() => openItem(item)} className={`block w-full border-b px-4 py-3 text-left hover:bg-gray-50 ${item.read_at ? '' : 'bg-brand-50/60'}`}>
                                    <p className="text-sm font-medium text-gray-900">{item.title}</p>
                                    {item.body && <p className="line-clamp-2 text-xs text-gray-600">{item.body}</p>}
                                    <p className="mt-1 text-[11px] text-gray-400">{formatDate(item.created_at, true)}</p>
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
