<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_generation_logs', function (Blueprint $table) {
            $table->id();
            
            // Aqui podemos manter foreignId, pois sua nova tabela term_templates usa $table->id() (BIGINT)
            $table->foreignId('term_template_id')->constrained()->onDelete('cascade');
            
            // Para tabelas antigas do Snipe-IT, precisamos forçar o tipo para UNSIGNED INTEGER normal
            $table->integer('asset_id')->unsigned();
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            
            $table->integer('assigned_user_id')->unsigned()->nullable();
            $table->foreign('assigned_user_id')->references('id')->on('users')->onDelete('set null');
            
            $table->integer('generated_by_id')->unsigned();
            $table->foreign('generated_by_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->string('generated_file_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_generation_logs');
    }
};