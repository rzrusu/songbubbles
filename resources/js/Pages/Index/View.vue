<script setup>
import Layout from '@/Layouts/Layout.vue'
import { Head } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
    
defineProps({
    tracks: Array
})
</script>

<template>
  <Layout>
    <Head title="Welcome" />
    <h1>Welcome</h1>
    <p>Hello, welcome to your first Inertia app!</p>
    <div v-for="track in tracks.items" :key="track.id">
      <p>{{ track.name }} -</p>
    </div>
    <Link href="/spotify">Spotify</Link>
  </Layout>
</template>