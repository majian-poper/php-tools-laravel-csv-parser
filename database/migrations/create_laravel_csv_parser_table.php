<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'csv_rows',
            function (Blueprint $table) {
                $table->id();
                $table->string('file_type');
                $table->unsignedBigInteger('file_id');
                $table->unsignedInteger('no');
                $table->jsonb('content');
                $table->timestamps();

                $table->unique(['file_type', 'file_id', 'no'], 'csv_rows_unique_index');
            }
        );

        Schema::create(
            'csv_parsed_rows',
            function (Blueprint $table) {
                $table->id();
                $table->string('file_type');
                $table->unsignedBigInteger('file_id');
                $table->unsignedInteger('no');
                $table->unsignedBigInteger('row_id');
                $table->unsignedInteger('order_number');
                $table->string('model_type')->nullable();
                $table->unsignedBigInteger('model_id')->nullable();
                $table->string('model_unique_key')->nullable();
                $table->jsonb('values');
                $table->jsonb('errors');
                $table->timestamps();

                $table->index(['file_type', 'file_id', 'no'], 'csv_parsed_rows_file_index');
                $table->index(['row_id'], 'csv_parsed_rows_row_id_index');
                $table->index(['model_type', 'model_id'], 'csv_parsed_rows_model_index');
                $table->index(['model_type', 'model_unique_key'], 'csv_parsed_rows_model_unique_key_index');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csv_parsed_rows');

        Schema::dropIfExists('csv_rows');
    }
};
