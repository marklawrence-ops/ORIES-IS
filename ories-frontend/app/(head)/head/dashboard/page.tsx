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

    useEffect(() => {
        api.get('/deliverables').then(res => setDeliverables(res.data));
    }, []);

    const handleLogout = async () => {
        await api.post('/logout');
        Cookies.remove('ories_token');
        Cookies.remove('ories_role');
        router.push('/');
    };

    return (
        <div className="min-h-screen bg-gray-50 p-8">
            <header className="mb-8 flex items-center justify-between border-b pb-4">
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">My Deliverables</h1>
                    <p className="text-gray-600">Department Head Dashboard</p>
                </div>
                
                {/* Updated Button Container */}
                <div className="flex gap-4">
                    {/* 1. New Ingestion Queue Button */}
                    <Link 
                        href="/head/ingestion-queue" 
                        className="rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 font-medium flex items-center"
                    >
                        Ingestion Queue
                    </Link>
                    
                    {/* 2. Existing Encode Button */}
                    <Link 
                        href="/head/encode" 
                        className="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 font-medium flex items-center"
                    >
                        + Encode Deliverable
                    </Link>
                    
                    {/* 3. Existing Logout Button */}
                    <button 
                        onClick={handleLogout}
                        className="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                    >
                        Logout
                    </button>
                </div>
            </header>
            
            <div className="space-y-6">
                {deliverables.length === 0 ? (
                    <p className="text-gray-500">No deliverables encoded yet.</p>
                ) : (
                    deliverables.map((item) => (
                        <div key={item.id} className="bg-white p-6 rounded-lg shadow border border-gray-100 pb-12">
                            <div className="flex justify-between items-start mb-6 border-b pb-4">
                                <div>
                                    <h3 className="text-xl font-semibold text-gray-800">{item.title}</h3>
                                    <span className="text-xs uppercase bg-gray-100 text-gray-600 px-2 py-1 rounded mt-2 inline-block">
                                        {item.office_type}
                                    </span>
                                </div>
                                <span className="text-sm font-medium text-gray-500">Due: {item.due_date}</span>
                            </div>
                            
                            {/* The Shopee-style Tracker */}
                            <PipelineTracker currentStatus={item.status} />

                            {/* Trigger MOV Upload state conditionally */}
                            {item.status === 'director_endorsed' && (
                                <div className="mt-10 flex justify-end">
                                    <button 
                                        onClick={() => api.patch(`/deliverables/${item.id}/status`, { status: 'mov_upload' }).then(() => window.location.reload())}
                                        className="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700">
                                        Upload MOVs & Forward to VP
                                    </button>
                                </div>
                            )}
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}