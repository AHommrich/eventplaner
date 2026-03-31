<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

defineOptions({ inheritAttrs: false })

interface Props {
  className?: HTMLAttributes['class']
}
defineProps<Props>()
</script>

<template>
  <img src="/logo-admin.png" :class="className" v-bind="$attrs" alt="Logo" />
</template>
