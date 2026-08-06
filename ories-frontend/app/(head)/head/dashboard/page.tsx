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
    // 1. Add a state for the active tab
    const [activeTab, setActiveTab] = useState<'active' | 'completed'>('active');

    useEffect(() => {
        api.get('/deliverables').then(res => setDeliverables(res.data));
    }, []);

    const handleLogout = async () => {
        await api.post('/logout');
        Cookies.remove('ories_token');
        Cookies.remove('ories_role');
        router.push('/');
    };

    const handleForwardToVP = async (id: number) => {
        try {
            await api.patch(`/deliverables/${id}/status`, { status: 'mov_upload' });
            window.location.reload();
        } catch (error) {
            console.error("Failed to forward", error);
            alert("Failed to forward to VP.");
        }
    };

    // 2. Split the data into two groups
    const activeDeliverables = deliverables.filter(d => d.status !== 'vp_approved');
    const completedDeliverables = deliverables.filter(d => d.status === 'vp_approved');

    // 3. Helper function to render the list so we don't repeat code
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

                {item.status === 'director_endorsed' && (
                    <div className="mt-8 flex justify-end border-t pt-4">
                        <button 
                            onClick={() => handleForwardToVP(item.id)}
                            className="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700 font-medium shadow-sm transition-colors">
                            Forward to VP (MOVs Auto-Attached)
                        </button>
                    </div>
                )}
            </div>
        ));
    };

    return (
        <div className="min-h-screen bg-gray-50 p-8">
            <header className="mb-8 flex items-center justify-between border-b pb-4">
                {/* ... (Keep your existing header and buttons exactly the same) ... */}
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

            {/* 4. Render the Tabs UI */}
            <div className="mb-6 border-b border-gray-200">
                <nav className="-mb-px flex space-x-8">
                    <button
                        onClick={() => setActiveTab('active')}
                        className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                            activeTab === 'active'
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        }`}
                    >
                        Active Deliverables ({activeDeliverables.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('completed')}
                        className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                            activeTab === 'completed'
                                ? 'border-green-500 text-green-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        }`}
                    >
                        Completed History ({completedDeliverables.length})
                    </button>
                </nav>
            </div>

            {/* 5. Render the content based on the active tab */}
            <div className="space-y-6">
                {activeTab === 'active' ? renderDeliverables(activeDeliverables) : renderDeliverables(completedDeliverables)}
            </div>
        </div>
    );
}