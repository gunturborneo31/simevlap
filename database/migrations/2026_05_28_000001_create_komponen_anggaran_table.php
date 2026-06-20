<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('komponen_anggaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('komponen_anggaran')->onDelete('cascade');
            $table->string('kode');
            $table->enum('jenis', ['program', 'kegiatan', 'sub_kegiatan']);
            $table->string('sub_unit');
            $table->string('urusan');
            $table->string('bidang_urusan');
            $table->string('nama_komponen');
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('komponen_anggaran');
    }
};
