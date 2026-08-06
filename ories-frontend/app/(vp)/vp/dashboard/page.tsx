'use client';

import { useEffect, useState } from 'react';
import api from '@/lib/axios';
import { useRouter } from 'next/navigation';
import Cookies from 'js-cookie';

export default function VpDashboard() {
    const router = useRouter();
    const [deliverables, setDeliverables] = useState<any[]>([]);

    useEffect(() => {
        api.get('/deliverables').then(res => setDeliverables(res.data));
    }, []);

    const handleApprove = async (id: number) => {
        try {
            await api.patch(`/deliverables/${id}/status`, { status: 'vp_approved' });
            setDeliverables(deliverables.map(d => d.id === id ? { ...d, status: 'vp_approved' } : d));
        } catch (error) {
            console.error("Failed to approve deliverable", error);
            alert("Failed to update status.");
        }
    };

    const handleLogout = async () => {
        await api.post('/logout');
        Cookies.remove('ories_token');
        Cookies.remove('ories_role');
        router.push('/');
    };

    return (
        <div className="min-h-screen bg-gray-50 p-8">
            {/* New Header with Logout */}
             <header className="mb-8 flex items-center justify-between border-b pb-4">
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">VP of ORIES Dashboard</h1>
                    <p className="text-gray-600">University-wide Final Approval Queue</p>
                </div>
                <button 
                    onClick={handleLogout}
                    className="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                >
                    Logout
                </button>
            </header>
            
            <div className="space-y-4">
                <h2 className="text-xl font-semibold mb-4">Pending Final Approvals (MOV Uploaded)</h2>
                {deliverables.filter(d => d.status === 'mov_upload').map(item => (
                    <div key={item.id} className="bg-white p-6 rounded-lg shadow flex justify-between items-center border-l-4 border-green-500">
                        <div>
                            <h3 className="text-lg font-semibold">{item.title}</h3>
                            <p className="text-sm text-gray-500">Campus ID: {item.campus_id} | Type: {item.office_type.toUpperCase()}</p>
                            {/* In a complete UI, the Google Drive MOV link would be clickable here */}
                        </div>
                        <button 
                            onClick={() => handleApprove(item.id)}
                            className="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700">
                            Approve Deliverable
                        </button>
                    </div>
                ))}
                
                {deliverables.filter(d => d.status === 'mov_upload').length === 0 && (
                    <div className="bg-white p-6 rounded-lg shadow text-center text-gray-500">
                        No deliverables pending final approval.
                    </div>
                )}
            </div>
            
            <div className="mt-12 space-y-4">
                <h2 className="text-xl font-semibold mb-4">Recently Approved</h2>
                {deliverables.filter(d => d.status === 'vp_approved').map(item => (
                    <div key={item.id} className="bg-gray-100 p-4 rounded-lg flex justify-between items-center opacity-75">
                        <span className="font-medium text-gray-700">{item.title}</span>
                        <span className="text-sm font-bold text-green-700">✓ Approved</span>
                    </div>
                ))}
            </div>
        </div>
    );
}