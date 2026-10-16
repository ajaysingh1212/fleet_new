<?php

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'type' => ConversationType::Group,
            'name' => fake()->words(3, true),
            'description' => null,
            'avatar' => null,
            'owner_id' => User::factory(),
            'status' => ConversationStatus::Active,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConversationType::Private,
            'name' => null,
            'owner_id' => null,
        ]);
    }
}
