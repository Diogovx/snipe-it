<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    
            $table->string('file_name');               
            $table->json('allowed_categories')->nullable();
            $table->json('field_map')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_templates');
    }
};