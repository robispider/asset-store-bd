<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The optional note a holder writes when handing over a duty. It was only kept in the notice log,
 * so the recipient's "Waiting for you" card could not show it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gov_role_handshakes') && ! Schema::hasColumn('gov_role_handshakes', 'note')) {
            Schema::table('gov_role_handshakes', function (Blueprint $t) {
                $t->string('note', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gov_role_handshakes', 'note')) {
            Schema::table('gov_role_handshakes', function (Blueprint $t) {
                $t->dropColumn('note');
            });
        }
    }
};
