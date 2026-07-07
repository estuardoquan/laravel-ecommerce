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

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('category_id')->nullable();
            $table->uuid('subcategory_id')->nullable();
            $table->uuid('provider_id')->nullable();

            $table->boolean('active')->default(false);

            $table->integer('multiplier')->default(1);

            $table->string('name');
            $table->longText('description')->nullable();
            $table->string('slug')->unique();

            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();

            $table->foreign('subcategory_id')
                ->references('id')
                ->on('subcategories')
                ->nullOnDelete();

            $table->foreign('provider_id')
                ->references('id')
                ->on('providers')
                ->nullOnDelete();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');

            $table->string('key');
            $table->string('value');
            $table->integer('order')->nullable();
            $table->boolean('default')->nullable();


            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });

        Schema::create('plus', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->decimal('price', 8, 2)->nullable();
            $table->integer('stock')->default(0)->nullable();

            $table->string('slug')->unique()->nullable();
            $table->string('sku')->unique();

            $table->timestamps();
        });

        Schema::create('model_has_plus', function (Blueprint $table) {
            $table->uuid('plu_id');

            $table->uuid('model_id');
            $table->string('model_type');

            $table->index(['model_id', 'model_type'], 'model_has_plus_model_id_model_type_index');

            $table->foreign('plu_id')
                ->references('id')
                ->on('plus')
                ->cascadeOnDelete();

            $table->primary(['plu_id', 'model_id', 'model_type'], 'model_has_plus_plu_model_type_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('plus');
        Schema::dropIfExists('model_has_plus');
    }
};
