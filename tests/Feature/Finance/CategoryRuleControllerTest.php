<?php

namespace Tests\Feature\Finance;

use App\Models\CategoryRule;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('category-rules.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_a_user_only_sees_their_own_rules()
    {
        $user = User::factory()->create();
        CategoryRule::factory()->create(['user_id' => $user->id, 'pattern' => 'mine']);
        CategoryRule::factory()->create(['user_id' => User::factory(), 'pattern' => 'someone_elses']);

        $response = $this->actingAs($user)->get(route('category-rules.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('rules', 1)
            ->where('rules.0.pattern', 'mine')
        );
    }

    public function test_a_user_can_update_their_own_rule()
    {
        $user = User::factory()->create();
        $rule = CategoryRule::factory()->create(['user_id' => $user->id, 'pattern' => 'old']);
        $category = TransactionCategory::factory()->create(['user_id' => null]);

        $response = $this->actingAs($user)->patch(route('category-rules.update', $rule), [
            'pattern' => 'new',
            'transaction_category_id' => $category->id,
            'is_active' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('category_rules', [
            'id' => $rule->id,
            'pattern' => 'new',
            'transaction_category_id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_a_user_cannot_update_another_users_rule()
    {
        $user = User::factory()->create();
        $rule = CategoryRule::factory()->create(['user_id' => User::factory(), 'pattern' => 'old']);

        $response = $this->actingAs($user)->patch(route('category-rules.update', $rule), [
            'pattern' => 'hacked',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('category_rules', ['id' => $rule->id, 'pattern' => 'old']);
    }

    public function test_a_user_can_delete_their_own_rule()
    {
        $user = User::factory()->create();
        $rule = CategoryRule::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('category-rules.destroy', $rule));

        $response->assertRedirect();
        $this->assertDatabaseMissing('category_rules', ['id' => $rule->id]);
    }

    public function test_a_user_cannot_delete_another_users_rule()
    {
        $user = User::factory()->create();
        $rule = CategoryRule::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->delete(route('category-rules.destroy', $rule));

        $response->assertForbidden();
        $this->assertDatabaseHas('category_rules', ['id' => $rule->id]);
    }
}
