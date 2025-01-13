<script setup>
import Layout from '@/Layouts/Layout.vue'
import { Head } from '@inertiajs/vue3'

defineProps({
    tracks: Object
})

</script>

<template>
  <Layout>
    <Head title="Spotify" />
    <h1>Spotify</h1>
    <p>Hello, welcome to your Spotify page</p>
    <div v-for="track in tracks.items" :key="track.id">
      <p class="bg-red-500">{{ track.name }}</p>
    </div>
  </Layout>
</template>