import { STAGE_LABELS } from '@/lib/format';
import type { Stage, VerificationStatus } from '@/lib/types';

const FLOW: Stage[] = ['proposal', 'ongoing', 'completed'];

/**
 * Lifecycle tracker: Proposal → Ongoing → Completed, with outcomes shown separately.
 */
export default function StageTracker({ stage, verification, pendingStage }: { stage: Stage; verification: VerificationStatus; pendingStage?: Stage | null }) {
    const current = stage === 'terminated' ? -1 : FLOW.indexOf(stage);

    return (
        <ol className="flex w-full items-center">
            {FLOW.map((step, index) => {
                const done = verification === 'verified' && index <= current;
                const pending = (verification === 'pending' && index === 0) || pendingStage === step;
                const isLast = index === FLOW.length - 1;

                return (
                    <li key={step} className={`flex items-center ${isLast ? '' : 'flex-1'}`}>
                        <div className="flex flex-col items-center">
                            <span
                                className={`flex h-9 w-9 items-center justify-center rounded-full border-2 text-sm font-bold ${
                                    done
                                        ? 'border-brand-700 bg-brand-700 text-white'
                                        : pending
                                          ? 'border-amber-500 bg-amber-50 text-amber-700'
                                          : 'border-gray-300 bg-white text-gray-400'
                                }`}
                            >
                                {done ? '✓' : index + 1}
                            </span>
                            <span className={`mt-2 text-xs font-medium ${done ? 'text-brand-800' : pending ? 'text-amber-700' : 'text-gray-400'}`}>
                                {STAGE_LABELS[step]}
                                {pending && <span className="block text-[10px] font-normal">awaiting verification</span>}
                            </span>
                        </div>
                        {!isLast && <div className={`mx-2 mb-6 h-1 flex-1 rounded ${verification === 'verified' && index < current ? 'bg-brand-700' : 'bg-gray-200'}`} />}
                    </li>
                );
            })}
            {stage === 'terminated' && (
                <li className="ml-4 rounded-md bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">Terminated</li>
            )}
        </ol>
    );
}
