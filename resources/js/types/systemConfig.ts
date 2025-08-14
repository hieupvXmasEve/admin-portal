export interface SystemConfig {
  app_name: string
  logo_full: string
  logo_text: string
  copyright_text: string
  country: string
}

export interface SystemConfigResponse {
  success: boolean
  message: string
  data: SystemConfig
}
