/**
 * Composable for generating user initials from names
 */
export function useInitials() {
  /**
   * Generate initials from a full name
   */
  const getInitials = (name: string): string => {
    if (!name || typeof name !== 'string') {
      return '??'
    }

    const words = name.trim().split(/\s+/)

    if (words.length === 0) {
      return '??'
    }

    if (words.length === 1) {
      // Single word - take first two characters
      return words[0].substring(0, 2).toUpperCase()
    }

    // Multiple words - take first character of first and last word
    const firstInitial = words[0].charAt(0)
    const lastInitial = words[words.length - 1].charAt(0)

    return (firstInitial + lastInitial).toUpperCase()
  }

  /**
   * Generate initials with custom logic for different scenarios
   */
  const getInitialsAdvanced = (
    name: string,
    options: {
      maxInitials?: number
      fallback?: string
      includeMiddle?: boolean
    } = {}
  ): string => {
    const { maxInitials = 2, fallback = '??', includeMiddle = false } = options

    if (!name || typeof name !== 'string') {
      return fallback
    }

    const words = name.trim().split(/\s+/).filter(word => word.length > 0)

    if (words.length === 0) {
      return fallback
    }

    let initials = ''

    if (words.length === 1) {
      // Single word - take first characters up to maxInitials
      initials = words[0].substring(0, maxInitials)
    } else if (words.length === 2) {
      // Two words - first character of each
      initials = words[0].charAt(0) + words[1].charAt(0)
    } else {
      // Multiple words
      if (includeMiddle && maxInitials >= 3) {
        // First, middle, last
        const firstInitial = words[0].charAt(0)
        const middleInitial = words[Math.floor(words.length / 2)].charAt(0)
        const lastInitial = words[words.length - 1].charAt(0)
        initials = firstInitial + middleInitial + lastInitial
      } else {
        // Just first and last
        const firstInitial = words[0].charAt(0)
        const lastInitial = words[words.length - 1].charAt(0)
        initials = firstInitial + lastInitial
      }
    }

    return initials.toUpperCase().substring(0, maxInitials)
  }

  /**
   * Generate a background color based on the name (for avatar backgrounds)
   */
  const getInitialsColor = (name: string): string => {
    if (!name) return '#6b7280' // gray-500

    const colors = [
      '#ef4444', // red-500
      '#f97316', // orange-500
      '#f59e0b', // amber-500
      '#eab308', // yellow-500
      '#84cc16', // lime-500
      '#22c55e', // green-500
      '#10b981', // emerald-500
      '#14b8a6', // teal-500
      '#06b6d4', // cyan-500
      '#0ea5e9', // sky-500
      '#3b82f6', // blue-500
      '#6366f1', // indigo-500
      '#8b5cf6', // violet-500
      '#a855f7', // purple-500
      '#d946ef', // fuchsia-500
      '#ec4899', // pink-500
      '#f43f5e', // rose-500
    ]

    // Generate a consistent hash from the name
    let hash = 0
    for (let i = 0; i < name.length; i++) {
      hash = name.charCodeAt(i) + ((hash << 5) - hash)
    }

    // Use the hash to select a color
    const colorIndex = Math.abs(hash) % colors.length
    return colors[colorIndex]
  }

  /**
   * Generate initials with color for avatar display
   */
  const getInitialsWithColor = (name: string) => {
    return {
      initials: getInitials(name),
      color: getInitialsColor(name)
    }
  }

  return {
    getInitials,
    getInitialsAdvanced,
    getInitialsColor,
    getInitialsWithColor
  }
}
