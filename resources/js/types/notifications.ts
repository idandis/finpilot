/** One line in the sidebar bell. */
export type AppNotification = {
    id: string;
    /** 'invitation' arrived by email too; 'activity' only lives here. */
    type: 'invitation' | 'activity';
    message: string;
    resource: {
        kind: 'shopping_list' | 'task_board' | 'meal_plan';
        label: string;
        name: string;
        url: string;
    } | null;
    read: boolean;
    created_at: string | null;
};

export type NotificationFeed = {
    unread: number;
    items: AppNotification[];
};
