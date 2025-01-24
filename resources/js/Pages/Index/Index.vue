<script setup>
import { ref, onMounted, watch, computed, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import * as d3 from 'd3'
import { Vibrant } from "node-vibrant/browser";

// Initialize all refs at the top
const selectedAlbums = ref([])
const searchResults = ref([])
const showResults = ref(false)
const search = ref('')

// Define props
const props = defineProps({
  tracks: {
    type: Array,
    default: () => []
  },
  results: {
    type: Array,
    default: () => []
  }
})

let simulation = null // Store the D3 simulation instance
let zoom = null // Store the D3 zoom behavior
let currentTransform = { x: 0, y: 0, k: 1 } // Track current zoom/pan state
const selectedTrack = ref(null) // Track the currently selected track ID
const trackColors = ref({}) // Store colors for each track
const isLoading = ref(true)

const processedTracks = ref([])
const initialized = ref(false)

// Constants for track sizes
const minSize = 300
const maxSize = 800
const scalingFactor = 5

// Loading state for search
const isSearching = ref(false)

// State for showing album management menu
const showAlbumManager = ref(false)

// Nr of max albums
const MAX_ALBUMS = 5

const getVibrantColor = async (imageUrl) => {
  try {
    const v = new Vibrant(imageUrl)
    const palette = await v.getPalette()
    // Get the base color, preferring DarkVibrant
    const baseColor = palette.DarkVibrant?.hex || 
                     palette.Vibrant?.hex || 
                     palette.DarkMuted?.hex || 
                     '#000000'
    
    // Convert to RGB
    const r = parseInt(baseColor.slice(1,3), 16)
    const g = parseInt(baseColor.slice(3,5), 16)
    const b = parseInt(baseColor.slice(5,7), 16)
    
    // Desaturate and darken by mixing with black
    const darkening = 0.4
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
  
  initializeSimulation()
}

// Function to initialize or restart the simulation
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
// This is what generates the sort of gravitational push effect
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

  const getBounds = () => {
    const tracks = processedTracks.value
    if (!tracks.length) return { minX: 0, maxX: window.innerWidth, minY: 0, maxY: window.innerHeight }
    
    return tracks.reduce((bounds, track) => {
      const radius = track.size / 2
      bounds.minX = Math.min(bounds.minX, track.x - radius)
      bounds.maxX = Math.max(bounds.maxX, track.x + radius)
      bounds.minY = Math.min(bounds.minY, track.y - radius)
      bounds.maxY = Math.max(bounds.maxY, track.y + radius)
      return bounds
    }, {
      minX: Infinity,
      maxX: -Infinity,
      minY: Infinity,
      maxY: -Infinity
    })
  }

  zoom = d3.zoom()
    .scaleExtent([0.2, 5])
    .on("zoom", (event) => {
      const bounds = getBounds()
      const { transform } = event
      
      const boundsWidth = bounds.maxX - bounds.minX
      const boundsHeight = bounds.maxY - bounds.minY
      
      const padding = {
        x: boundsWidth * 0.05,
        y: boundsHeight * 0.05
      }
      
      const scaledBounds = {
        minX: (bounds.minX - padding.x) * transform.k,
        maxX: (bounds.maxX + padding.x) * transform.k,
        minY: (bounds.minY - padding.y) * transform.k,
        maxY: (bounds.maxY + padding.y) * transform.k
      }
      
      // Ensure at least 25% of the bounds width/height is always visible
      const minVisibleWidth = (boundsWidth + 2 * padding.x) * transform.k * 0.25
      const minVisibleHeight = (boundsHeight + 2 * padding.y) * transform.k * 0.25
      
      // Calculate viewport constraints
      const xMin = Math.min(-scaledBounds.maxX + window.innerWidth, -scaledBounds.minX - minVisibleWidth)
      const xMax = Math.max(-scaledBounds.minX, -scaledBounds.maxX + window.innerWidth + minVisibleWidth)
      const yMin = Math.min(-scaledBounds.maxY + window.innerHeight, -scaledBounds.minY - minVisibleHeight)
      const yMax = Math.max(-scaledBounds.minY, -scaledBounds.maxY + window.innerHeight + minVisibleHeight)
      
      transform.x = Math.min(xMax, Math.max(xMin, transform.x))
      transform.y = Math.min(yMax, Math.max(yMin, transform.y))
      
      currentTransform = transform
      container.style(
        "transform",
        `translate(${transform.x}px, ${transform.y}px) scale(${transform.k})`
      )
    })

  wrapper.call(zoom)

  // Initial zoom setup
  const initialScale = 0.5
  const viewportWidth = window.innerWidth
  const viewportHeight = window.innerHeight
  const translateX = (viewportWidth - viewportWidth * initialScale) / 2
  const translateY = (viewportHeight - viewportHeight * initialScale) / 2

  wrapper
    .transition()
    .duration(500)
    .ease(d3.easeCubicOut)
    .call(
      zoom.transform,
      d3.zoomIdentity.translate(translateX, translateY).scale(initialScale)
    )

  currentTransform = { x: translateX, y: translateY, k: initialScale }
}

// Dynamically adjust track sizes for mobile
const adjustSizesForMobile = () => {
  const isMobile = window.innerWidth < 768
  const scaleFactor = isMobile ? 0.6 : 1 // Reduce size for mobile

  return {
    minSize: minSize * scaleFactor,
    maxSize: maxSize * scaleFactor,
  }
}

watch(() => props.tracks, processTracks, { immediate: true })

// Watch for props.results changes
watch(() => props.results, (newResults) => {
  searchResults.value = newResults || []
}, { immediate: true })

// Modify search handling
const handleSearch = async () => {
  if (!search.value) {
    showResults.value = false
    isSearching.value = false
    return
  }
  
  showResults.value = true
  showAlbumManager.value = false
  isSearching.value = true
  
  router.visit('/search-preview', { 
    method: 'post',
    data: { query: search.value },
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      isSearching.value = false
    },
    onError: () => {
      isSearching.value = false
      showResults.value = false
      console.error('Search failed')
    }
  })
}

