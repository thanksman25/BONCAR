<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AllometricEquation;
use App\Models\FormulaSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SupabaseStorageService;

class FormulaController extends Controller
{
    public function index()
    {
        $equations = AllometricEquation::orderBy('name', 'asc')->get();
        return response()->json($equations);
    }

    /**
     * Mengajukan rumus baru oleh pengguna.
     * Disesuaikan dengan frontend NewSubmissionPage.vue
     */
    public function submit(Request $request, SupabaseStorageService $supabase)
    {
        $validatedData = $request->validate([
            'formula_name' => 'required|string|max:255',
            'equation_template' => 'required|string',
            'reference' => 'required|string|max:255',
            'description' => 'nullable|string',
            'supporting_document' => 'required|file|mimes:pdf|max:5120',
        ]);

        $filePath = null;

        if ($request->hasFile('supporting_document')) {
            $file = $request->file('supporting_document');
            $filename = uniqid() . '-' . $file->getClientOriginalName();
            $storagePath = 'formula_submissions/' . $filename;

            $uploadSuccess = $supabase->upload($storagePath, $file);

            if ($uploadSuccess) {
                $filePath = $supabase->getPublicUrl($storagePath);
            } else {
                return response()->json(['message' => 'Failed to upload file to storage.'], 500);
            }
        }

        FormulaSubmission::create([
            'user_id' => Auth::id(),
            'formula_name' => $validatedData['formula_name'],
            'equation_template' => $validatedData['equation_template'],
            'reference' => $validatedData['reference'],
            'description' => $validatedData['description'],
            'supporting_document_path' => $filePath,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Formula submitted successfully for review.'], 201);
    }

    // --- METODE KHUSUS ADMIN (Tidak diubah) ---

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
