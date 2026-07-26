export interface SystemConfig {
    app_name: string;
    copyright_text: string;
    country: string;
    logo_full_url: string | null;
    logo_text_url: string | null;
    favicon_url: string | null;
    apple_touch_icon_url: string | null;
    branding_version: string | number;
    updated_at: string | null;
}

export interface SystemConfigTextUpdate {
    app_name: string;
    copyright_text: string;
    country: string;
}

export type BrandingSlot = 'logo_full' | 'logo_text' | 'favicon' | 'apple_touch_icon';
