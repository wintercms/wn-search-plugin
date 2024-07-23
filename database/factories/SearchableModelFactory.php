<?php

namespace Winter\Search\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Winter\Search\Tests\Fixtures\SearchableModel;

class SearchableModelFactory extends Factory
{
    protected $model = SearchableModel::class;

    public function definition()
    {
        $faker = fake();
        $faker->addProvider(\Faker\Provider\en_US\Text::class);

        return [
            'title' => $faker->sentence(),
            'description' => $faker->sentence(30),
            'content' => $faker->paragraph(16),
            'keywords' => $faker->words(8, true),
        ];
    }
}
