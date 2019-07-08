<?php

declare(strict_types=1);

use Faker\Generator as Faker;

$factory->define(App\Models\Appointment::class, function (Faker $faker) {
    return [
        'user_id' => function () {
            return factory(\App\Models\User::class)->create()->id;
        },
        'clinic_id' => function () {
            return factory(\App\Models\Clinic::class)->create()->id;
        },
        'start_at' => \Carbon\CarbonImmutable::today()->addWeek(),
    ];
});