// Update the search watcher with additional safeguards
watch(search, (newValue) => {
  if (!newValue || newValue.length < 2) {
    showResults.value = false
    isSearching.value = false
    return
  }
  
  if (newValue.length >= 2) {
    handleSearch()
    showAlbumManager.value = false
  }
}, { debounce: 300 })

// Handle album selection
const toggleAlbumSelection = (album) => {
  console.log('Before toggle:', selectedAlbums.value) // Debug log
  
  const index = selectedAlbums.value.findIndex(a => a.id === album.id)
  if (index === -1) {
    if (getCurrentAlbums.value.length + selectedAlbums.value.length >= MAX_ALBUMS) {
      alert('Maximum of 5 albums allowed')
      return
    }
    if (getCurrentAlbums.value.some(a => a.id === album.id)) {
      alert('This album is already in your selection')
      return
    }
    // Create a new array to ensure reactivity
    selectedAlbums.value = [...selectedAlbums.value, album]
  } else {
    // Create a new array to ensure reactivity
    selectedAlbums.value = selectedAlbums.value.filter(a => a.id !== album.id)
  }
  
  console.log('After toggle:', selectedAlbums.value) // Debug log
}

// Add selected albums to visualization
const addSelectedAlbums = () => {
  isLoading.value = true
  router.post('/add-albums', {
    albumIds: selectedAlbums.value.map(album => album.id),
    currentTracks: processedTracks.value // Send current tracks to backend
  }, {
    preserveState: true,
    onSuccess: () => {
      selectedAlbums.value = []
      showResults.value = false
      search.value = ''
    }
  })
}

// Function to remove an album's tracks
const removeAlbum = (albumId) => {
  // Filter out tracks from the specified album
  processedTracks.value = processedTracks.value.filter(track => track.album.id !== albumId)
  
  // Only close menu if no albums left
  if (getCurrentAlbums.value.length === 0) {
    showAlbumManager.value = false
  } else {
    showAlbumManager.value = true // Explicitly keep menu open
  }
  
  // Restart simulation
  initializeSimulation()
}

// Helper function to get unique albums from tracks
const getCurrentAlbums = computed(() => {
  const albums = new Map()
  processedTracks.value.forEach(track => {
    if (!albums.has(track.album.id)) {
      albums.set(track.album.id, track.album)
    }
  })
  return Array.from(albums.values())
})

