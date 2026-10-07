<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progres scan komponen untuk baris bundle pada QC resi.
 *
 * QC hanya men-scan barang fisik (komponen). Baris qc_scan_resi_items untuk SKU bundle
 * tetap menyimpan jumlah bundle yang sudah lengkap, sedangkan tabel ini menyimpan
 * snapshot komposisi bundle saat QC dimulai beserta qty komponen yang sudah discan.
 * Tabel baru, tidak mengubah tabel yang sudah ada.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('qc_scan_resi_bundle_components')) {
            return;
        }

        Schema::create('qc_scan_resi_bundle_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_scan_resi_item_id')->constrained('qc_scan_resi_items')->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('items')->restrictOnDelete();
            $table->string('component_sku', 100);
            $table->unsignedInteger('qty_per_bundle');
            $table->unsignedInteger('scanned_qty')->default(0);
            $table->timestamps();

            $table->unique(['qc_scan_resi_item_id', 'component_item_id'], 'qc_bundle_comp_unique');
            $table->index('component_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_scan_resi_bundle_components');
    }
};
