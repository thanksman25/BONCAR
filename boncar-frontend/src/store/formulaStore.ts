// #### File: src/store/formulaStore.ts

import { defineStore } from 'pinia';
import api from '@/services/api';

// Interface untuk data formulir pengajuan oleh pengguna
interface UserFormulaSubmission {
  name: string;
  reference: string;
  formula_agb: string;
  formula_bgb: string;
  formula_carbon: string;
  required_parameters: string[];
  reference_file: File | null;
}

// Interface untuk data rumus yang ada (untuk admin)
export interface AllometricEquation {
  id: number;
  name: string;
  reference: string;
  equation_template: string | null;
  formula_agb: string;
  formula_bgb: string;
  formula_carbon: string;
  required_parameters: ('circumference' | 'height' | 'wood_density')[];
}

interface FormulaState {
  formulas: AllometricEquation[];
  selectedFormula: AllometricEquation | null;
  loading: boolean;
  error: string | null;
}

export const useFormulaStore = defineStore('formula', {
  state: (): FormulaState => ({
    formulas: [],
    selectedFormula: null,
    loading: false,
    error: null,
  }),

  actions: {
    // --- ACTION BARU UNTUK PENGGUNA ---
    async submitUserFormula(formData: UserFormulaSubmission) {
      this.loading = true;
      this.error = null;

      const data = new FormData();
      data.append('name', formData.name);
      data.append('reference', formData.reference);
      data.append('formula_agb', formData.formula_agb || '');
      data.append('formula_bgb', formData.formula_bgb || '');
      data.append('formula_carbon', formData.formula_carbon || '');
      
      formData.required_parameters.forEach((param: string) => {
        data.append('required_parameters[]', param);
      });
      
      if (formData.reference_file) {
        data.append('reference_file', formData.reference_file);
      }

      try {
        await api.post('/formulas/submit', data, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        alert('Pengajuan rumus berhasil dikirim!');
        return true;
      } catch (err: any) {
        this.error = 'Gagal mengirim pengajuan. Pastikan semua data terisi.';
        console.error(err);
        alert(this.error);
        return false;
      } finally {
        this.loading = false;
      }
    },

    // --- ACTIONS UNTUK ADMIN (Tidak Berubah) ---
    async fetchFormulas() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await api.get<AllometricEquation[]>('/formulas');
        this.formulas = data;
      } catch (err: any) {
        this.error = 'Gagal memuat daftar rumus.';
        console.error(err);
      } finally {
        this.loading = false;
      }
    },

    async fetchFormulaById(id: number) {
        this.loading = true;
        this.selectedFormula = null;
        try {
            const { data } = await api.get<AllometricEquation>(`/admin/formulas/${id}`);
            this.selectedFormula = data;
        } catch (err) {
            this.error = 'Gagal mengambil detail rumus.';
            console.error(err);
        } finally {
            this.loading = false;
        }
    },

    async createFormula(payload: Omit<AllometricEquation, 'id' | 'equation_template'>) {
        try {
            const { data } = await api.post('/admin/formulas', payload);
            this.formulas.push(data);
        } catch (err: any) {
            console.error(err);
            if (err.response?.data?.errors) {
                const errors = err.response.data.errors;
                throw new Error(Object.values(errors).flat().join('\n'));
            }
            throw new Error('Gagal membuat rumus baru.');
        }
    },

    async updateFormula(id: number, payload: Omit<AllometricEquation, 'id' | 'equation_template'>) {
      try {
        const { data } = await api.put(`/admin/formulas/${id}`, payload);
        const index = this.formulas.findIndex(f => f.id === id);
        if (index !== -1) {
          this.formulas[index] = data;
        }
        if (this.selectedFormula?.id === id) {
            this.selectedFormula = data;
        }
      } catch (err: any) {
        console.error(err);
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            throw new Error(Object.values(errors).flat().join('\n'));
        }
        throw new Error('Gagal memperbarui rumus.');
      }
    },

    async deleteFormula(id: number) {
      try {
        await api.delete(`/admin/formulas/${id}`);
        this.formulas = this.formulas.filter(f => f.id !== id);
      } catch (err: any) {
        console.error(err);
        if (err.response && err.response.status === 409) {
            throw new Error(err.response.data.message);
        }
        throw new Error('Gagal menghapus rumus.');
      }
    },
  },
});