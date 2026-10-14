export interface Tag {
    id: number;
    tagged_campus_id?: number | null;
    tagged_department?: string | null;
    collaborator_name?: string | null;
    type?: string;
    tag_type?: string;
    name?: string;
    tag_name?: string;
    entity_name?: string;
}

export interface Deliverable {
    id: number;
    title: string;
    due_date: string;
    office_type: string;
    status: string;
    mov_link?: string;
    tags?: Tag[];
}

export interface DriveFile {
    id: string;
    name: string;
    url: string;
    created_at: string;
}
