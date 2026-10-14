export type Role = 'faculty' | 'head' | 'staff' | 'director' | 'vp' | 'oup';
export type Office = 'research' | 'innovation' | 'extension';
export type Stage = 'proposal' | 'ongoing' | 'completed' | 'terminated';
export type VerificationStatus = 'pending' | 'verified' | 'returned';
export type OutcomeType = 'publication' | 'presentation' | 'commercialization' | 'policy' | 'technology_transfer';

export interface Unit {
    id: number;
    campus_id?: number;
    name: string;
    code: string | null;
    type?: string;
}

export interface Campus {
    id: number;
    name: string;
    code?: string | null;
    units?: Unit[];
}

export interface CurrentUser {
    id: number;
    name: string;
    email: string;
    role: Role;
    office: Office | null;
    position: string | null;
    employee_type: 'teaching' | 'non_teaching' | null;
    campus: Campus | null;
    unit: Unit | null;
    can_manage_users: boolean;
}

export interface UserAccount {
    id: number;
    name: string;
    email: string;
    role: Role;
    office: Office | null;
    position: string | null;
    employee_type: string | null;
    status: 'pending' | 'active' | 'inactive';
    campus_id: number | null;
    unit_id: number | null;
    campus?: Campus | null;
    unit?: Unit | null;
    created_at: string;
}

export interface PersonRef {
    id: number;
    name: string;
    position?: string | null;
    email?: string;
}

export interface ProjectMember {
    id: number;
    user_id: number | null;
    name: string;
    role: 'lead' | 'member';
    user?: PersonRef | null;
}

export interface ProjectTag {
    id: number;
    type: 'unit' | 'campus' | 'partner';
    unit_id: number | null;
    campus_id: number | null;
    label: string;
}

export interface ProjectOutcome {
    id: number;
    type: OutcomeType;
    title: string;
    venue: string | null;
    indexing: string | null;
    date: string | null;
    url: string | null;
    citations: number | null;
    notes: string | null;
}

export interface Attachment {
    id: number;
    original_name: string;
    mime_type: string | null;
    size: number;
    checksum: string | null;
    source: 'upload' | 'drive_import';
    drive_status: 'pending' | 'synced' | 'failed' | 'disabled';
    created_at: string;
    uploader?: PersonRef;
}

export interface DuplicateMatch {
    project_id: number;
    title: string;
    office: Office;
    stage: Stage;
    score: number;
}

export interface ProjectUpdate {
    id: number;
    project_id: number;
    type: 'proposal' | 'stage' | 'outcome';
    from_stage: Stage | null;
    to_stage: Stage | null;
    data: (Partial<ProjectOutcome> & { date?: string }) | null;
    remarks: string | null;
    duplicate_matches: DuplicateMatch[] | null;
    status: VerificationStatus;
    review_remarks: string | null;
    reviewed_at: string | null;
    created_at: string;
    summary: string;
    submitter?: PersonRef;
    reviewer?: PersonRef | null;
    attachments?: Attachment[];
    attachments_hidden?: boolean;
    attachments_count?: number;
    project?: Pick<Project, 'id' | 'title' | 'office' | 'stage' | 'verification_status'> & {
        campus?: Campus | null;
        unit?: Unit | null;
    };
}

export interface Certificate {
    id: number;
    project_id: number;
    user_id: number;
    type: 'participation' | 'completion' | 'publication' | 'utilization';
    purpose: string | null;
    request_remarks: string | null;
    status: 'requested' | 'issued' | 'declined';
    serial_no: string | null;
    verification_code: string | null;
    issued_at: string | null;
    decision_remarks: string | null;
    created_at: string;
    recipient?: PersonRef;
    issuer?: (PersonRef & { office?: Office | null }) | null;
    project?: Project;
}

export interface Project {
    id: number;
    title: string;
    office: Office;
    abstract: string | null;
    keywords: string | null;
    lead_id: number;
    stage: Stage;
    verification_status: VerificationStatus;
    funding_source: string | null;
    budget: string | null;
    start_date: string | null;
    end_date: string | null;
    proposed_at: string | null;
    completed_at: string | null;
    last_verified_at: string | null;
    updated_at: string;
    lead?: PersonRef;
    campus?: Campus | null;
    unit?: Unit | null;
    members?: ProjectMember[];
    tags?: ProjectTag[];
    outcomes?: ProjectOutcome[];
    outcomes_count?: number;
    certificates?: Certificate[];
}

export interface ActivityEntry {
    id: number;
    action: string;
    description: string;
    created_at: string;
    user?: PersonRef | null;
}

export interface ProjectDetail {
    project: Project;
    updates: ProjectUpdate[];
    imported_attachments: Attachment[];
    activity: ActivityEntry[];
    permissions: {
        is_involved: boolean;
        can_inspect: boolean;
        can_verify: boolean;
        can_submit_update: boolean;
        can_resubmit: boolean;
        can_request_certificate: boolean;
        next_stages: Stage[];
    };
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

export interface Lookups {
    campuses: Campus[];
    offices: Office[];
    roles: Role[];
    stages: Stage[];
    outcome_types: OutcomeType[];
    indexing: string[];
    certificate_types: Certificate['type'][];
    certificate_purposes: string[];
    target_metrics: string[];
    duplicate_threshold: number;
}

export interface NotificationItem {
    id: number;
    title: string;
    body: string | null;
    link: string | null;
    read_at: string | null;
    created_at: string;
}

export interface ReportColumn {
    key: string;
    label: string;
}

export type ReportCell = string | number | null;

export interface Report {
    title: string;
    columns: ReportColumn[];
    rows: Record<string, ReportCell>[];
    totals: Record<string, ReportCell> | null;
    meta: {
        type: string;
        from: string;
        to: string;
        group_by: string;
        scope: string;
        generated_by: string;
        generated_at: string;
    };
}

export interface OrgNode {
    id: string;
    type: 'executive' | 'office' | 'group' | 'campus' | 'unit';
    label: string;
    people: { id: number; name: string; position: string | null; role: Role }[];
    children: OrgNode[];
    records?: number;
    faculty_count?: number;
    office?: Office;
    campus_id?: number;
    unit_id?: number;
}
