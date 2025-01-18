<script setup>
import { ref, onMounted, watch } from 'vue'
import Layout from '@/Layouts/Layout.vue'
import { Head, router } from '@inertiajs/vue3'
import * as d3 from 'd3'
import { Vibrant } from "node-vibrant/browser";

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
const trackColors = ref({}) // Store colors for each track
const isLoading = ref(true)

// Constants for size normalization
const minSize = 300 // Updated min size
const maxSize = 800
const scalingFactor = 5

const getVibrantColor = async (imageUrl) => {
  try {
    const v = new Vibrant(imageUrl)
    const palette = await v.getPalette()
    // Get the base color, preferring DarkVibrant
    const baseColor = palette.DarkVibrant?.hex || 
                     palette.Vibrant?.hex || 
                     palette.DarkMuted?.hex || 
                     '#000000'
    
    // Convert to RGB to manipulate the color
    const r = parseInt(baseColor.slice(1,3), 16)
    const g = parseInt(baseColor.slice(3,5), 16)
    const b = parseInt(baseColor.slice(5,7), 16)
    
    // Desaturate and darken by mixing with black
    const darkening = 0.4 // You might want to adjust this for DarkVibrant colors
    const newR = Math.floor(r * (1 - darkening))
    const newG = Math.floor(g * (1 - darkening))
    const newB = Math.floor(b * (1 - darkening))
    
    // Convert back to hex
    const newColor = '#' + 
      (newR.toString(16).padStart(2, '0')) +
      (newG.toString(16).padStart(2, '0')) +
      (newB.toString(16).padStart(2, '0'))
    
    return newColor
  } catch (error) {
    console.error('Error extracting color:', error)
    return 'rgba(0, 0, 0, 0.5)' // fallback color
  }
}

const processTracks = async () => {
  if (!props.tracks || props.tracks.length === 0) {
    isLoading.value = false
    return
  }

  const { minSize: adjustedMinSize, maxSize: adjustedMaxSize } = adjustSizesForMobile()
  const maxPopularity = Math.max(...props.tracks.map(t => t.popularity))

  // Set up initial positions and process tracks
  processedTracks.value = props.tracks.map(track => ({
    ...track,
    size: adjustedMinSize +
      Math.pow((track.popularity / maxPopularity), scalingFactor) *
        (adjustedMaxSize - adjustedMinSize),
    x: window.innerWidth / 2 + (Math.random() - 0.5) * 200,
    y: window.innerHeight / 2 + (Math.random() - 0.5) * 200
  }))

  // Process colors first
  await Promise.all(
    processedTracks.value.map(async (track) => {
      const imageUrl = track.album.images[0].url
      trackColors.value[track.id] = await getVibrantColor(imageUrl)
    })
  )

  // Remove loading screen before starting simulation
  isLoading.value = false
  
  // Initialize simulation after loading screen is gone
  initializeSimulation()
}

// Function to initialize or restart the D3 simulation
const initializeSimulation = () => {
  if (simulation) simulation.stop()

  simulation = d3.forceSimulation(processedTracks.value)
    .alphaDecay(0.001)
    .velocityDecay(0.6)
    .force('center', d3.forceCenter(window.innerWidth / 2, window.innerHeight / 2))
    .force('collision', d3.forceCollide(d => d.size / 2 + 80))
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
    .scaleExtent([0.2, 5])
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

const handleSearch = () => {
  if (!search.value) {
    alert('Please enter a search query.')
    return
  }
  
  isLoading.value = true
  router.post('/search', { 
    query: search.value 
  }, {
    preserveState: true,
    onSuccess: () => {
      setTimeout(() => {
        isLoading.value = false
      }, 1500)
    }
  })
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
      <button @click="handleSearch">Search</button>
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
            height: track.size * 1.4 + 'px',
            transform: `translate(${track.x - track.size / 2}px, ${track.y - (track.size * 1.5) / 2}px)`,
            outline: selectedTrack === track.id ? `40px solid ${trackColors[track.id]}15` : 'none',
            backgroundColor: trackColors[track.id] ? `${trackColors[track.id]}33` : 'rgba(0, 0, 0, 0.5)',
          }"
          class="absolute rounded-xl shadow-lg flex backdrop-blur-xl flex-col items-center text-white text-center p-4 hover:cursor-pointer"
        >
          <div
            :style="{
              backgroundImage: `url(${track.album.images[0].url})`,
              backgroundSize: 'cover',
              backgroundPosition: 'center',
              width: '100%',
              height: '100%',
            }"
            class="rounded-lg"
          ></div>
          <div
            class="w-full h-full py-4 flex flex-col items-center"
            :style="{ height: track.size / 1.2 + 'px' }"
          >
            <div class="flex flex-col items-center h-full justify-center">
              <div
                class="font-bold truncate-text"
                :style="{ fontSize: (track.size / 10) + 'px' }"
              >
                {{ track.name }}
              </div>
              <div
                class="truncate-text text-gray-300"
                :style="{ fontSize: (track.size / 14) + 'px' }"
              >
                {{ track.artists.map(artist => artist.name).join(', ') }}
              </div>
            </div>
            <div class="flex flex-row items-center space-x-4">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="currentColor"
                :width="track.size / 7 + 'px'"
                :height="track.size / 7 + 'px'"
              >
                <path
                  d="M9.195 18.44c1.25.714 2.805-.189 2.805-1.629v-2.34l6.945 3.968c1.25.715 2.805-.188 2.805-1.628V8.69c0-1.44-1.555-2.343-2.805-1.628L12 11.029v-2.34c0-1.44-1.555-2.343-2.805-1.628l-7.108 4.061c-1.26.72-1.26 2.536 0 3.256l7.108 4.061Z"
                />
              </svg>
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="currentColor"
                :width="track.size / 7 + 'px'"
                :height="track.size / 7 + 'px'"
              >
                <path
                  d="M6.75 5.25a.75.75 0 0 1 .75-.75H9a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75H7.5a.75.75 0 0 1-.75-.75V5.25Zm7.5 0A.75.75 0 0 1 15 4.5h1.5a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75H15a.75.75 0 0 1-.75-.75V5.25Z"
                />
              </svg>
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="currentColor"
                :width="track.size / 7 + 'px'"
                :height="track.size / 7 + 'px'"
              >
                <path
                  d="M5.055 7.06C3.805 6.347 2.25 7.25 2.25 8.69v8.122c0 1.44 1.555 2.343 2.805 1.628L12 14.471v2.34c0 1.44 1.555 2.343 2.805 1.628l7.108-4.061c1.26-.72 1.26-2.536 0-3.256l-7.108-4.061C13.555 6.346 12 7.249 12 8.689v2.34L5.055 7.061Z"
                />
              </svg>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Loading overlay with blur -->
      <Transition
        enter-active-class="transition-opacity duration-500"
        leave-active-class="transition-opacity duration-500"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div v-if="isLoading" 
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xl">
          <div class="text-center space-y-4">
            <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mx-auto"></div>
            <div class="text-white text-xl font-medium">Arranging your music...</div>
          </div>
        </div>
      </Transition>
    </div>
  </Layout>
</template>

<style scoped>
.truncate-text {
  display: -webkit-box;
  -webkit-line-clamp: 1; /* Limit to 2 lines */
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  word-wrap: break-word;
  white-space: normal;
}

#zoomable-container {
  transform-origin: 0 0;
}

.backdrop-blur-xl {
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

.loading-overlay {
  background: rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}
</style>