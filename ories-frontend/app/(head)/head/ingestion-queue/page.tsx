'use client';

import { useState } from 'react';
import api from '@/lib/axios';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import Cookies from 'js-cookie';

export default function IngestionQueue() {
    const router = useRouter();
    const [files, setFiles] = useState<any[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const handleSync = async () => {
        setLoading(true);
        setError('');
        try {
            const response = await api.get('/ingestion/sync');
            setFiles(response.data.files);
        } catch (err) {
            console.error("Sync failed", err);
            setError('Failed to fetch files from Google Drive. Check your backend logs.');
        } finally {
            setLoading(false);
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
            <header className="mb-8 flex items-center justify-between border-b pb-4">
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">Ingestion Queue</h1>
                    <p className="text-gray-600">Manually pull faculty drop-off files</p>
                </div>
                <div className="flex gap-4">
                    <Link 
                        href="/head/dashboard" 
                        className="rounded bg-gray-600 px-4 py-2 text-sm text-white hover:bg-gray-700 font-medium flex items-center"
                    >
                        Back to Dashboard
                    </Link>
                    <button 
                        onClick={handleLogout}
                        className="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                    >
                        Logout
                    </button>
                </div>
            </header>
            
            <div className="mb-6 flex justify-between items-center bg-white p-6 rounded-lg shadow border border-gray-100">
                <div>
                    <h2 className="text-xl font-semibold text-gray-800">Google Drive Sync</h2>
                    <p className="text-sm text-gray-500">Fetch the latest unencoded deliverables from the drop-off folder.</p>
                </div>
                <button 
                    onClick={handleSync}
                    disabled={loading}
                    className={`rounded px-6 py-3 text-sm font-medium text-white transition-colors 
                        ${loading ? 'bg-blue-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'}`}
                >
                    {loading ? 'Syncing...' : 'Sync with Google Drive'}
                </button>
            </div>

            {error && (
                <div className="mb-6 bg-red-50 border-l-4 border-red-500 p-4 text-red-700">
                    {error}
                </div>
            )}

            <div className="space-y-4">
                {files.length === 0 && !loading && !error && (
                    <p className="text-gray-500 text-center py-8">No files synced yet. Click the button above to pull from Drive.</p>
                )}
                
                {files.map((file) => (
                    <div key={file.id} className="bg-white p-4 rounded-lg shadow flex justify-between items-center border-l-4 border-yellow-500">
                        <div>
                            <h3 className="font-semibold text-gray-800 truncate max-w-lg">{file.name}</h3>
                            <p className="text-xs text-gray-500">Uploaded: {file.created_at}</p>
                        </div>
                        <div className="flex gap-2">
                            <a 
                                href={file.url} 
                                target="_blank" 
                                rel="noopener noreferrer"
                                className="rounded border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                View File
                            </a>
                            <Link 
                                href={`/head/encode?title=${encodeURIComponent(file.name)}&link=${encodeURIComponent(file.url)}`}
                                className="rounded bg-green-600 px-3 py-1 text-sm text-white hover:bg-green-700"
                            >
                                Encode
                            </Link>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}