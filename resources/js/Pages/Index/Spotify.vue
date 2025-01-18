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
const selectedTrack = ref(null) // Track the currently selected track ID

// Constants for size normalization
const minSize = 300 // Updated min size
const maxSize = 800
const scalingFactor = 3

// Helper function to generate a random gray color
const getRandomGray = () => {
  const grayValue = Math.floor(Math.random() * 156) + 50
  return `rgb(${grayValue}, ${grayValue}, ${grayValue})`
}

const processTracks = () => {
  if (!props.tracks || props.tracks.length === 0) return

  const { minSize: adjustedMinSize, maxSize: adjustedMaxSize } = adjustSizesForMobile()

  const maxPopularity = Math.max(...props.tracks.map(t => t.popularity))

  processedTracks.value = props.tracks.map(track => ({
    ...track,
    size:
      adjustedMinSize +
      Math.pow((track.popularity / maxPopularity), scalingFactor) *
        (adjustedMaxSize - adjustedMinSize),
    color: getRandomGray(),
    x: window.innerWidth / 2 + (Math.random() - 0.5) * 200,
    y: window.innerHeight / 2 + (Math.random() - 0.5) * 200
  }))

  initializeSimulation()
}

// Function to initialize or restart the D3 simulation
const initializeSimulation = () => {
  if (simulation) simulation.stop()

  simulation = d3.forceSimulation(processedTracks.value)
    .alphaDecay(0.001)
    .velocityDecay(0.6)
    .force('center', d3.forceCenter(window.innerWidth / 2, window.innerHeight / 2))
    .force('collision', d3.forceCollide(d => d.size / 2 + 40))
    .force('charge', d3.forceManyBody().strength(-200))
    .force('x', d3.forceX(window.innerWidth / 2).strength(0.05))
    .force('y', d3.forceY(window.innerHeight / 2).strength(0.05))
    .on('tick', () => {
      const container = d3.select("#zoomable-container")
      container.style("transform", `translate(${currentTransform.x}px, ${currentTransform.y}px) scale(${currentTransform.k})`)
      processedTracks.value = [...processedTracks.value]
    })

  initialized.value = true
}

// Function to shift elements dynamically when a song is clicked
const bringToFront = (trackId) => {
  selectedTrack.value = trackId
  const selected = processedTracks.value.find(t => t.id === trackId)

  if (!selected) return

  simulation
    .force('collision', d3.forceCollide(d => {
      if (d.id === trackId) {
        return d.size + 60
      }
      return d.size / 2 + 40
    }))
    .force('charge', d3.forceManyBody().strength(d => (d.id === trackId ? -400 : -200)))
    .alpha(0.3)
    .restart()
}

const initializeZoom = () => {
  const container = d3.select("#zoomable-container")
  const wrapper = d3.select("#zoomable-wrapper")

  zoom = d3.zoom()
    .scaleExtent([0.2, 5]) // Allow zooming out to 0.2x and in to 5x
    .on("zoom", (event) => {
      currentTransform = event.transform
      container.style(
        "transform",
        `translate(${event.transform.x}px, ${event.transform.y}px) scale(${event.transform.k})`
      )
    })

  wrapper.call(zoom)

  const initialScale = 0.5 // Larger scale for smaller screens
  const viewportWidth = window.innerWidth
  const viewportHeight = window.innerHeight

  // Adjust centering dynamically
  const translateX = (viewportWidth - viewportWidth * initialScale) / 2
  const translateY = (viewportHeight - viewportHeight * initialScale) / 2

  // Smooth zoom transition with easing
  wrapper
    .transition()
    .duration(500) // Faster transition for mobile
    .ease(d3.easeCubicOut)
    .call(
      zoom.transform,
      d3.zoomIdentity.translate(translateX, translateY).scale(initialScale)
    )

  currentTransform = { x: translateX, y: translateY, k: initialScale }
}

// Adjust min and max size for mobile screens
const adjustSizesForMobile = () => {
  const isMobile = window.innerWidth < 768
  const scaleFactor = isMobile ? 0.6 : 1 // Reduce size for mobile

  return {
    minSize: minSize * scaleFactor,
    maxSize: maxSize * scaleFactor,
  }
}


watch(() => props.tracks, processTracks, { immediate: true })

const searchSpotify = () => {
  if (!search.value) {
    alert('Please enter a search query.')
    return
  }

  router.post('/search', { query: search.value }, { preserveState: true })
}

onMounted(() => {
  window.addEventListener("wheel", (event) => event.preventDefault(), { passive: false })
  processTracks()
  initializeZoom()
})
</script>

<template>
  <Layout>
    <div class="absolute top-0 left-0 bg-red-500 m-4 z-50">
      <input type="text" v-model="search" placeholder="Search" />
      <button @click="searchSpotify">Search</button>
    </div>
    <Head title="Spotify" />
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
          @click="bringToFront(track.id)" 
          :style="{ 
            width: track.size + 'px',
            height: track.size + 'px',
            transform: `translate(${track.x - track.size / 2}px, ${track.y - track.size / 2}px)`,
            backgroundColor: track.color,
            backgroundImage: `url(${track.album.images[0].url})`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            outline: selectedTrack === track.id ? '40px solid rgba(255, 255, 255, 0.1)' : 'none' // Add border if selected
          }"
          class="absolute rounded-lg shadow-lg flex flex-col items-center justify-end text-white text-center p-4 hover:cursor-pointer"
        >
          <div 
            class="backdrop-blur-sm bg-black/60 w-full rounded-lg p-2"
          >
            <div 
              class="font-bold truncate-text"
              :style="{ fontSize: (track.size / 12) + 'px' }" 
            >
              {{ track.name }}
            </div>
            <div 
              class="text-gray-300 mt-1"
              :style="{ fontSize: (track.size / 16) + 'px' }" 
            >
              {{ track.popularity }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.truncate-text {
  display: -webkit-box;
  -webkit-line-clamp: 2; /* Limit to 2 lines */
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  word-wrap: break-word;
  white-space: normal;
}
</style>
