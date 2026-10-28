'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import api from '@/lib/api';
import { OFFICE_LABELS, STAGE_LABELS } from '@/lib/format';
import type { Campus, DuplicateMatch, PersonRef, Unit } from '@/lib/types';
import { Alert, Button, Input, Select } from './ui';

export interface MemberDraft {
    user_id?: number;
    name: string;
}

export interface TagDraft {
    type: 'unit' | 'campus' | 'partner';
    unit_id?: number;
    campus_id?: number;
    label: string;
}

/**
 * Adds co-proponents from existing accounts (so they are credited in reports) or by name.
 */
export function MemberPicker({ members, onChange }: { members: MemberDraft[]; onChange: (m: MemberDraft[]) => void }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<(PersonRef & { unit?: Unit | null })[]>([]);

    useEffect(() => {
        if (query.trim().length < 2) return;
        const timer = setTimeout(() => {
            api.get('/people', { params: { q: query } }).then((res) => setResults(res.data)).catch(() => setResults([]));
        }, 250);
        return () => clearTimeout(timer);
    }, [query]);

    const add = (member: MemberDraft) => {
        if (!members.some((m) => (member.user_id ? m.user_id === member.user_id : m.name === member.name))) {
            onChange([...members, member]);
        }
        setQuery('');
        setResults([]);
    };

    return (
        <div>
            <div className="relative">
                <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search EVSU personnel by name…" />
                {query.trim().length >= 2 && (
                    <div className="absolute z-10 mt-1 w-full rounded-md border bg-white shadow-lg">
                        {results.map((person) => (
                            <button key={person.id} type="button" className="block w-full px-3 py-2 text-left text-sm hover:bg-brand-50" onClick={() => add({ user_id: person.id, name: person.name })}>
                                {person.name} <span className="text-xs text-gray-500">{person.unit?.code ?? ''} {person.position ?? ''}</span>
                            </button>
                        ))}
                        <button type="button" className="block w-full border-t px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50" onClick={() => add({ name: query.trim() })}>
                            Add “{query.trim()}” as a collaborator without an account
                        </button>
                    </div>
                )}
            </div>
            {members.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                    {members.map((m, i) => (
                        <span key={`${m.name}-${i}`} className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-sm">
                            {m.name}
                            {!m.user_id && <span className="text-xs text-gray-500">(external)</span>}
                            <button type="button" className="ml-1 text-gray-500 hover:text-red-600" onClick={() => onChange(members.filter((_, j) => j !== i))}>&times;</button>
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * Contributor tagging: credits colleges/units, campuses, and partner agencies.
 */
export function TagPicker({ tags, onChange, campuses }: { tags: TagDraft[]; onChange: (t: TagDraft[]) => void; campuses: Campus[] }) {
    const [type, setType] = useState<TagDraft['type']>('unit');
    const [value, setValue] = useState('');
    const units = campuses.flatMap((c) => (c.units ?? []).map((u) => ({ ...u, campusName: c.name })));

    const add = () => {
        if (!value.trim()) return;
        let tag: TagDraft;
        if (type === 'unit') {
            const unit = units.find((u) => String(u.id) === value);
            if (!unit) return;
            // Same form the server stores: units on different campuses can share a name
            tag = { type, unit_id: unit.id, label: `${unit.name} (${unit.campusName})` };
        } else if (type === 'campus') {
            const campus = campuses.find((c) => String(c.id) === value);
            if (!campus) return;
            tag = { type, campus_id: campus.id, label: campus.name };
        } else {
            tag = { type, label: value.trim() };
        }
        if (!tags.some((t) => t.type === tag.type && t.label === tag.label)) onChange([...tags, tag]);
        setValue('');
    };

    return (
        <div>
            <div className="flex flex-wrap gap-2">
                <Select value={type} onChange={(e) => { setType(e.target.value as TagDraft['type']); setValue(''); }} className="w-auto">
                    <option value="unit">College / Unit</option>
                    <option value="campus">Campus</option>
                    <option value="partner">Partner agency</option>
                </Select>
                <div className="min-w-48 flex-1">
                    {type === 'unit' && (
                        <Select value={value} onChange={(e) => setValue(e.target.value)}>
                            <option value="">Select a college or unit…</option>
                            {campuses.map((c) => (
                                <optgroup key={c.id} label={c.name}>
                                    {(c.units ?? []).map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                                </optgroup>
                            ))}
                        </Select>
                    )}
                    {type === 'campus' && (
                        <Select value={value} onChange={(e) => setValue(e.target.value)}>
                            <option value="">Select a campus…</option>
                            {campuses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </Select>
                    )}
                    {type === 'partner' && (
                        <Input value={value} onChange={(e) => setValue(e.target.value)} placeholder="e.g., DOST Region VIII, Tacloban City LGU"
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } }} />
                    )}
                </div>
                <Button type="button" variant="secondary" onClick={add}>Add tag</Button>
            </div>
            {tags.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                    {tags.map((t, i) => (
                        <span key={`${t.type}-${t.label}`} className="inline-flex items-center gap-1 rounded-full bg-green-50 px-3 py-1 text-sm text-green-800">
                            <span className="text-[10px] font-bold uppercase opacity-70">{t.type}</span> {t.label}
                            <button type="button" className="ml-1 hover:text-red-600" onClick={() => onChange(tags.filter((_, j) => j !== i))}>&times;</button>
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * Real-time Crosschecking Module warning shown while a proposal title is typed.
 */
export function DuplicateWarning({ title, ignoreId }: { title: string; ignoreId?: number }) {
    const [matches, setMatches] = useState<DuplicateMatch[]>([]);

    useEffect(() => {
        if (title.trim().length < 12) return;
        const timer = setTimeout(() => {
            api.post('/projects/check-title', { title, ignore_id: ignoreId })
                .then((res) => setMatches(res.data.matches))
                .catch(() => setMatches([]));
        }, 500);
        return () => clearTimeout(timer);
    }, [title, ignoreId]);

    if (title.trim().length < 12 || matches.length === 0) return null;

    return (
        <div className="mt-2">
            <Alert tone="amber">
                <p className="font-medium">Possible duplicate title{matches.length > 1 ? 's' : ''} found</p>
                <p className="text-xs">You may still submit. ORIES staff will review the similarity before verifying your proposal.</p>
                <DuplicateMatches matches={matches} />
            </Alert>
        </div>
    );
}

export function DuplicateMatches({ matches }: { matches: DuplicateMatch[] }) {
    return (
        <ul className="mt-2 space-y-1">
            {matches.map((m) => (
                <li key={m.project_id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <Link href={`/projects/${m.project_id}`} target="_blank" className="underline">{m.title}</Link>
                    <span className="text-xs">
                        {OFFICE_LABELS[m.office]} · {STAGE_LABELS[m.stage]} · <strong>{Math.round(m.score * 100)}% similar</strong>
                    </span>
                </li>
            ))}
        </ul>
    );
}
