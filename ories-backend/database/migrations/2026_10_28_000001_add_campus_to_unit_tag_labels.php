<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unit tags used to store only the unit name, so units with the same name on
 * different campuses looked identical. New tags store "Unit (Campus)"; this
 * relabels the tags saved before that change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->unitTags()->orderBy('project_tags.id')->each(function ($tag) {
            $label = "{$tag->unit_name} ({$tag->campus_name})";
            if ($tag->label !== $label) {
                DB::table('project_tags')->where('id', $tag->id)->update(['label' => $label]);
            }
        });
    }

    public function down(): void
    {
        $this->unitTags()->orderBy('project_tags.id')->each(function ($tag) {
            DB::table('project_tags')->where('id', $tag->id)->update(['label' => $tag->unit_name]);
        });
    }

    private function unitTags()
    {
        return DB::table('project_tags')
            ->join('units', 'units.id', '=', 'project_tags.unit_id')
            ->join('campuses', 'campuses.id', '=', 'units.campus_id')
            ->where('project_tags.type', 'unit')
            ->select('project_tags.id', 'project_tags.label', 'units.name as unit_name', 'campuses.name as campus_name');
    }
};
