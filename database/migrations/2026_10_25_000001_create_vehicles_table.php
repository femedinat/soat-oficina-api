<?php

declare(strict_types=1);

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
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->nullable()->index()->constrained('customers');
            $table->string('license_plate', 7)->unique();
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->smallInteger('year');
            $table->string('color', 50)->nullable();
            $table->integer('mileage')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
