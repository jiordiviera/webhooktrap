import type { Auth } from '@/types/auth';
import type { Flash, GuestMeter, Instance, Principal } from '@/types/identity';
import type { AgentPill } from '@/types/run';
import type {
    CurrentProject,
    LayoutPreference,
    ProjectSwitcherItem,
} from '@/types/workspace';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            principal: Principal;
            instance: Instance;
            meter?: GuestMeter;
            flash?: Flash;
            currentProject?: CurrentProject | null;
            projectSwitcher?: ProjectSwitcherItem[];
            layout: LayoutPreference;
            agentPill?: AgentPill | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
