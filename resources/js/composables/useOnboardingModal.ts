import { ref } from 'vue';

const open = ref(false);

export function useOnboardingModal() {
    return { open };
}
