export type PasswordEntry = {
    id: number;
    password_group_id: number;
    platform_name: string;
    username: string | null;
    created_at: string;
};

export type PasswordGroup = {
    id: number;
    user_id: number;
    name: string;
    icon: string | null;
    entries: PasswordEntry[];
};
