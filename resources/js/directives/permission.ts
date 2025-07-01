import { DirectiveBinding } from 'vue';
import { usePermissions } from '../composables/usePermissions';

export const vCan = {
    mounted(el: HTMLElement, binding: DirectiveBinding) {
        const { can } = usePermissions();

        if (!can(binding.value)) {
            el.parentNode?.removeChild(el);
        }
    },
};

export const vCanAny = {
    mounted(el: HTMLElement, binding: DirectiveBinding) {
        const { canAny } = usePermissions();
        const permissions = Array.isArray(binding.value) ? binding.value : [binding.value];

        if (!canAny(permissions)) {
            el.parentNode?.removeChild(el);
        }
    },
};
