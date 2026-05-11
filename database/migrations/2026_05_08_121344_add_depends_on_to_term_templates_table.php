<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('term_templates', function (Blueprint $table) {
        // Armazena o term_type do qual este template depende
        // Ex: 'devolucao_celular' depende de 'responsabilidade_celular'
        $table->string('depends_on')->nullable()->after('term_type');
    });
}

public function down(): void
{
    Schema::table('term_templates', function (Blueprint $table) {
        $table->dropColumn('depends_on');
    });
}
};
