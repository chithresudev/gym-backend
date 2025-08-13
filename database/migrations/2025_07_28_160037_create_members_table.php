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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();

            $table->string('name', 255);
            $table->string('register_no', 50)->nullable()->unique();
            $table->string('email', 255)->unique();
            $table->string('phone', 15)->nullable()->unique();
            $table->string('address', 255)->nullable();
            $table->string('plan', 255)->nullable();
            $table->integer('age')->nullable()->unsigned();
            $table->enum('status', ['active', 'inactive'])->nullable();
            $table->date('join_date')->nullable();
            $table->enum('payment_status', ['paid', 'due', 'overdue'])->nullable();
            $table->string('image')->nullable(); // store path/filename

            $table->timestamps();

            // Foreign key relation
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
