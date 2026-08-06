'use client';

import { useState, type MouseEvent } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import api from '@/lib/axios';


export default function EncodeDeliverable() {

    const searchParams = useSearchParams();
    const initialTitle = searchParams.get('title') || '';
    const initialLink = searchParams.get('link') || ''; // Catch the Google Drive URL

    const router = useRouter();
    const [loading, setLoading] = useState(false);

    // Core Deliverable State
    const [title, setTitle] = useState('');
    const [officeType, setOfficeType] = useState('extension');
    const [dueDate, setDueDate] = useState('');

    // Tagging Module State[cite: 9]
    const [tags, setTags] = useState<{ type: string; value: string }[]>([]);
    const [tagType, setTagType] = useState('department');
    const [tagValue, setTagValue] = useState('');

    const handleAddTag = () => {
        if (tagValue.trim() !== '') {
            setTags([...tags, { type: tagType, value: tagValue.trim() }]);
            setTagValue('');
        }
    };

    const handleRemoveTag = (indexToRemove: number) => {
        setTags(tags.filter((_, index) => index !== indexToRemove));
    };

    const handleSubmit = async (e: MouseEvent<HTMLButtonElement>, statusType: string) => {
            e.preventDefault();
            setLoading(true);
            
            try {
                const payload = {
                    title,
                    office_type: officeType,
                    due_date: dueDate,
                    mov_link: initialLink,
                    status: statusType,
                    tags
                };
                
                // Post to the newly created Laravel route
                await api.post('/deliverables', payload);
                
                router.push('/head/dashboard');
            } catch (error) {
                console.error("Failed to encode deliverable", error);
                alert("Failed to submit deliverable. Check console for details.");
                setLoading(false);
            }
        };

    return (
        <div className="min-h-screen bg-gray-50 p-8">
            <div className="mb-8">
                <h1 className="text-3xl font-bold text-gray-900">Encode Deliverable</h1>
                <p className="text-gray-600">Finalize details and tag collaborators for the ingested file[cite: 8, 9].</p>
            </div>

            <form className="space-y-8 max-w-3xl">
                {/* 1. Core Deliverable Metadata */}
                <div className="rounded-lg bg-white p-6 shadow space-y-4 border-t-4 border-blue-600">
                    <h2 className="text-xl font-semibold text-gray-800 border-b pb-2">1. General Information</h2>
                    
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Proposal / Deliverable Title</label>
                        <input
                            type="text"
                            required
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"
                            placeholder="Enter exact title (Jaro-Winkler check will run here later)"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Office Type</label>
                            <select
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"
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
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"
                                value={dueDate}
                                onChange={(e) => setDueDate(e.target.value)}
                            />
                        </div>
                    </div>
                </div>

                {/* 2. Tagging Module */}
                <div className="rounded-lg bg-white p-6 shadow space-y-4 border-t-4 border-green-500">
                    <h2 className="text-xl font-semibold text-gray-800 border-b pb-2">2. Contributor Tagging</h2>
                    <p className="text-sm text-gray-500">Credit participating units, colleges, or individual collaborators[cite: 8, 9].</p>
                    
                    <div className="flex gap-2 items-end">
                        <div className="w-1/3">
                            <label className="block text-xs font-medium text-gray-700">Tag Type</label>
                            <select
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm p-2 border"
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
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm p-2 border"
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
                    <button
                        type="button"
                        onClick={() => router.back()}
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        onClick={(e) => handleSubmit(e, 'draft')}
                        className="bg-gray-200 text-gray-800 px-6 py-2 rounded hover:bg-gray-300 font-medium transition-colors"
                    >
                        Save as Draft
                    </button>
                    <button
                        type="button" 
                        onClick={(e) => handleSubmit(e, 'submitted')}
                        className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 font-medium shadow transition-colors"
                        >
                        {loading ? 'Encoding...' : 'Save & Submit to Director'}
                    </button>
                </div>
            </form>
        </div>
    );
}