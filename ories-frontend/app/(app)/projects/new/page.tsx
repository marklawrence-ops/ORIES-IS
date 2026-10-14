'use client';

import ProposalForm from '@/components/ProposalForm';
import { Alert, PageHeader } from '@/components/ui';
import { useAuth } from '@/lib/auth';

export default function NewProposalPage() {
    const { hasRole } = useAuth();

    return (
        <div className="mx-auto max-w-4xl">
            <PageHeader
                title="Submit a Proposal"
                description="Start a research, innovation, or extension record. Later stages (ongoing, completed) and outcomes are added as updates to this same record."
            />
            {hasRole('faculty', 'head') ? <ProposalForm /> : <Alert>Only faculty and heads submit records.</Alert>}
        </div>
    );
}
