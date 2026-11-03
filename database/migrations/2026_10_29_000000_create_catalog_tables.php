<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto Catálogo: serviços oferecidos pela oficina e suas peças padrão.
 * Preço da mão de obra em centavos; quantidade padrão em numeric(15,3), como no Estoque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('category', 60)->nullable()->index();
            $table->bigInteger('labor_price_cents');
            $table->integer('estimated_minutes');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Nome único sem diferenciar maiúsculas (a Aplicação confere antes; o índice é a última barreira).
        DB::statement('CREATE UNIQUE INDEX services_name_lower_unique ON services (lower(name))');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_labor_price_check CHECK (labor_price_cents >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_estimated_minutes_check CHECK (estimated_minutes BETWEEN 1 AND 43200)');

        Schema::create('service_standard_parts', function (Blueprint $table) {
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);

            $table->primary(['service_id', 'inventory_item_id']);
            $table->index('inventory_item_id');
        });

        DB::statement('ALTER TABLE service_standard_parts ADD CONSTRAINT service_standard_parts_quantity_check CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_standard_parts');
        Schema::dropIfExists('services');
    }
};
