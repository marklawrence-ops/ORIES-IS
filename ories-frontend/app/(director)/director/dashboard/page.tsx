'use client';

import { useEffect, useState } from 'react';
import api from '@/lib/axios';
import { useRouter } from 'next/navigation';
import Cookies from 'js-cookie';

export default function DirectorDashboard() {
    const router = useRouter();
    const [deliverables, setDeliverables] = useState<any[]>([]);

    useEffect(() => {
        api.get('/deliverables').then(res => setDeliverables(res.data));
    }, []);

    const handleEndorse = async (id: number) => {
        await api.patch(`/deliverables/${id}/status`, { status: 'director_endorsed' });
        setDeliverables(deliverables.map(d => d.id === id ? { ...d, status: 'director_endorsed' } : d));
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
                    <h1 className="text-3xl font-bold text-gray-900">Campus Review Queue</h1>
                    <p className="text-gray-600">Director Dashboard</p>
                </div>
                <button 
                    onClick={handleLogout}
                    className="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                >
                    Logout
                </button>
            </header>            
            <div className="space-y-4">
                {deliverables.filter(d => d.status === 'submitted').map(item => (
                    <div key={item.id} className="bg-white p-6 rounded-lg shadow flex justify-between items-center border-l-4 border-blue-500">
                        <div>
                            <h3 className="text-lg font-semibold">{item.title}</h3>
                            <p className="text-sm text-gray-500">Submitted by: Head ID #{item.head_id}</p>
                        </div>
                        <button 
                            onClick={() => handleEndorse(item.id)}
                            className="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">
                            Endorse to VP
                        </button>
                    </div>
                ))}
                {deliverables.filter(d => d.status === 'submitted').length === 0 && (
                    <p className="text-gray-500 text-sm">No pending submissions from Department Heads.</p>
                )}
            </div>
        </div>
    );
}