// Add computed for remaining album slots
const remainingAlbumSlots = computed(() => {
  return MAX_ALBUMS - getCurrentAlbums.value.length
})

// Modify the album manager toggle
const toggleAlbumManager = () => {
  showAlbumManager.value = !showAlbumManager.value
  if (showAlbumManager.value) {
    showResults.value = false
    search.value = '' // Optional: clear search when opening album manager
  }
}

// Add click outside handler
const closeMenus = (event) => {
  // Check if click is outside both menus and the search bar
  const searchContainer = document.querySelector('#search-container')
  const albumManager = document.querySelector('#album-manager')
  const searchResults = document.querySelector('#search-results')
  
  // Don't close if clicking inside any of these elements
  if (searchContainer?.contains(event.target) || 
      albumManager?.contains(event.target) || 
      searchResults?.contains(event.target)) {
    return
  }
  
  showResults.value = false
  showAlbumManager.value = false
}

// Add a watcher to debug
watch(selectedAlbums, (newVal) => {
  console.log('selectedAlbums changed:', newVal)
}, { deep: true })

// Add computed property for checking selected status
const isAlbumSelected = (albumId) => {
  return selectedAlbums.value?.some(album => album.id === albumId)
}

// Add computed for selected albums count
const selectedCount = computed(() => selectedAlbums.value.length)

onMounted(() => {
  window.addEventListener("wheel", (event) => event.preventDefault(), { passive: false })
  processTracks()
  initializeZoom()
  document.addEventListener('click', closeMenus)
})

// Clean up event listener
onUnmounted(() => {
  document.removeEventListener('click', closeMenus)
})
</script>

