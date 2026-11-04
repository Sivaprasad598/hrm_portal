<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Lead Finder API keys (GET /api/lead-finder/projects/search): one per administrator, created on the finder's
     * "API access" page and sent as "Authorization: Bearer <key>". Only the SHA-256 hash of the key is stored.
     */
    public function up(): void
    {
        Schema::create('lead_finder_api_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->string('key_hash', 64)->unique();
            $table->string('last_four', 4);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_finder_api_keys');
    }

};
