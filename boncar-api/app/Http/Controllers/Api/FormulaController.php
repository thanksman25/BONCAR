<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AllometricEquation;
use App\Models\FormulaSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; // Pastikan ini di-import

class FormulaController extends Controller
{
    /**
     * Mengambil semua rumus yang sudah disetujui (aktif).
     */
    public function index()
    {
        $equations = AllometricEquation::orderBy('name', 'asc')->get();
        return response()->json($equations);
    }

    /**
     * Mengajukan rumus baru oleh pengguna.
     * Disesuaikan dengan frontend NewSubmissionPage.vue
     */
    public function submit(Request $request)
    {
        // 1. Sesuaikan aturan validasi agar cocok dengan nama field dari frontend
        $validatedData = $request->validate([
            'formula_name' => 'required|string|max:255',
            'equation_template' => 'required|string',
            'reference' => 'required|string|max:255',
            'description' => 'nullable|string',
            'supporting_document' => 'required|file|mimes:pdf|max:5120', // PDF, maks 5MB
        ]);

        $filePath = null;
        if ($request->hasFile('supporting_document')) {
            // 2. Unggah file ke disk 'supabase'
            // Pastikan konfigurasi di filesystems.php sudah benar
            $filePath = $request->file('supporting_document')->store('formula_submissions', 'supabase');
        }

        // 3. Simpan data ke database
        FormulaSubmission::create([
            'user_id' => Auth::id(),
            'formula_name' => $validatedData['formula_name'],
            'equation_template' => $validatedData['equation_template'],
            'reference' => $validatedData['reference'],
            'description' => $validatedData['description'],
            'supporting_document_path' => $filePath, // Simpan path dari Supabase
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Formula submitted successfully for review.'], 201);
    }

    // --- METODE KHUSUS ADMIN (Tidak ada perubahan) ---

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'reference' => 'required|string|max:255',
            'formula_agb' => 'required|string',
            'formula_bgb' => 'required|string',
            'formula_carbon' => 'required|string',
            'required_parameters' => 'nullable|array',
        ]);

        if (isset($validatedData['required_parameters'])) {
            foreach ($validatedData['required_parameters'] as $param) {
                if (!in_array($param, ['circumference', 'height', 'wood_density'])) {
                    return response()->json(['message' => "Invalid parameter: {$param}"], 422);
                }
            }
        }
        $equation = AllometricEquation::create($validatedData);
        return response()->json($equation, 201);
    }

    public function show(AllometricEquation $equation)
    {
        return response()->json($equation);
    }
    
    public function getSubmissions()
    {
        $submissions = FormulaSubmission::with('user:id,name,email')->latest()->get();
        return response()->json($submissions);
    }

    public function approve(FormulaSubmission $submission)
    {
        if ($submission->status !== 'pending') {
            return response()->json(['message' => 'This submission has already been reviewed.'], 409);
        }

        $equation = AllometricEquation::create([
            'name' => $submission->formula_name,
            'equation_template' => $submission->equation_template,
            'reference' => $submission->reference,
            'submission_id' => $submission->id,
            'formula_agb' => $submission->equation_template,
            'formula_bgb' => 'AGB * 0.26',
            'formula_carbon' => '(AGB + BGB) * 0.47',
            'required_parameters' => ['circumference'],
        ]);

        $submission->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Formula approved and is now active.', 'equation' => $equation]);
    }

    public function reject(Request $request, FormulaSubmission $submission)
    {
        $request->validate(['rejection_reason' => 'required|string|max:1000']);

        if ($submission->status !== 'pending') {
            return response()->json(['message' => 'This submission has already been reviewed.'], 409);
        }

        $submission->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Submission has been rejected.']);
    }

    public function update(Request $request, AllometricEquation $equation)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'reference' => 'required|string|max:255',
            'formula_agb' => 'required|string',
            'formula_bgb' => 'required|string',
            'formula_carbon' => 'required|string',
            'required_parameters' => 'nullable|array',
        ]);
        
        if (isset($validatedData['required_parameters'])) {
            foreach ($validatedData['required_parameters'] as $param) {
                if (!in_array($param, ['circumference', 'height', 'wood_density'])) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'required_parameters' => ["Invalid parameter selected: {$param}"]
                        ]
                    ], 422);
                }
            }
        }
        
        $validatedData['equation_template'] = $validatedData['formula_agb'];

        $equation->update($validatedData);

        return response()->json([
            'message' => 'Formula updated successfully.',
            'equation' => $equation,
        ]);
    }

    public function destroy(AllometricEquation $equation)
    {
        $isUsedInProjects = DB::table('calculation_projects')->where('allometric_equation_id', $equation->id)->exists();
        $isUsedInTrees = DB::table('trees')->where('allometric_equation_id', $equation->id)->exists();

        if ($isUsedInProjects || $isUsedInTrees) {
            return response()->json(['message' => 'Cannot delete formula because it is currently used in a calculation project.'], 409);
        }

        $equation->delete();

        return response()->noContent();
    }
}