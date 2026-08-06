import React from 'react';

const stages = [
    { id: 'draft', label: 'Draft' },
    { id: 'submitted', label: 'Submitted' },
    { id: 'director_endorsed', label: 'Director Endorsed' },
    { id: 'mov_upload', label: 'MOV Uploaded' },
    { id: 'vp_approved', label: 'VP Approved' },
];

export default function PipelineTracker({ currentStatus }: { currentStatus: string }) {
    const currentIndex = stages.findIndex(stage => stage.id === currentStatus);

    return (
        <div className="flex items-center w-full my-4">
            {stages.map((stage, index) => {
                const isCompleted = index <= currentIndex;
                const isLast = index === stages.length - 1;

                return (
                    <div key={stage.id} className="flex-1 flex items-center relative">
                        <div className="flex flex-col items-center relative z-10">
                            <div className={`h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold border-2 
                                ${isCompleted ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-300 text-gray-400'}`}>
                                {index + 1}
                            </div>
                            <span className={`text-xs mt-2 font-medium absolute top-10 w-24 text-center 
                                ${isCompleted ? 'text-blue-800' : 'text-gray-400'}`}>
                                {stage.label}
                            </span>
                        </div>
                        {!isLast && (
                            <div className={`flex-auto border-t-4 transition-colors duration-300 -ml-2 -mr-2 z-0 
                                ${index < currentIndex ? 'border-blue-600' : 'border-gray-200'}`}>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}