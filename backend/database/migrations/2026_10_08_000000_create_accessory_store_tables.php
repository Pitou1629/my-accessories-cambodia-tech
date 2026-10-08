<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_metadata')) {
            Schema::create('app_metadata', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->text('value');
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('phone')->default('');
                $table->string('role');
                $table->text('createdAt');
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->string('category');
                $table->float('price');
                $table->integer('stock');
                $table->integer('sold')->default(0);
                $table->text('image');
                $table->text('description');
                $table->boolean('featured')->default(false);
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('customerId');
                $table->string('customerName');
                $table->text('items');
                $table->float('total');
                $table->string('paymentMethod');
                $table->text('shippingAddress');
                $table->string('status');
                $table->text('createdAt');
            });
        }

        if (! Schema::hasTable('visits')) {
            Schema::create('visits', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->text('path');
                $table->text('page');
                $table->string('customerId');
                $table->text('createdAt');
            });
        }

        if (! Schema::hasTable('feedback')) {
            Schema::create('feedback', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('rating');
                $table->text('message');
                $table->text('createdAt');
            });
        }

        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('customerId');
                $table->string('sender');
                $table->string('name');
                $table->text('message');
                $table->text('createdAt');
            });
        }

        if (! Schema::hasTable('api_tokens')) {
            Schema::create('api_tokens', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('token')->unique();
                $table->text('user');
                $table->text('createdAt');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
