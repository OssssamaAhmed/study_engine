<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            $table->longText('research_context')->nullable()->after('mega_project_md');
        });
    }

    public function down(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            $table->dropColumn('research_context');
        });
    }
};
