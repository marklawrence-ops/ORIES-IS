'use client';

import { useState, useEffect, type MouseEvent, use } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/axios';
import type { Tag } from '@/lib/types';

export default function EditDraft({ params }: { params: Promise<{ id: string }> }) {
    // 1. Unwrap the Promise to fix the Next.js Error!
    const resolvedParams = use(params);
    const deliverableId = resolvedParams.id;

    const router = useRouter();
    const [loading, setLoading] = useState(true);

    const [title, setTitle] = useState('');
    const [officeType, setOfficeType] = useState('extension');
    const [dueDate, setDueDate] = useState('');
    
    // Tagging Module State
    const [tags, setTags] = useState<{ type: string; value: string }[]>([]);
    const [tagType, setTagType] = useState('department');
    const [tagValue, setTagValue] = useState('');

    useEffect(() => {
        // Fetch the existing draft data using the unwrapped ID
        api.get(`/deliverables/${deliverableId}`).then((res) => {
            setTitle(res.data.title);
            setOfficeType(res.data.office_type);
            setDueDate(res.data.due_date);
            
            // Map the database tags back to the frontend format
            if (res.data.tags) {
                const existingTags = res.data.tags.map((t: Tag) => {
                    if (t.tagged_campus_id) return { type: 'campus', value: String(t.tagged_campus_id) };
                    if (t.tagged_department) return { type: 'department', value: t.tagged_department };
                    if (t.collaborator_name) return { type: 'faculty', value: t.collaborator_name };
                    return null;
                }).filter((t: { type: string; value: string } | null) => t !== null); // Remove any nulls
                setTags(existingTags);
            }
            setLoading(false);
        }).catch((err) => {
            console.error("Failed to fetch draft", err);
            setLoading(false);
        });
    }, [deliverableId]);

    const handleAddTag = () => {
        if (tagValue.trim() !== '') {
            setTags([...tags, { type: tagType, value: tagValue.trim() }]);
            setTagValue('');
        }
    };

    const handleRemoveTag = (indexToRemove: number) => {
        setTags(tags.filter((_, index) => index !== indexToRemove));
    };

    const handleUpdate = async (e: MouseEvent<HTMLButtonElement>, statusType: string) => {
        e.preventDefault();
        
        if (title.trim() === '' || dueDate === '') {
            alert("Please fill in the Proposal Title and Target Due Date.");
            return; 
        }

        setLoading(true);
        try {
            await api.put(`/deliverables/${deliverableId}`, {
                title,
                office_type: officeType,
                due_date: dueDate,
                status: statusType,
                tags
            });
            router.push('/head/dashboard');
        } catch (error) {
            console.error("Failed to update deliverable", error);
            alert("Update failed. Check console.");
            setLoading(false);
        }
    };

    if (loading) return <div className="p-8 text-center text-gray-600 font-medium mt-10">Loading Draft Data...</div>;

    return (
        <div className="min-h-screen bg-gray-50 p-8">
            <div className="mb-8">
                <h1 className="text-3xl font-bold text-gray-900">Edit Draft Deliverable</h1>
                <p className="text-gray-600">Update details and collaborators before submitting to the Campus Director.</p>
            </div>

            <form className="space-y-8 max-w-3xl">
                {/* 1. Core Deliverable Metadata */}
                <div className="rounded-lg bg-white p-6 shadow space-y-4 border-t-4 border-yellow-500">
                    <h2 className="text-xl font-semibold text-gray-800 border-b pb-2">1. General Information</h2>
                    
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Proposal / Deliverable Title</label>
                        <input
                            type="text"
                            required
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Office Type</label>
                            <select
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 p-2 border"
                                value={officeType}
                                onChange={(e) => setOfficeType(e.target.value)}
                            >
                                <option value="extension">Extension Services</option>
                                <option value="research">Research</option>
                                <option value="innovation">Innovation</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Target Due Date</label>
                            <input
                                type="date"
                                required
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 p-2 border"
                                value={dueDate}
                                onChange={(e) => setDueDate(e.target.value)}
                            />
                        </div>
                    </div>
                </div>

                {/* 2. Tagging Module */}
                <div className="rounded-lg bg-white p-6 shadow space-y-4 border-t-4 border-green-500">
                    <h2 className="text-xl font-semibold text-gray-800 border-b pb-2">2. Contributor Tagging</h2>
                    <p className="text-sm text-gray-500">Credit participating units, colleges, or individual collaborators.</p>
                    
                    <div className="flex gap-2 items-end">
                        <div className="w-1/3">
                            <label className="block text-xs font-medium text-gray-700">Tag Type</label>
                            <select
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 p-2 border"
                                value={tagType}
                                onChange={(e) => setTagType(e.target.value)}
                            >
                                <option value="department">Department / College</option>
                                <option value="campus">Campus</option>
                                <option value="faculty">Individual Faculty</option>
                            </select>
                        </div>
                        <div className="flex-1">
                            <label className="block text-xs font-medium text-gray-700">Contributor Name</label>
                            <input
                                type="text"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 p-2 border"
                                placeholder="e.g., College of Engineering"
                                value={tagValue}
                                onChange={(e) => setTagValue(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), handleAddTag())}
                            />
                        </div>
                        <button
                            type="button"
                            onClick={handleAddTag}
                            className="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm font-medium h-10"
                        >
                            Add Tag
                        </button>
                    </div>

                    {/* Render Active Tags */}
                    <div className="flex flex-wrap gap-2 mt-4">
                        {tags.length === 0 && <span className="text-sm text-gray-400 italic">No collaborators tagged yet.</span>}
                        {tags.map((tag, index) => (
                            <span key={index} className="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">
                                <span className="uppercase text-xs font-bold mr-1 opacity-75">[{tag.type}]</span> 
                                {tag.value}
                                <button
                                    type="button"
                                    onClick={() => handleRemoveTag(index)}
                                    className="ml-1 text-green-600 hover:text-green-900 font-bold"
                                >
                                    &times;
                                </button>
                            </span>
                        ))}
                    </div>
                </div>

                <div className="flex justify-end gap-4 pt-4">
                    <button type="button" onClick={() => router.back()} className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" onClick={(e) => handleUpdate(e, 'draft')} className="bg-gray-200 text-gray-800 px-6 py-2 rounded hover:bg-gray-300 font-medium">
                        Update Draft
                    </button>
                    <button type="button" onClick={(e) => handleUpdate(e, 'submitted')} className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 font-medium shadow">
                        Update & Submit to Director
                    </button>
                </div>
            </form>
        </div>
    );
}