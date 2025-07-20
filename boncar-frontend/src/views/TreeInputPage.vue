<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useCalculatorStore } from '@/store/calculator';
import api from '@/services/api';

const router = useRouter();
const store = useCalculatorStore();

const searchResults = ref<any[]>([]);
const activeSearchIndex = ref<number | null>(null);
let searchTimeout: any = null;

// Helper untuk menemukan objek rumus yang dipilih berdasarkan ID
const getSelectedEquation = (treeId: number) => {
  const tree = store.trees.find(t => t.id === treeId);
  if (!tree) return null;
  return store.availableEquations.find(eq => eq.id === tree.allometric_equation_id);
};

// Helper untuk memeriksa apakah parameter dibutuhkan oleh rumus
const isParameterRequired = (treeId: number, param: 'height' | 'wood_density') => {
  const equation = getSelectedEquation(treeId);
  return equation?.required_parameters?.includes(param);
};

const onSearchSpecies = (treeId: number, event: Event) => {
  const searchTerm = (event.target as HTMLInputElement).value;
  const tree = store.trees.find(t => t.id === treeId);
  if (!tree) return;

  tree.species_search_term = searchTerm;
  tree.species_id = null;
  
  clearTimeout(searchTimeout);
  if (searchTerm.length < 3) {
    searchResults.value = [];
    return;
  }

  searchTimeout = setTimeout(async () => {
    try {
      activeSearchIndex.value = treeId;
      const { data } = await api.get(`/species/search?q=${searchTerm}`);
      searchResults.value = data;
    } catch (error) {
      console.error("Gagal mencari spesies:", error);
    }
  }, 500);
};

const selectSpecies = async (treeId: number, species: any) => {
  const tree = store.trees.find(t => t.id === treeId);
  if (!tree) return;

  tree.species_search_term = `${species.name} (${species.scientific_name})`;
  searchResults.value = [];
  activeSearchIndex.value = null;

  if (!species.is_local) {
    try {
      const response = await api.post('/species/from-inaturalist', {
        name: species.name,
        scientific_name: species.scientific_name,
        inaturalist_id: species.inaturalist_id,
        description: species.description,
        family: species.family
      });
      tree.species_id = response.data.id;
    } catch (error) {
      console.error("Gagal menyimpan spesies baru:", error);
      alert('Gagal menyimpan spesies baru ke database. Silakan coba lagi.');
      tree.species_search_term = '';
    }
  } else {
    tree.species_id = species.id;
  }
};

if (!store.projectDetails) {
  router.replace({ name: 'CalculatorForm' });
}

onMounted(() => {
  store.fetchAvailableEquations();
});

const handleCalculate = async () => {
  const success = await store.submitAndCalculate();
  if (success) {
    router.push({ name: 'CalculatorResults' });
  }
};

const goBack = () => router.back();
</script>

