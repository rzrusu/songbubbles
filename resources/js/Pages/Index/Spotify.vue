<script setup>
import { ref, onMounted, watch } from 'vue'
import Layout from '@/Layouts/Layout.vue'
import { Head, router } from '@inertiajs/vue3'
import * as d3 from 'd3'

// Define props
const props = defineProps({
  tracks: {
    type: Array,
    required: true
  }
})

let search = ref('')
const processedTracks = ref([])
const initialized = ref(false)
let simulation = null // Store the D3 simulation instance
let zoom = null // Store the D3 zoom behavior
let currentTransform = { x: 0, y: 0, k: 1 } // Track current zoom/pan state

// Constants for size normalization
const minSize = 150
const maxSize = 600
const scalingFactor = 3

// Helper function to generate a random gray color
const getRandomGray = () => {
  const grayValue = Math.floor(Math.random() * 156) + 50
  return `rgb(${grayValue}, ${grayValue}, ${grayValue})`
}

// Function to reprocess tracks when data changes
const processTracks = () => {
  console.log('Processing tracks:', props.tracks) // Debugging

  if (!props.tracks || props.tracks.length === 0) {
    console.warn('No tracks available to process.')
    return
  }

  const maxPopularity = Math.max(...props.tracks.map(t => t.popularity))

  processedTracks.value = props.tracks.map(track => ({
    ...track,
    size:
      minSize +
      Math.pow((track.popularity / maxPopularity), scalingFactor) * (maxSize - minSize),
    color: getRandomGray(),
    x: window.innerWidth / 2 + (Math.random() - 0.5) * 200,
    y: window.innerHeight / 2 + (Math.random() - 0.5) * 200
  }))

  console.log('Processed tracks:', processedTracks.value) // Debugging
  initializeSimulation()
}

// Function to initialize or restart the D3 simulation
const initializeSimulation = () => {
  console.log('Initializing simulation...') // Debugging

  if (simulation) {
    simulation.stop() // Stop any existing simulation
  }

  simulation = d3.forceSimulation(processedTracks.value)
    .force('center', d3.forceCenter(window.innerWidth / 2, window.innerHeight / 2))
    .force('collision', d3.forceCollide(d => d.size / 2 + 20)) // Add collision padding
    .force('charge', d3.forceManyBody().strength(-100))
    .force('x', d3.forceX(window.innerWidth / 2).strength(0.1))
    .force('y', d3.forceY(window.innerHeight / 2).strength(0.1))
    .on('tick', () => {
      processedTracks.value = [...processedTracks.value]
    })

  initialized.value = true
}

// Function to initialize zoom behavior
const initializeZoom = () => {
  console.log('Initializing zoom...') // Debugging

  const container = d3.select("#zoomable-container")
  const wrapper = d3.select("#zoomable-wrapper")

  zoom = d3.zoom()
    .scaleExtent([0.5, 5]) // Set zoom range
    .on("zoom", (event) => {
      currentTransform = event.transform // Save current transform
      container.style("transform", `translate(${event.transform.x}px, ${event.transform.y}px) scale(${event.transform.k})`)
    })

  wrapper.call(zoom)

  // Apply initial zoom transform if available
  wrapper.call(zoom.transform, d3.zoomIdentity.translate(currentTransform.x, currentTransform.y).scale(currentTransform.k))
}

// Watch for changes in tracks and reprocess them
watch(() => props.tracks, processTracks, { immediate: true })

// Function to send search query to the backend
const searchSpotify = () => {
  if (!search.value) {
    alert('Please enter a search query.')
    return
  }

  router.post('/search', { query: search.value }, { preserveState: true })
}

onMounted(() => {
  console.log('Component mounted, initializing...') // Debugging
  window.addEventListener("wheel", (event) => event.preventDefault(), { passive: false })
  processTracks() // Process initial tracks
  initializeZoom() // Initialize zoom behavior
})
</script>

<template>
  <Layout>
    <div class="absolute top-0 left-0 bg-red-500 m-4 z-50">
      <input type="text" v-model="search" placeholder="Search" />
      <button @click="searchSpotify">Search</button>
    </div>
    <Head title="Spotify" />
    <!-- Div Container for Zoom and Pan -->
    <div
      id="zoomable-wrapper"
      class="w-screen h-screen bg-black overflow-hidden relative"
      :class="{ hidden: !initialized }" 
    >
      <div v-if="processedTracks.length === 0" class="text-white text-center">
        No tracks available to display.
      </div>
      <div v-else id="zoomable-container" class="absolute top-0 left-0">
        <div
          v-for="track in processedTracks"
          :key="track.id || track.name"
          :style="{ 
            width: track.size + 'px',
            height: track.size + 'px',
            transform: `translate(${track.x - track.size / 2}px, ${track.y - track.size / 2}px)`,
            backgroundColor: track.color
          }"
          class="absolute rounded-lg shadow-lg flex flex-col items-center justify-center text-white text-center"
        >
          <!-- Track Name -->
          <div class="text-2xl font-bold">
            {{ track.name }}
          </div>
          <!-- Track Popularity -->
          <div class="text-xl text-gray-300 mt-1">
            {{ track.popularity }}
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>
