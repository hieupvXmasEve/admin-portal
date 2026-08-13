export interface Guardian {
    id: number;
    full_name: string;
    relationship: string | null;
    phone: string | null;
    email: string | null;
    occupation: string | null;
    address: string | null;
    is_primary: boolean;
}
