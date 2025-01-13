<script setup>
import { ref, onMounted } from 'vue'
import Layout from '@/Layouts/Layout.vue'
import { Head } from '@inertiajs/vue3'
import * as d3 from 'd3'

// Define props
const props = defineProps({
  tracks: {
    type: Array,
    required: true
  }
})

// Reactive variables
const processedTracks = ref([])
const initialized = ref(false) // Tracks if initial zoom has been applied

// Constants for size normalization
const minSize = 150
const maxSize = 600
const scalingFactor = 3 // Higher value for more dramatic differences

// Helper function to generate a random gray color
const getRandomGray = () => {
  const grayValue = Math.floor(Math.random() * 156) + 50
  return `rgb(${grayValue}, ${grayValue}, ${grayValue})`
}

onMounted(() => {
  // Prevent page scrolling on wheel events
  window.addEventListener("wheel", (event) => event.preventDefault(), { passive: false })

  // Find the maximum popularity across all songs
  const maxPopularity = Math.max(...props.tracks.map(t => t.popularity))

  // Normalize sizes using exponential scaling
  const normalizedTracks = props.tracks.map(track => ({
    ...track,
    size:
      minSize +
      Math.pow((track.popularity / maxPopularity), scalingFactor) * (maxSize - minSize), // Exponential scaling
    color: getRandomGray(),
    x: Math.random() * window.innerWidth, // Random initial x position
    y: Math.random() * window.innerHeight // Random initial y position
  }))

  // Create D3 simulation
  const simulation = d3.forceSimulation(normalizedTracks)
    .force('center', d3.forceCenter(window.innerWidth / 2, window.innerHeight / 2))
    .force('collision', d3.forceCollide(d => d.size / 2 + 20)) // Add collision padding
    .force('charge', d3.forceManyBody().strength(-100)) // Add more repulsion for spacing
    .force('x', d3.forceX(window.innerWidth / 2).strength(0.1))
    .force('y', d3.forceY(window.innerHeight / 2).strength(0.1))
    .on('tick', () => {
      processedTracks.value = [...normalizedTracks]
    })

  setTimeout(() => {
    simulation.stop()

    // Calculate initial zoom after simulation is complete
    const xExtent = d3.extent(normalizedTracks, d => d.x - d.size / 2)
    const yExtent = d3.extent(normalizedTracks, d => d.y - d.size / 2)

    const bboxWidth = xExtent[1] - xExtent[0]
    const bboxHeight = yExtent[1] - yExtent[0]

    // Calculate initial zoom level
    const scale = Math.min(
      window.innerWidth / (bboxWidth + 100), // Add padding
      window.innerHeight / (bboxHeight + 100)
    )

    const translateX = window.innerWidth / 2 - (xExtent[0] + bboxWidth / 2) * scale
    const translateY = window.innerHeight / 2 - (yExtent[0] + bboxHeight / 2) * scale

    const container = d3.select("#zoomable-container")
    const wrapper = d3.select("#zoomable-wrapper")

    const zoom = d3.zoom()
      .scaleExtent([0.5, 5]) // Set zoom range
      .on("zoom", (event) => {
        container.style("transform", `translate(${event.transform.x}px, ${event.transform.y}px) scale(${event.transform.k})`)
      })

    wrapper.call(zoom)

    // Apply initial zoom and pan
    wrapper.call(zoom.transform, d3.zoomIdentity.translate(translateX, translateY).scale(scale))

    // Mark initialization as complete to render elements
    initialized.value = true
  }, 1000)
})
</script>

<template>
  <Layout>
    <Head title="Spotify" />
    <!-- Div Container for Zoom and Pan -->
    <div
      id="zoomable-wrapper"
      class="w-screen h-screen bg-black overflow-hidden relative"
      :class="{ hidden: !initialized }" 
    >
      <div id="zoomable-container" class="absolute top-0 left-0">
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
