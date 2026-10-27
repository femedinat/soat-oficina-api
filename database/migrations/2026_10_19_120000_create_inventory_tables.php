<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto Estoque: peças/insumos, extrato de movimentações e reservas por OS.
 * Quantidades em numeric(15,3) (insumo fracionado, ex.: 4,5 L); preço em centavos.
 * As CHECK constraints repetem as invariantes do domínio como última barreira no banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku', 40)->unique();
            $table->string('name', 120)->index();
            $table->text('description')->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('type', 10);
            $table->string('unit', 5);
            $table->bigInteger('price_cents');
            $table->decimal('physical_quantity', 15, 3)->default(0);
            $table->decimal('reserved_quantity', 15, 3)->default(0);
            $table->decimal('minimum_quantity', 15, 3)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_type_check CHECK (type IN ('PECA', 'INSUMO'))");
        DB::statement("ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_unit_check CHECK (unit IN ('UN', 'L', 'KG', 'M'))");
        DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_price_check CHECK (price_cents >= 0)');
        DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_balance_check CHECK (reserved_quantity >= 0 AND minimum_quantity >= 0 AND physical_quantity >= reserved_quantity)');

        Schema::create('stock_movements', function (Blueprint $table) {
            // Sequência para ordenar o extrato de forma estável; o id público é o UUID.
            $table->bigIncrements('sequence');
            $table->uuid('id')->unique();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity', 15, 3);
            $table->decimal('physical_after', 15, 3);
            $table->decimal('reserved_after', 15, 3);
            $table->string('reason', 255)->nullable();
            $table->string('reference', 100)->nullable()->index();
            $table->timestampTz('occurred_at');

            $table->index(['inventory_item_id', 'sequence']);
        });

        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_check CHECK (quantity > 0)');

        Schema::create('stock_reservations', function (Blueprint $table) {
            // Uma reserva por OS. Sem FK: a OS pertence a outro contexto.
            $table->uuid('service_order_id')->primary();
            $table->string('status', 10);
            $table->timestamps();
        });

        Schema::create('stock_reservation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('service_order_id')->constrained('stock_reservations', 'service_order_id')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);

            $table->unique(['service_order_id', 'inventory_item_id']);
        });

        DB::statement('ALTER TABLE stock_reservation_lines ADD CONSTRAINT stock_reservation_lines_quantity_check CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservation_lines');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_items');
    }
};
