<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::create('membership_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('membership_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedInteger('fee_cents');
            $table->unsignedSmallInteger('validity_days')->default(30);
            $table->json('benefits');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->string('membership_no', 40)->unique();
            $table->foreignId('membership_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_level_id')->constrained()->restrictOnDelete();
            $table->string('full_name', 150)->index();
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('membership_expires_at')->nullable()->index();
            $table->boolean('is_deactivated')->default(false)->index();
            $table->timestamps();
        });
        Schema::create('membership_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_no', 40)->unique();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_level_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->string('category_name', 80);
            $table->string('level_name', 80);
            $table->unsignedInteger('base_fee_cents');
            $table->unsignedTinyInteger('discount_percent');
            $table->unsignedInteger('discount_cents');
            $table->unsignedInteger('total_cents');
            $table->unsignedSmallInteger('validity_days');
            $table->timestamp('payment_due_at')->index();
            $table->timestamp('membership_starts_at')->nullable();
            $table->timestamp('membership_ends_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamps();
            $table->index(['member_id', 'status']);
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_no', 40)->unique();
            $table->foreignId('membership_transaction_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('method', 20);
            $table->string('external_reference', 100)->nullable()->unique();
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'membership_transactions', 'members', 'membership_levels', 'membership_categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
