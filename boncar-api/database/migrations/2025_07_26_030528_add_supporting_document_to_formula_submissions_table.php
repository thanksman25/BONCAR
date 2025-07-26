<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formula_submissions', function (Blueprint $table) {
            $table->string('supporting_document_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('formula_submissions', function (Blueprint $table) {
            $table->dropColumn('supporting_document_path');
        });
    }
};