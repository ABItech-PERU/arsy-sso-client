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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'sso_id')) {
                $table->string('sso_id')->nullable()->unique()->after('id')->comment('ID inmutable del SSO');
            }
            if (!Schema::hasColumn('users', 'sso_last_login_at')) {
                $table->timestamp('sso_last_login_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'sso_id',
                'sso_last_login_at'
            ]);
        });
    }
};