<template>
  <Layout>
    <Head title="Spotify" />

    <!-- View Switcher (future feature) -->
    <div class="fixed top-8 left-1/2 -translate-x-1/2 z-50">
      <div class="bg-black/40 backdrop-blur-xl rounded-full border border-white/20 p-1">
        <div class="relative flex">
          <button class="relative z-10 w-40 px-4 py-2 text-sm text-white transition-colors duration-200 rounded-full flex items-center justify-center">
            Song Bubbles
          </button>
          <button class="relative z-10 w-40 px-4 py-2 text-sm text-white/20 transition-colors duration-200 rounded-full cursor-not-allowed group flex items-center justify-center"
                  disabled>
            Artist Connections
            <span class="absolute -bottom-8 left-1/2 -translate-x-1/2 text-xs text-white/40 bg-black/80 px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200">Coming Soon</span>
          </button>
          <!-- Animated background pill -->
          <div class="absolute left-0 top-0 h-full w-1/2 bg-white/10 rounded-full transition-all duration-300 ease-in-out"></div>
        </div>
      </div>
    </div>
    
    <!-- Search results dropdown with loading state -->
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-200 ease-in"
      enter-from-class="transform translate-y-4 opacity-0"
      leave-to-class="transform translate-y-4 opacity-0"
    >
      <div v-if="showResults" 
           id="search-results"
           class="fixed bottom-28 left-1/2 transform -translate-x-1/2 z-50 w-[500px] max-h-[400px] overflow-y-auto bg-black/40 backdrop-blur-xl rounded-xl border border-white/20">
        <!-- Add album limit indicator -->
        <div class="px-4 pt-3 pb-2 border-b border-white/10">
          <div class="text-white/60 text-sm">
            {{ remainingAlbumSlots }} album{{ remainingAlbumSlots === 1 ? '' : 's' }} remaining
          </div>
        </div>
        
        <!-- Loading state -->
        <div v-if="isSearching" class="p-8 text-center text-white">
          <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-white mx-auto mb-4"></div>
          <div class="text-white/60">Searching...</div>
        </div>
        
        <!-- Results -->
        <template v-else>
          <div v-if="searchResults.length > 0" class="p-4 space-y-2">
            <div v-for="album in searchResults" 
                 :key="album.id"
                 @click="toggleAlbumSelection(album)"
                 class="flex items-center space-x-3 p-2 rounded-lg hover:bg-white/10 cursor-pointer transition-colors duration-200"
                 :class="{ 
                   'bg-white/20': isAlbumSelected(album.id),
                   'opacity-50 cursor-not-allowed': getCurrentAlbums.value?.some(a => a.id === album.id)
                 }">
              <img :src="album.images[2].url" class="w-12 h-12 rounded" />
              <div class="flex-1">
                <div class="text-white font-medium">{{ album.name }}</div>
                <div class="text-white/60 text-sm">{{ album.artists[0].name }}</div>
              </div>
              <div v-show="isAlbumSelected(album.id)"
                   class="text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
          </div>
          
          <!-- No results message -->
          <div v-else-if="search" class="p-8 text-center text-white/60">
            No albums found
          </div>
        </template>
      </div>
    </Transition>
    
    <!-- Search bar with album manager button -->
    <div id="search-container" class="fixed bottom-8 left-1/2 transform -translate-x-1/2 z-50">
      <div class="flex items-center space-x-2 bg-black/40 backdrop-blur-xl px-4 py-3 rounded-xl border border-white/20">
        <input 
          type="text" 
          v-model="search" 
          placeholder="Search for an album..." 
          class="bg-transparent border-none outline-none text-white placeholder-white/50 w-64"
        />
        <button 
          v-show="selectedCount > 0"
          @click="addSelectedAlbums"
          class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-xl transition-colors duration-200"
        >
          Add {{ selectedCount }} album{{ selectedCount === 1 ? '' : 's' }}
        </button>
        <button 
          @click="toggleAlbumManager"
          class="bg-white/10 hover:bg-white/20 text-white p-2 rounded-xl transition-colors duration-200"
          :class="{ 'bg-white/20': showAlbumManager }"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5z" />
            <path d="M11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
          </svg>
        </button>
      </div>
    </div>
    
    <!-- Album manager menu -->
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-200 ease-in"
      enter-from-class="transform translate-y-4 opacity-0"
      leave-to-class="transform translate-y-4 opacity-0"
    >
      <div v-if="showAlbumManager" 
           id="album-manager"
           class="fixed bottom-28 left-1/2 transform -translate-x-1/2 z-50 w-[500px] max-h-[400px] overflow-y-auto bg-black/40 backdrop-blur-xl rounded-xl border border-white/20">
        <div class="p-4">
          <div class="text-white font-medium mb-4">Current Albums</div>
          <div v-if="getCurrentAlbums.length === 0" class="text-white/60 text-center py-4">
            No albums added yet
          </div>
          <div v-else class="space-y-2">
            <div v-for="album in getCurrentAlbums" 
                 :key="album.id"
                 class="flex items-center space-x-3 p-2 rounded-lg bg-white/5">
              <img :src="album.images[2].url" class="w-12 h-12 rounded" />
              <div class="flex-1">
                <div class="text-white font-medium">{{ album.name }}</div>
                <div class="text-white/60 text-sm">{{ album.artists[0].name }}</div>
              </div>
              <button 
                @click.stop="removeAlbum(album.id)"
                class="text-white/60 hover:text-white/90 transition-colors duration-200"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
    
    <div
      id="zoomable-wrapper"
      class="w-screen h-screen bg-[#030507] overflow-hidden relative"
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
            :style="{ height: track.size / 3 + 'px' }"
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
            <div class="text-white text-xl font-medium">Grabbing your music...</div>
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
    line-clamp: 1;
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
  
  input::placeholder {
    color: rgba(255, 255, 255, 0.5);
  }
  
  input {
    font-size: 1rem;
  }
  
  /* Webkit scrollbar styles */
  .overflow-y-auto {
    scrollbar-width: thin; /* Firefox */
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent; /* Firefox */
  }
  
  .overflow-y-auto::-webkit-scrollbar {
    width: 6px;
  }
  
  .overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
    margin: 4px 0;
  }
  
  .overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
    transition: background-color 0.2s;
  }
  
  .overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background-color: rgba(255, 255, 255, 0.5);
  }
  
  /* Hide scrollbar when not hovering (optional) */
  .overflow-y-auto:not(:hover)::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.1);
  }
  
  /* Ensure padding for scrollbar */
  .overflow-y-auto {
    padding-right: 2px;
  }
  
</style>