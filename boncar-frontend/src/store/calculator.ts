import { defineStore } from 'pinia';
import api from '@/services/api';
import axios from 'axios';

const WILAYAH_API_URL = 'https://thanksman25.github.io/api-wilayah-indonesia/api';

interface Wilayah {
  id: string;
  name: string;
}

export interface AllometricEquation {
  id: number;
  name: string;
  equation_template: string;
  reference: string;
  required_parameters?: ('circumference' | 'height' | 'wood_density')[];
}

export interface ProjectDetails {
  project_name: string;
  province: string;
  city: string;
  district: string;
  village: string;
  land_area: number;
  method: 'census' | 'sampling';
  default_equation_id: number;
  sample_area?: number;
}

export interface TreeData {
  id: number;
  name: string;
  species_search_term: string;
  species_id: number | null;
  allometric_equation_id: number;
  circumference: number;
  height?: number;
  wood_density?: number; // <-- TAMBAHAN
}

interface CalculationResults {
  project: any;
  trees: any[];
  total_biomass_ton: number;
  total_carbon_ton: number;
  biomass_per_ha_ton: number;
  carbon_per_ha_ton: number;
  settings: { [key: string]: string };
}

interface CalculatorState {
  projectDetails: ProjectDetails | null;
  trees: TreeData[];
  availableEquations: AllometricEquation[];
  results: CalculationResults | null;
  loading: boolean;
  error: string | null;
  provinces: Wilayah[];
  regencies: Wilayah[];
  districts: Wilayah[];
  villages: Wilayah[];
  loadingWilayah: boolean;
}

export const BrownEquationIDs = {
  Dry: 1,
  Moist: 2,
  Wet: 3,
};

export const useCalculatorStore = defineStore('calculator', {
  state: (): CalculatorState => ({
    projectDetails: null,
    trees: [],
    availableEquations: [],
    results: null,
    loading: false,
    error: null,
    provinces: [],
    regencies: [],
    districts: [],
    villages: [],
    loadingWilayah: false,
  }),

  actions: {
    async fetchProvinces() {
      if (this.provinces.length) return;
      this.loadingWilayah = true;
      try {
        const { data } = await axios.get(`${WILAYAH_API_URL}/provinces.json`);
        this.provinces = data;
      } catch (e) { console.error('Gagal fetch provinsi:', e); } 
      finally { this.loadingWilayah = false; }
    },
    async fetchRegencies(provinceId: string) {
      this.regencies = []; this.districts = []; this.villages = [];
      this.loadingWilayah = true;
      try {
        const { data } = await axios.get(`${WILAYAH_API_URL}/regencies/${provinceId}.json`);
        this.regencies = data;
      } catch (e) { console.error('Gagal fetch kabupaten:', e); }
      finally { this.loadingWilayah = false; }
    },
    async fetchDistricts(regencyId: string) {
      this.districts = []; this.villages = [];
      this.loadingWilayah = true;
      try {
        const { data } = await axios.get(`${WILAYAH_API_URL}/districts/${regencyId}.json`);
        this.districts = data;
      } catch (e) { console.error('Gagal fetch kecamatan:', e); }
      finally { this.loadingWilayah = false; }
    },
    async fetchVillages(districtId: string) {
      this.villages = [];
      this.loadingWilayah = true;
      try {
        const { data } = await axios.get(`${WILAYAH_API_URL}/villages/${districtId}.json`);
        this.villages = data;
      } catch (e) { console.error('Gagal fetch desa:', e); }
      finally { this.loadingWilayah = false; }
    },
    
    setProjectDetails(details: ProjectDetails) {
      this.projectDetails = details;
      this.trees = [];
      this.addTree();
    },

    addTree() {
      if (!this.projectDetails) return;
      const newTree: TreeData = {
        id: Date.now() + Math.random(),
        name: '',
        species_search_term: '',
        species_id: null,
        allometric_equation_id: this.projectDetails.default_equation_id,
        circumference: 0,
        height: undefined,
        wood_density: undefined,
      };
      this.trees.push(newTree);
    },
    
    removeTree(treeId: number) {
      if (this.trees.length > 1) {
        this.trees = this.trees.filter(tree => tree.id !== treeId);
      } else {
        alert('Minimal harus ada satu data pohon.');
      }
    },

    async fetchAvailableEquations() {
      if (this.availableEquations.length > 0) return;
      this.loading = true;
      try {
        const { data } = await api.get<AllometricEquation[]>('/formulas');
        this.availableEquations = data;
      } catch (err) {
        this.error = 'Gagal memuat daftar rumus.';
      } finally {
        this.loading = false;
      }
    },
    
    async submitAndCalculate() {
      if (!this.projectDetails || this.trees.some(t => !t.species_id)) {
        this.error = 'Pastikan semua data proyek dan spesies pohon telah diisi.';
        alert(this.error);
        return false;
      }
      this.loading = true;
      this.error = null;

      const payload = {
        ...this.projectDetails,
        trees: this.trees.map(tree => ({
          species_id: tree.species_id,
          allometric_equation_id: tree.allometric_equation_id,
          parameters: {
            circumference: tree.circumference,
            height: tree.height,
            wood_density: tree.wood_density,
          },
        })),
      };

      try {
        const { data } = await api.post('/calculator-projects', payload);
        this.results = data;
        return true;
      } catch (err: any) {
        console.error("Calculation failed:", err.response);
        let detailedError = 'Terjadi kesalahan yang tidak diketahui.';
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            const errorMessages = Object.keys(errors).map(key => `${key}: ${errors[key].join(', ')}`);
            detailedError = `Data tidak valid:\n- ${errorMessages.join('\n- ')}`;
        } else if (err.response?.data?.message) {
            detailedError = err.response.data.message;
        } else {
            detailedError = err.message;
        }
        this.error = `GAGAL! Request ditolak oleh server.\n\nError: ${detailedError}`;
        alert(this.error);
        return false;
      } finally {
        this.loading = false;
      }
    },
  },
});