export interface Role {
    id: number;
    name: string;
    code: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    campus_roles?: Role[];
    // created_at: string
    // updated_at: string
}
