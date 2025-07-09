/**
 * Authentication Routes Constants
 * Centralized route management for Authentication module
 */

// Route Names - these should match Laravel route names
export const AUTH_ROUTE_NAMES = {
    // Registration Routes
    REGISTER_SHOW: 'register',
    REGISTER_STORE: 'register.store',

    // Login Routes
    LOGIN_SHOW: 'login',
    LOGIN_STORE: 'login.store',
    LOGOUT: 'logout',

    // Social Authentication
    GOOGLE_LOGIN: 'google.login',
    GOOGLE_CALLBACK: 'google.callback',

    // Password Reset Routes
    PASSWORD_REQUEST: 'password.request',
    PASSWORD_EMAIL: 'password.email',
    PASSWORD_RESET: 'password.reset',
    PASSWORD_STORE: 'password.store',
    PASSWORD_CONFIRM: 'password.confirm',
    PASSWORD_CONFIRM_STORE: 'password.confirm.store',

    // Email Verification Routes
    VERIFICATION_NOTICE: 'verification.notice',
    VERIFICATION_VERIFY: 'verification.verify',
    VERIFICATION_SEND: 'verification.send',
} as const;

// Route Paths - for frontend routing and navigation
export const AUTH_ROUTE_PATHS = {
    REGISTER: '/register',
    LOGIN: '/login',
    LOGOUT: '/logout',

    GOOGLE_LOGIN: '/auth/google/redirect',
    GOOGLE_CALLBACK: '/auth/google/callback',

    PASSWORD_REQUEST: '/forgot-password',
    PASSWORD_RESET: (token: string) => `/reset-password/${token}`,
    PASSWORD_CONFIRM: '/confirm-password',

    VERIFICATION_NOTICE: '/verify-email',
    VERIFICATION_VERIFY: (id: string, hash: string) => `/verify-email/${id}/${hash}`,
} as const;

// Route Helper Functions
export const authRoutes = {
    // Registration
    registerShow: () => AUTH_ROUTE_NAMES.REGISTER_SHOW,
    registerStore: () => AUTH_ROUTE_NAMES.REGISTER_STORE,

    // Login/Logout
    loginShow: () => AUTH_ROUTE_NAMES.LOGIN_SHOW,
    loginStore: () => AUTH_ROUTE_NAMES.LOGIN_STORE,
    logout: () => AUTH_ROUTE_NAMES.LOGOUT,

    // Social Auth
    googleLogin: () => AUTH_ROUTE_NAMES.GOOGLE_LOGIN,
    googleCallback: () => AUTH_ROUTE_NAMES.GOOGLE_CALLBACK,

    // Password Reset
    passwordRequest: () => AUTH_ROUTE_NAMES.PASSWORD_REQUEST,
    passwordEmail: () => AUTH_ROUTE_NAMES.PASSWORD_EMAIL,
    passwordReset: () => AUTH_ROUTE_NAMES.PASSWORD_RESET,
    passwordStore: () => AUTH_ROUTE_NAMES.PASSWORD_STORE,
    passwordConfirm: () => AUTH_ROUTE_NAMES.PASSWORD_CONFIRM,
    passwordConfirmStore: () => AUTH_ROUTE_NAMES.PASSWORD_CONFIRM_STORE,

    // Email Verification
    verificationNotice: () => AUTH_ROUTE_NAMES.VERIFICATION_NOTICE,
    verificationVerify: () => AUTH_ROUTE_NAMES.VERIFICATION_VERIFY,
    verificationSend: () => AUTH_ROUTE_NAMES.VERIFICATION_SEND,

    // Helper functions for frontend
    getRegisterPath: () => AUTH_ROUTE_PATHS.REGISTER,
    getLoginPath: () => AUTH_ROUTE_PATHS.LOGIN,
    getLogoutPath: () => AUTH_ROUTE_PATHS.LOGOUT,
    getGoogleLoginPath: () => AUTH_ROUTE_PATHS.GOOGLE_LOGIN,
    getPasswordRequestPath: () => AUTH_ROUTE_PATHS.PASSWORD_REQUEST,
    getPasswordResetPath: (token: string) => AUTH_ROUTE_PATHS.PASSWORD_RESET(token),
    getPasswordConfirmPath: () => AUTH_ROUTE_PATHS.PASSWORD_CONFIRM,
    getVerificationNoticePath: () => AUTH_ROUTE_PATHS.VERIFICATION_NOTICE,
    getVerificationVerifyPath: (id: string, hash: string) => AUTH_ROUTE_PATHS.VERIFICATION_VERIFY(id, hash),
} as const;

// Type definitions for better TypeScript support
export type AuthRouteNames = (typeof AUTH_ROUTE_NAMES)[keyof typeof AUTH_ROUTE_NAMES];
export type AuthRoutePaths = typeof AUTH_ROUTE_PATHS;
