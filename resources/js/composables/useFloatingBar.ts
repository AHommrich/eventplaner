import { ref } from 'vue';

const active = ref(false);

export function useFloatingBar() {
    return { active };
}
