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
        // Le categorie temporanee vivono nelle tabelle principali, distinte
        // dalla configurazione comune tramite monthly_budget_id.
        Schema::dropIfExists('monthly_budget_subcategories');
        Schema::dropIfExists('monthly_budget_categories');

        if (! Schema::hasColumn('budget_categories', 'monthly_budget_id')) {
            Schema::table('budget_categories', function (Blueprint $table) {
                $table->foreignId('monthly_budget_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('budget_subcategories', 'monthly_budget_id')) {
            Schema::table('budget_subcategories', function (Blueprint $table) {
                $table->foreignId('monthly_budget_id')->nullable()->after('budget_category_id')->constrained()->cascadeOnDelete();
            });
        }

        // Il nuovo indice va creato prima di rimuovere il vecchio: quello
        // esistente è l'indice che sorregge la foreign key sulla colonna
        // iniziale, e MySQL rifiuta di lasciarla scoperta.
        Schema::table('budget_categories', function (Blueprint $table) {
            $table->unique(['user_id', 'monthly_budget_id', 'name'], 'budget_categories_scope_name_unique');
            $table->dropUnique('budget_categories_user_id_name_unique');
        });

        Schema::table('budget_subcategories', function (Blueprint $table) {
            $table->unique(['budget_category_id', 'monthly_budget_id', 'name'], 'budget_subcategories_scope_name_unique');
            $table->dropUnique('budget_subcategories_budget_category_id_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_subcategories', function (Blueprint $table) {
            $table->unique(['budget_category_id', 'name'], 'budget_subcategories_budget_category_id_name_unique');
            $table->dropUnique('budget_subcategories_scope_name_unique');
            $table->dropConstrainedForeignId('monthly_budget_id');
        });

        Schema::table('budget_categories', function (Blueprint $table) {
            $table->unique(['user_id', 'name'], 'budget_categories_user_id_name_unique');
            $table->dropUnique('budget_categories_scope_name_unique');
            $table->dropConstrainedForeignId('monthly_budget_id');
        });

        Schema::create('monthly_budget_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_budget_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->default('#3b82f6');
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->unique(['monthly_budget_id', 'name']);
        });

        Schema::create('monthly_budget_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_budget_category_id')->constrained('monthly_budget_categories')->cascadeOnDelete();
            $table->string('name');
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->unique(['monthly_budget_category_id', 'name'], 'monthly_budget_subcats_unique');
        });
    }
};
