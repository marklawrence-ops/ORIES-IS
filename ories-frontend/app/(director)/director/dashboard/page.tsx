'use client';

import { useEffect, useState } from 'react';
import api from '@/lib/axios';
import type { Deliverable } from '@/lib/types';
import { useRouter } from 'next/navigation';
import Cookies from 'js-cookie';
import DeliverableModal from '@/components/DeliverableModal';

export default function DirectorDashboard() {
    const router = useRouter();
    const [deliverables, setDeliverables] = useState<Deliverable[]>([]);
    
    // Modal State
    const [selectedDeliverable, setSelectedDeliverable] = useState<Deliverable | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);

    useEffect(() => {
        api.get('/deliverables').then(res => setDeliverables(res.data));
    }, []);

    const handleEndorse = async (id: number) => {
        try {
            // Push the status forward to Stage 3
            await api.patch(`/deliverables/${id}/status`, { status: 'director_endorsed' });
            
            // Update UI optimistically
            setDeliverables(deliverables.map(d => d.id === id ? { ...d, status: 'director_endorsed' } : d));
        } catch (error) {
            console.error("Failed to endorse", error);
            alert("Failed to endorse deliverable.");
        }
    };

    const handleLogout = async () => {
        await api.post('/logout');
        Cookies.remove('ories_token');
        Cookies.remove('ories_role');
        router.push('/');
    };

    const openModal = (deliverable: Deliverable) => {
        setSelectedDeliverable(deliverable);
        setIsModalOpen(true);
    };

    // Filter logic specific to the Director's view
    const pendingDeliverables = deliverables.filter(d => d.status === 'submitted');
    
    // History includes anything that has successfully passed the Director's desk
    const historyDeliverables = deliverables.filter(d => 
        ['director_endorsed', 'mov_upload', 'vp_approved'].includes(d.status)
    );

    return (
        <div className="min-h-screen bg-gray-50 p-8">
            {/* Header */}
            <header className="mb-8 flex items-center justify-between border-b pb-4">
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">Campus Director Dashboard</h1>
                    <p className="text-gray-600">Department Deliverable Review & Endorsement</p>
                </div>
                <button onClick={handleLogout} className="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 font-medium">
                    Logout
                </button>
            </header>

            {/* KPI Summary Cards */}
            <div className="mb-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="bg-white p-6 rounded-lg shadow border-l-4 border-yellow-500">
                    <h3 className="text-sm font-medium text-gray-500 uppercase">Pending Review</h3>
                    <p className="text-3xl font-bold text-gray-900 mt-2">{pendingDeliverables.length}</p>
                </div>
                <div className="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                    <h3 className="text-sm font-medium text-gray-500 uppercase">Total Endorsed</h3>
                    <p className="text-3xl font-bold text-gray-900 mt-2">{historyDeliverables.length}</p>
                </div>
                <div className="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
                    <h3 className="text-sm font-medium text-gray-500 uppercase">Campus Status</h3>
                    <p className="text-xl font-bold text-green-600 mt-2">Operations Normal</p>
                </div>
            </div>
            
            {/* Active Action Queue */}
            <div className="space-y-4 mb-12">
                <h2 className="text-xl font-semibold text-gray-800 border-b pb-2">Action Required</h2>
                {pendingDeliverables.map(item => (
                    <div key={item.id} className="bg-white p-6 rounded-lg shadow flex flex-col md:flex-row justify-between items-start md:items-center border border-gray-100">
                        <div className="mb-4 md:mb-0">
                            <div className="flex items-center gap-3">
                                <h3 className="text-lg font-bold text-gray-900">{item.title}</h3>
                                <span className="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded uppercase">
                                    {item.office_type}
                                </span>
                            </div>
                            <p className="text-sm text-gray-500 mt-1">Due: {item.due_date}</p>
                        </div>
                        <div className="flex gap-3 w-full md:w-auto">
                            <button 
                                onClick={() => openModal(item)}
                                className="flex-1 md:flex-none border border-gray-300 bg-white text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-50 font-medium transition-colors">
                                View Details & MOVs
                            </button>
                            <button 
                                onClick={() => handleEndorse(item.id)}
                                className="flex-1 md:flex-none bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700 font-medium shadow transition-colors">
                                ✦ Endorse to VP
                            </button>
                        </div>
                    </div>
                ))}
                {pendingDeliverables.length === 0 && (
                    <div className="bg-gray-100 border-dashed border-2 border-gray-300 p-8 rounded-lg text-center text-gray-500">
                        No deliverables are currently awaiting your endorsement.
                    </div>
                )}
            </div>

            {/* Scrollable Endorsement History Table */}
            <div>
                <h2 className="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">Endorsement History</h2>
                <div className="bg-white rounded-lg shadow border border-gray-200 max-h-96 overflow-y-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deliverable Title</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Pipeline Status</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {historyDeliverables.map(item => (
                                <tr 
                                    key={item.id} 
                                    className="hover:bg-gray-50 cursor-pointer transition-colors" 
                                    onClick={() => openModal(item)}
                                >
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{item.title}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 uppercase">{item.office_type}</td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        {/* Dynamic status badge based on where it is in the pipeline AFTER the director */}
                                        {item.status === 'director_endorsed' && (
                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Awaiting Head MOV Upload
                                            </span>
                                        )}
                                        {item.status === 'mov_upload' && (
                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                Awaiting VP Approval
                                            </span>
                                        )}
                                        {item.status === 'vp_approved' && (
                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Fully Approved
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {historyDeliverables.length === 0 && (
                                <tr>
                                    <td colSpan={3} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No history available yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Reusable Modal Component */}
            <DeliverableModal 
                isOpen={isModalOpen} 
                onClose={() => setIsModalOpen(false)} 
                deliverable={selectedDeliverable} 
            />
        </div>
    );
}