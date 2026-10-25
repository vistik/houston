<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Front page picks used to save an empty list for "show all"; now an empty list hides that section
     * and null shows all, so saved empty lists become null to keep showing what they showed before.
     */
    public function up(): void
    {
        $this->convert(fn (mixed $ids): mixed => $ids === [] ? null : $ids);
    }

    public function down(): void
    {
        $this->convert(fn (mixed $ids): mixed => $ids ?? []);
    }

    /**
     * @param  callable(mixed): mixed  $convert
     */
    private function convert(callable $convert): void
    {
        $setting = DB::table('settings')->where('key', 'home_pins')->first();

        if ($setting === null) {
            return;
        }

        $value = json_decode($setting->value, true);

        if (! is_array($value)) {
            return;
        }

        DB::table('settings')->where('key', 'home_pins')->update([
            'value' => json_encode([
                'teams' => $convert($value['teams'] ?? null),
                'repositories' => $convert($value['repositories'] ?? null),
            ]),
        ]);
    }
};
