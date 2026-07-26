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
        // Step 1: Add new values to enum
        \DB::statement("ALTER TABLE users MODIFY role ENUM('MOTHER', 'NAKES', 'user', 'admin') DEFAULT 'MOTHER'");
        
        // Step 2: Update existing data
        \DB::statement("UPDATE users SET role = 'user' WHERE role = 'MOTHER'");
        \DB::statement("UPDATE users SET role = 'admin' WHERE role = 'NAKES'");
        
        // Step 3: Remove old values from enum
        \DB::statement("ALTER TABLE users MODIFY role ENUM('user', 'admin') DEFAULT 'user'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add old values back
        \DB::statement("ALTER TABLE users MODIFY role ENUM('user', 'admin', 'MOTHER', 'NAKES') DEFAULT 'user'");
        
        // Step 2: Revert data
        \DB::statement("UPDATE users SET role = 'MOTHER' WHERE role = 'user'");
        \DB::statement("UPDATE users SET role = 'NAKES' WHERE role = 'admin'");
        
        // Step 3: Remove new values
        \DB::statement("ALTER TABLE users MODIFY role ENUM('MOTHER', 'NAKES') DEFAULT 'MOTHER'");
    }
};
