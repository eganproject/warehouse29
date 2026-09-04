<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resi_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resi_id')->unique()->constrained('resis')->cascadeOnDelete();
            $table->foreignId('qc_scan_resi_id')->nullable()->constrained('qc_scan_resis')->nullOnDelete();
            $table->foreignId('packer_scan_out_id')->nullable()->constrained('packer_scan_outs')->nullOnDelete();
            $table->string('stage', 30);
            $table->text('reason')->nullable();
            $table->unsignedInteger('returned_stock_qty')->default(0);
            $table->timestamp('stock_returned_at')->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('canceled_at');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['stage', 'canceled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resi_cancellations');
    }
};
