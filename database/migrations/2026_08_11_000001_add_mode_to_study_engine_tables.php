<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables that gain a `mode` column but keep their existing indexes as-is. */
    private const PLAIN_TABLES = ['transcripts', 'concepts', 'chunks', 'exercises', 'notifications', 'exam_prep_sessions'];

    public function up(): void
    {
        foreach (self::PLAIN_TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('mode')->default('ai_security')->index();
            });
        }

        // phase_number was uniquely-indexed alone; a second track's phases
        // reuse the same numbers, so the constraint must become composite.
        // Column add MUST precede the index swap (SQLite needs the column to
        // exist before it can be indexed).
        Schema::table('topics', function (Blueprint $t) {
            $t->string('mode')->default('ai_security');
            $t->dropUnique(['phase_number']);
            $t->unique(['phase_number', 'mode']);
        });

        Schema::table('phase_progress', function (Blueprint $t) {
            $t->string('mode')->default('ai_security');
            $t->dropUnique(['phase_number']);
            $t->unique(['phase_number', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $t) {
            $t->dropUnique(['phase_number', 'mode']);
            $t->unique('phase_number');
            $t->dropColumn('mode');
        });

        Schema::table('phase_progress', function (Blueprint $t) {
            $t->dropUnique(['phase_number', 'mode']);
            $t->unique('phase_number');
            $t->dropColumn('mode');
        });

        foreach (self::PLAIN_TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('mode');
            });
        }
    }
};
