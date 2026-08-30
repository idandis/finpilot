import type { SharedPerson } from './sharing';

export type TaskStatus = 'todo' | 'in_progress' | 'done';

export type TaskAssignee = {
    id: number;
    name: string;
};

export type Task = {
    id: number;
    title: string;
    description: string | null;
    status: TaskStatus;
    position: number;
    /** Boards only: the Daily board is personal, nobody to assign to. */
    assignee: TaskAssignee | null;
};

/**
 * A board the user added next to the built-in "Daily" one, which has no
 * TaskBoard of its own (the Task page represents it as a null board).
 */
export type TaskBoard = {
    id: number;
    name: string;
    /** Owned by somebody else and shared with this user. */
    is_shared: boolean;
};

/** The board currently open, with everyone working on it. */
export type TaskBoardDetail = {
    id: number;
    name: string;
    is_owner: boolean;
    people: SharedPerson[];
};
