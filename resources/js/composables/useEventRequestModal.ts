import { ref } from 'vue';

const open = ref(false);

export function useEventRequestModal() {
    return { open };
}
