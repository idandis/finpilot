export type TaskStatus = 'todo' | 'in_progress' | 'done';

export type Task = {
    id: number;
    title: string;
    description: string | null;
    status: TaskStatus;
    position: number;
};
