import { ref, computed } from 'vue';

export type EditorMode = 'default' | 'email' | 'document';

interface EditorStyleConfig {
  containerClass: string;
  editorClass: string;
  extensions: {
    link?: {
      HTMLAttributes?: Record<string, string>;
    };
    bulletList?: {
      HTMLAttributes?: Record<string, string>;
    };
    orderedList?: {
      HTMLAttributes?: Record<string, string>;
    };
    blockquote?: {
      HTMLAttributes?: Record<string, string>;
    };
    codeBlock?: {
      HTMLAttributes?: Record<string, string>;
    };
    table?: {
      HTMLAttributes?: Record<string, string>;
    };
    tableHeader?: {
      HTMLAttributes?: Record<string, string>;
    };
    tableCell?: {
      HTMLAttributes?: Record<string, string>;
    };
  };
}

const defaultConfig: EditorStyleConfig = {
  containerClass: 'prose max-w-none p-3',
  editorClass: '',
  extensions: {
    link: {
      HTMLAttributes: {
        class: 'text-blue-600 underline hover:text-blue-800',
      },
    },
    bulletList: {
      HTMLAttributes: {
        class: 'list-disc list-inside',
      },
    },
    orderedList: {
      HTMLAttributes: {
        class: 'list-decimal list-inside',
      },
    },
    blockquote: {
      HTMLAttributes: {
        class: 'border-l-4 border-gray-300 pl-4 italic',
      },
    },
    codeBlock: {
      HTMLAttributes: {
        class: 'bg-gray-100 text-gray-800 font-mono p-3 rounded',
      },
    },
    table: {
      HTMLAttributes: {
        class: 'border-collapse table-auto w-full border border-gray-300',
      },
    },
    tableHeader: {
      HTMLAttributes: {
        class: 'border border-gray-300 px-4 py-2 text-left bg-gray-50 font-semibold',
      },
    },
    tableCell: {
      HTMLAttributes: {
        class: 'border border-gray-300 px-4 py-2',
      },
    },
  },
};

const emailConfig: EditorStyleConfig = {
  containerClass: 'email-editor max-w-none p-3',
  editorClass: 'email-content',
  extensions: {
    link: {
      HTMLAttributes: {
        class: '', // No classes, let CSS handle styling
      },
    },
    bulletList: {
      HTMLAttributes: {
        class: '',
      },
    },
    orderedList: {
      HTMLAttributes: {
        class: '',
      },
    },
    blockquote: {
      HTMLAttributes: {
        class: '',
      },
    },
    codeBlock: {
      HTMLAttributes: {
        class: '',
      },
    },
    table: {
      HTMLAttributes: {
        class: '',
      },
    },
    tableHeader: {
      HTMLAttributes: {
        class: '',
      },
    },
    tableCell: {
      HTMLAttributes: {
        class: '',
      },
    },
  },
};

export function useEditorStyling(mode: EditorMode = 'default') {
  const currentMode = ref<EditorMode>(mode);

  const config = computed(() => {
    switch (currentMode.value) {
      case 'email':
        return emailConfig;
      case 'document':
        // Future: document-specific configuration
        return defaultConfig;
      default:
        return defaultConfig;
    }
  });

  const setMode = (newMode: EditorMode) => {
    currentMode.value = newMode;
  };

  const getExtensionConfig = (extensionName: keyof EditorStyleConfig['extensions']) => {
    return config.value.extensions[extensionName] || {};
  };

  return {
    currentMode,
    config,
    setMode,
    getExtensionConfig,
  };
}