<template>
  <div class="page-wrapper">
    <div class="background-overlay"></div>
    <div class="content-wrapper">
       <header class="page-header">
         <button @click="goBack" class="back-button material-icons">arrow_back</button>
         <h1 class="title">Pendataan Pohon</h1>
       </header>
       <div class="trees-container">
         <div v-for="(tree, index) in store.trees" :key="tree.id" class="tree-card">
           <div class="card-header">
             <h3>Data Pohon {{ index + 1 }}</h3>
             <button @click="store.removeTree(tree.id)" class="delete-button material-icons">delete</button>
           </div>
           
           <div class="input-grid">
             <div class="input-group full-width">
               <label>Nama Pohon (Opsional)</label>
               <input type="text" v-model="tree.name" placeholder="Contoh: Pohon Jati di sudut">
             </div>

             <div class="input-group full-width species-search-container">
               <label>Spesies Pohon*</label>
               <input 
                  type="text" 
                  v-model="tree.species_search_term"
                  @input="onSearchSpecies(tree.id, $event)"
                  placeholder="Ketik nama pohon (min. 3 huruf)"
                  autocomplete="off">
               <div v-if="activeSearchIndex === tree.id && searchResults.length > 0" class="search-results">
                  <ul>
                    <li v-for="species in searchResults" :key="species.id || species.inaturalist_id" @click="selectSpecies(tree.id, species)">
                      {{ species.name }} <br>
                      <em>{{ species.scientific_name }}</em>
                    </li>
                  </ul>
               </div>
             </div>

             <div class="input-group full-width">
                <label>Pilih Persamaan Alometrik</label>
                <select v-model.number="tree.allometric_equation_id">
                  <option v-for="eq in store.availableEquations" :key="eq.id" :value="eq.id">
                    {{ eq.name }}
                  </option>
                </select>
             </div>

             <div class="input-group">
               <label>Keliling Pohon (cm)*</label>
               <input type="number" v-model.number="tree.circumference" required>
             </div>

             <div v-if="isParameterRequired(tree.id, 'height')" class="input-group">
               <label>Tinggi (m)*</label>
               <input type="number" step="0.1" v-model.number="tree.height" placeholder="Contoh: 15.5" required>
             </div>
             
             <div v-if="isParameterRequired(tree.id, 'wood_density')" class="input-group">
               <label>Kerapatan Kayu (g/cm³)*</label>
               <input type="number" step="0.01" v-model.number="tree.wood_density" placeholder="Contoh: 0.65" required>
             </div>
           </div>
         </div>
       </div>
       <div class="button-footer">
         <button @click="store.addTree" class="btn-secondary">Tambah Pohon</button>
         <button @click="handleCalculate" class="btn-primary" :disabled="store.loading">
            <span v-if="store.loading">MEMPROSES...</span>
            <span v-else>HITUNG</span>
         </button>
       </div>
    </div>
  </div>
</template>

<style scoped>
.page-wrapper {
  background-image: url('@/assets/images/forest_background.jpg');
  background-size: cover; background-position: center; min-height: 100vh;
  padding: 20px;
}
.background-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0, 0, 0, 0.6); z-index: 1;
}
.content-wrapper {
  position: relative; z-index: 2; width: 100%; max-width: 500px;
  margin: 0 auto;
}
.page-header {
  display: flex; align-items: center; padding: 16px; color: white;
}
.title { font-size: 20px; font-weight: bold; }
.back-button {
  background: none; border: none; color: white;
  font-size: 24px; cursor: pointer; margin-right: 16px;
}
.trees-container {
  background-color: rgba(255, 255, 255, 0.98);
  padding: 16px; max-height: calc(100vh - 180px); overflow-y: auto;
  border-radius: 20px;
}
.tree-card {
  background: #f0f4f1; padding: 16px; border-radius: 15px; margin-bottom: 16px;
  border: 1px solid #e0e0e0;
}
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
h3 { color: #333; margin: 0; }
.delete-button {
  background: none; color: #e74c3c; border: none; cursor: pointer; font-size: 22px;
}
.input-group { margin-bottom: 12px; }
.input-group label {
  display: block; font-size: 14px; color: #555; margin-bottom: 6px; font-weight: 500;
}
.input-group input, .input-group select {
  width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px;
  font-size: 16px; background-color: white;
}
.button-footer {
  display: flex; gap: 16px; padding: 16px 0 0 0;
}
.btn-primary, .btn-secondary {
  flex: 1; padding: 14px; border-radius: 30px;
  border: none; font-weight: bold; cursor: pointer; font-size: 16px;
}
.btn-primary { background-color: #2C8A4A; color: white; }
.btn-primary:disabled { background-color: #95a5a6; cursor: not-allowed; }
.btn-secondary { background-color: #ffffff; color: #333; }
.species-search-container {
  position: relative;
}
.search-results {
  position: absolute;
  width: 100%;
  max-height: 200px;
  overflow-y: auto;
  background-color: white;
  border: 1px solid #ccc;
  border-top: none;
  border-radius: 0 0 8px 8px;
  z-index: 10;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.search-results ul {
  list-style: none;
  padding: 0;
  margin: 0;
}
.search-results li {
  padding: 10px 15px;
  cursor: pointer;
  border-bottom: 1px solid #eee;
  line-height: 1.4;
}
.search-results li:last-child {
  border-bottom: none;
}
.search-results li:hover {
  background-color: #f0f4f1;
}
.search-results li em {
  font-size: 0.9em;
  color: #555;
}
.input-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 16px;
}
.full-width {
  grid-column: 1 / -1;
}
</style>