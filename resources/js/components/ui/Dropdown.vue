<template>
  <AppDropdown v-bind="$attrs">
    <template v-for="(_, name) in $slots" #[name]="slotProps">
      <slot :name="name" v-bind="slotProps" />
    </template>
  </AppDropdown>
</template>

<script setup lang="ts">
import AppDropdown from './AppDropdown.vue';
</script>
