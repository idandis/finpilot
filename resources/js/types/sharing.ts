/**
 * Someone who works on a shared thing (a task board, a shopping list): its
 * owner plus everyone they invited by email.
 */
export type SharedPerson = {
    id: number;
    name: string;
    email: string;
    is_owner: boolean;
};
