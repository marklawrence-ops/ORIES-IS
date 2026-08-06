'use client';

import React from 'react';

interface Tag {
    id: number;
    tagged_campus_id?: number | null;
    tagged_department?: string | null;
    collaborator_name?: string | null;
    type?: string;
    tag_type?: string;
    name?: string;
    tag_name?: string;
    entity_name?: string;
}

interface Deliverable {
    id: number;
    title: string;
    due_date: string;
    office_type: string;
    status: string;
    mov_link?: string;
    tags?: Tag[];
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    deliverable: Deliverable | null;
}

export default function DeliverableModal({ isOpen, onClose, deliverable }: Props) {
    if (isOpen && deliverable) {
        console.log("TAG DATA FROM LARAVEL:", deliverable.tags);
    }

    if (!isOpen || !deliverable) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div className="w-full max-w-2xl rounded-xl bg-white shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                
                {/* Modal Header */}
                <div className="flex items-center justify-between border-b bg-gray-50 px-6 py-4">
                    <h2 className="text-xl font-bold text-gray-800">Deliverable Details</h2>
                    <button 
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 focus:outline-none"
                    >
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Modal Body */}
                <div className="overflow-y-auto px-6 py-6 space-y-6">
                    <div>
                        <h3 className="text-2xl font-bold text-gray-900">{deliverable.title}</h3>
                        <div className="mt-2 flex gap-3">
                            <span className="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded uppercase">
                                {deliverable.office_type}
                            </span>
                            <span className="bg-gray-100 text-gray-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                Due: {deliverable.due_date}
                            </span>
                        </div>
                    </div>

                    {/* Contributor Tags Section */}
                    <div>
                        <h4 className="text-sm font-semibold uppercase text-gray-500 border-b pb-1 mb-3">Tagged Contributors</h4>
                        {deliverable.tags && deliverable.tags.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {deliverable.tags.map((tag) => {
                                    // Determine what kind of tag this is based on which column is not null
                                    let tagType = 'Unknown';
                                    let tagValue = '';

                                    if (tag.tagged_campus_id) {
                                        tagType = 'Campus';
                                        // Note: Since this is an ID (like 1), you might want to map it to the actual name later!
                                        tagValue = `ID: ${tag.tagged_campus_id}`; 
                                    } else if (tag.tagged_department) {
                                        tagType = 'Department';
                                        tagValue = tag.tagged_department;
                                    } else if (tag.collaborator_name) {
                                        tagType = 'Faculty';
                                        tagValue = tag.collaborator_name;
                                    }

                                    return (
                                        <span key={tag.id} className="inline-flex items-center gap-1 rounded-full bg-green-50 px-3 py-1 text-sm text-green-700 border border-green-200">
                                            <span className="font-semibold">{tagType}:</span> {tagValue}
                                        </span>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500 italic">No specific contributors tagged.</p>
                        )}
                    </div>

                    {/* Means of Verification (MOVs) Section */}
                    <div>
                        <h4 className="text-sm font-semibold uppercase text-gray-500 border-b pb-1 mb-3">Means of Verification</h4>
                        {deliverable.mov_link ? (
                            <a 
                                href={deliverable.mov_link} 
                                target="_blank" 
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 rounded bg-indigo-50 px-4 py-2 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 font-medium transition-colors"
                            >
                                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                Open Google Drive Folder
                            </a>
                        ) : (
                            <div className="rounded bg-yellow-50 p-4 border border-yellow-200">
                                <p className="text-sm text-yellow-700 font-medium">No external MOVs attached to this record.</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Modal Footer */}
                <div className="border-t bg-gray-50 px-6 py-4 flex justify-end">
                    <button 
                        onClick={onClose}
                        className="rounded border border-gray-300 bg-white px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
}