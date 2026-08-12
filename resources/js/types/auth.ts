export type UserRole = 'owner' | 'admin' | 'attendant';

export type User = {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    role: UserRole;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/**
 * What the signed-in user is allowed to reach.
 *
 * These gate the sidebar. Every controller checks the same policies again —
 * a hidden nav item is not authorisation.
 */
export type Abilities = {
    viewDocuments: boolean;
    viewCustomers: boolean;
    viewPrices: boolean;
    publishPrices: boolean;
    viewSettings: boolean;
    viewYard: boolean;
};

export type Auth = {
    user: User;
    role: UserRole | null;
    can: Abilities;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
