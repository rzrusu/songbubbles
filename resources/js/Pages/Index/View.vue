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
    <div class="grid grid-cols-4 gap-4">
        <div v-for="track in tracks" :key="track.id">
            <div></div>
            <p class="text-white">{{ track.name }}</p>
    </div>
    </div>
    <Link href="/spotify">Spotify</Link>
  </Layout>
</template>