<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Usuários pré-existentes eram administradores.
            $table->string('role', 20)->default('administrator')->after('password')->index();
            // Relação 1:1 com Cliente (unique garante no máximo um usuário por cliente).
            $table->foreignUuid('customer_id')->nullable()->unique()->after('role')
                ->constrained('customers')->cascadeOnDelete();
        });

        // Perfil Cliente exige vínculo; Administrador/Mecânico não podem ter.
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_customer_check CHECK (
            role IN ('administrator', 'mechanic', 'customer')
            AND ((role = 'customer') = (customer_id IS NOT NULL))
        )");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_customer_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('role');
        });
    }
};
