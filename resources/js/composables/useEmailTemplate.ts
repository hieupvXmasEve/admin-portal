import { ref, reactive } from 'vue'
import axios from 'axios'

interface EmailTemplate {
  id: number
  name: string
  type: string
  subject: string
  html_content: string
  text_content?: string
  variables?: string[]
  is_active: boolean
  version: number
  description?: string
  created_at: string
  updated_at: string
}

interface TemplateForm {
  name: string
  type: string
  subject: string
  html_content: string
  text_content?: string
  description?: string
  is_active: boolean
}

interface PaginatedTemplates {
  data: EmailTemplate[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
  prev_page_url?: string
  next_page_url?: string
}

export function useEmailTemplate() {
  const templates = ref<PaginatedTemplates>({
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0
  })

  const templateTypes = ref<Record<string, string>>({})
  const isLoading = ref(false)
  const isRefreshing = ref(false)

  const loadTemplateTypes = async () => {
    try {
      const response = await axios.get('/api/v1/admin/email-templates/types')

      if (response.data.success) {
        templateTypes.value = response.data.data
      }
    } catch (error) {
      console.error('Failed to load template types:', error)
    }
  }

  const loadTemplates = async (filters: any = {}, page: number = 1) => {
    isLoading.value = true
    isRefreshing.value = true

    try {
      const params = new URLSearchParams()

      if (filters.type) params.append('type', filters.type)
      if (filters.is_active !== '') params.append('is_active', filters.is_active)
      if (filters.search) params.append('search', filters.search)
      params.append('page', page.toString())
      params.append('per_page', '15')

      const response = await axios.get(`/api/v1/admin/email-templates?${params}`)

      if (response.data.success) {
        templates.value = response.data.data
      }
    } catch (error) {
      console.error('Failed to load templates:', error)
      throw error
    } finally {
      isLoading.value = false
      isRefreshing.value = false
    }
  }

  const createTemplate = async (data: TemplateForm) => {
    try {
      const response = await axios.post('/api/v1/admin/email-templates', data)

      if (response.data.success) {
        return response.data.data
      }

      throw new Error(response.data.message || 'Failed to create template')
    } catch (error) {
      console.error('Failed to create template:', error)
      throw error
    }
  }

  const updateTemplate = async (id: number, data: Partial<TemplateForm>) => {
    try {
      const response = await axios.put(`/api/v1/admin/email-templates/${id}`, data)

      if (response.data.success) {
        return response.data.data
      }

      throw new Error(response.data.message || 'Failed to update template')
    } catch (error) {
      console.error('Failed to update template:', error)
      throw error
    }
  }

  const deleteTemplate = async (id: number) => {
    try {
      const response = await axios.delete(`/api/v1/admin/email-templates/${id}`)

      if (response.data.success) {
        return true
      }

      throw new Error(response.data.message || 'Failed to delete template')
    } catch (error) {
      console.error('Failed to delete template:', error)
      throw error
    }
  }

  const previewTemplate = async (id: number, variables: Record<string, string> = {}) => {
    try {
      const response = await axios.post(`/api/v1/admin/email-templates/${id}/preview`, {
        variables
      })

      if (response.data.success) {
        return response.data.data
      }

      throw new Error(response.data.message || 'Failed to preview template')
    } catch (error) {
      console.error('Failed to preview template:', error)
      throw error
    }
  }

  const createNewVersion = async (id: number, data: TemplateForm) => {
    try {
      const response = await axios.post(`/api/v1/admin/email-templates/${id}/version`, data)

      if (response.data.success) {
        return response.data.data
      }

      throw new Error(response.data.message || 'Failed to create new version')
    } catch (error) {
      console.error('Failed to create new version:', error)
      throw error
    }
  }

  const validateTemplate = async (content: string) => {
    try {
      const response = await axios.post('/api/v1/admin/email-templates/validate', {
        content
      })

      return response.data
    } catch (error) {
      console.error('Failed to validate template:', error)
      throw error
    }
  }

  const getTemplatesByType = async (type: string) => {
    try {
      const response = await axios.get(`/api/v1/admin/email-templates/type/${type}`)

      if (response.data.success) {
        return response.data.data
      }

      throw new Error(response.data.message || 'Failed to get templates by type')
    } catch (error) {
      console.error('Failed to get templates by type:', error)
      throw error
    }
  }

  const getTemplate = async (id: number) => {
    try {
      const response = await axios.get(`/api/v1/admin/email-templates/${id}`)

      if (response.data.success) {
        return response.data.data
      }

      throw new Error(response.data.message || 'Failed to get template')
    } catch (error) {
      console.error('Failed to get template:', error)
      throw error
    }
  }

  // Initialize template types on first use
  loadTemplateTypes()

  return {
    // State
    templates,
    templateTypes,
    isLoading,
    isRefreshing,

    // Actions
    loadTemplates,
    createTemplate,
    updateTemplate,
    deleteTemplate,
    previewTemplate,
    createNewVersion,
    validateTemplate,
    getTemplatesByType,
    getTemplate,
    loadTemplateTypes
  }
}
