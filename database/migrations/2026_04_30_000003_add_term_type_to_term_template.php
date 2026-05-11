<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // database/migrations/xxxx_add_term_type_to_term_templates.php

public function up(): void
{
    Schema::table('term_templates', function (Blueprint $table) {
        $table->string('term_type')->nullable()->after('name');
    });
}

};