'use client';

import { useEffect, useState } from 'react';
import api from '@/lib/axios';
import PipelineTracker from '@/components/PipelineTracker';
import { useRouter } from 'next/navigation';
import Cookies from 'js-cookie';
import Link from 'next/link';

export default function HeadDashboard() {
    const router = useRouter();
    const [deliverables, setDeliverables] = useState<any[]>([]);
    const [activeTab, setActiveTab] = useState<'active' | 'completed'>('active');

    // State for the MOV Upload Modal
    const [isMovModalOpen, setIsMovModalOpen] = useState(false);
    const [selectedDeliverable, setSelectedDeliverable] = useState<any | null>(null);
    const [additionalLink, setAdditionalLink] = useState('');

    useEffect(() => {
        api.get('/deliverables').then(res => setDeliverables(res.data));
    }, []);

    const handleLogout = async () => {
        await api.post('/logout');
        Cookies.remove('ories_token');
        Cookies.remove('ories_role');
        router.push('/');
    };

    // Generic status update for drafts
    const updateStatus = async (id: number, newStatus: string) => {
        try {
            await api.patch(`/deliverables/${id}/status`, { status: newStatus });
            setDeliverables(deliverables.map(d => d.id === id ? { ...d, status: newStatus } : d));
        } catch (error) {
            console.error("Failed to update status", error);
        }
    };

    // Open the MOV Modal
    const openMovModal = (item: any) => {
        setSelectedDeliverable(item);
        setAdditionalLink(''); // Reset input
        setIsMovModalOpen(true);
    };

    // Submit the MOV Modal
    const handleMovSubmit = async () => {
        if (!selectedDeliverable) return;

        // Combine the auto-attached link with the new one if provided
        let finalLink = selectedDeliverable.mov_link || '';
        if (additionalLink.trim() !== '') {
            finalLink = finalLink ? `${finalLink} | Additional: ${additionalLink}` : additionalLink;
        }

        try {
            await api.patch(`/deliverables/${selectedDeliverable.id}/status`, { 
                status: 'mov_upload',
                mov_link: finalLink
            });
            setDeliverables(deliverables.map(d => 
                d.id === selectedDeliverable.id ? { ...d, status: 'mov_upload', mov_link: finalLink } : d
            ));
            setIsMovModalOpen(false);
        } catch (error) {
            console.error("Failed to forward", error);
            alert("Failed to upload MOVs.");
        }
    };

    const activeDeliverables = deliverables.filter(d => d.status !== 'vp_approved');
    const completedDeliverables = deliverables.filter(d => d.status === 'vp_approved');

    const renderDeliverables = (list: any[]) => {
        if (list.length === 0) {
            return (
                <div className="bg-gray-50 border-dashed border-2 border-gray-300 p-8 rounded-lg text-center text-gray-500">
                    No deliverables found in this category.
                </div>
            );
        }
        
        return list.map(item => (
            <div key={item.id} className="bg-white p-6 rounded-lg shadow mb-6 border border-gray-100">
                <div className="flex justify-between items-center mb-6 border-b pb-4">
                    <div>
                        <h2 className="text-xl font-bold text-gray-800">{item.title}</h2>
                        <span className="bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-1 rounded uppercase mt-2 inline-block">
                            {item.office_type}
                        </span>
                    </div>
                    <div className="text-sm text-gray-500 font-medium">
                        Due: {item.due_date}
                    </div>
                </div>
                
                <PipelineTracker currentStatus={item.status} />

                {/* Draft Status Action */}
                {item.status === 'draft' && (
                    <div className="mt-8 flex justify-end gap-3 border-t pt-4">
                        <Link 
                            href={`/head/edit/${item.id}`}
                            className="bg-gray-200 text-gray-800 px-4 py-2 rounded text-sm hover:bg-gray-300 font-medium transition-colors flex items-center">
                            ✎ Edit Draft
                        </Link>
                        <button 
                            onClick={() => updateStatus(item.id, 'submitted')}
                            className="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700 font-medium transition-colors">
                            Submit for Director Review
                        </button>
                    </div>
                )}

                {/* Director Endorsed Status Action (Triggers Modal) */}
                {item.status === 'director_endorsed' && (
                    <div className="mt-8 flex justify-end border-t pt-4">
                        <button 
                            onClick={() => openMovModal(item)}
                            className="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700 font-medium shadow-sm transition-colors">
                            Upload MOVs & Forward to VP
                        </button>
                    </div>
                )}
            </div>
        ));
    };

    return (
        <div className="min-h-screen bg-gray-50 p-8">
            <header className="mb-8 flex items-center justify-between border-b pb-4">
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">My Deliverables</h1>
                    <p className="text-gray-600">Department Head Dashboard</p>
                </div>
                <div className="flex gap-4">
                    <Link href="/head/ingestion-queue" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 font-medium flex items-center">
                        Ingestion Queue
                    </Link>
                    <Link href="/head/encode" className="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 font-medium flex items-center">
                        + Encode Deliverable
                    </Link>
                    <button onClick={handleLogout} className="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700">
                        Logout
                    </button>
                </div>
            </header>

            <div className="mb-6 border-b border-gray-200">
                <nav className="-mb-px flex space-x-8">
                    <button
                        onClick={() => setActiveTab('active')}
                        className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                            activeTab === 'active' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        Active Deliverables ({activeDeliverables.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('completed')}
                        className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                            activeTab === 'completed' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        Completed History ({completedDeliverables.length})
                    </button>
                </nav>
            </div>

            <div className="space-y-6">
                {activeTab === 'active' ? renderDeliverables(activeDeliverables) : renderDeliverables(completedDeliverables)}
            </div>

            {/* MOV Upload Modal */}
            {isMovModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-lg rounded-xl bg-white shadow-2xl overflow-hidden flex flex-col">
                        <div className="border-b bg-gray-50 px-6 py-4 flex justify-between items-center">
                            <h2 className="text-lg font-bold text-gray-800">Final Means of Verification (MOV)</h2>
                            <button onClick={() => setIsMovModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                                ✕
                            </button>
                        </div>
                        
                        <div className="p-6 space-y-4">
                            <div className="bg-blue-50 border border-blue-200 rounded p-4 text-blue-800 text-sm">
                                <strong>Auto-Attached:</strong> The original Google Drive file link from the ingestion queue is already attached and will be forwarded automatically.
                            </div>
                            
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Add Additional MOVs (Optional)
                                </label>
                                <input 
                                    type="text" 
                                    placeholder="Paste another Google Drive folder/file link here..."
                                    value={additionalLink}
                                    onChange={(e) => setAdditionalLink(e.target.value)}
                                    className="w-full border border-gray-300 rounded p-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                                />
                                <p className="text-xs text-gray-500 mt-2">
                                    If your deliverables require extra evidence (like attendance sheets or pictures), place them in a Google Drive folder and paste the link above.
                                </p>
                            </div>
                        </div>

                        <div className="border-t bg-gray-50 px-6 py-4 flex justify-end gap-3">
                            <button 
                                onClick={() => setIsMovModalOpen(false)}
                                className="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                            >
                                Cancel
                            </button>
                            <button 
                                onClick={handleMovSubmit}
                                className="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 shadow"
                            >
                                Submit & Forward to VP
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}