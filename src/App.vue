<script setup>
import { RouterView } from 'vue-router'
</script>

<template>
  <v-app>
    <RouterView />
  </v-app>
</template>

<style scoped>
html,
body,
#app {
  height: 100dvh !important;
  width: 100dvw !important;
  background-color: rgb(0, 129, 250);
}
#app {
  display: flex;
  place-items: center;
}
</style>
