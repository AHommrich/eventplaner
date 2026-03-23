import { ref } from 'vue';

const open = ref(false);

export function useAddGuestModal() {
    return { open };
}
