<?php

namespace Database\Seeders;

use App\Enums\ScheduleKind;
use App\Models\Account;
use App\Models\ClassPassPlan;
use App\Models\ClassType;
use App\Models\Location;
use App\Models\Room;
use App\Models\TrainerType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ClassPassPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $account = Account::where('slug', 'charmpole')->first();

        if (! $account) {
            $this->command?->warn('Class pass demo data skipped: charmpole account was not found.');

            return;
        }

        $location = $account->locations()->firstOrCreate(
            ['slug' => 'charmpole'],
            [
                'name' => 'Charmpole',
                'address' => 'Київ, проспект Берестейський (Перемоги), 56',
                'timezone' => 'Europe/Kyiv',
                'is_active' => true,
            ],
        );
        $rooms = $this->rooms($account, $location);
        $classTypes = $this->classTypes($account);
        $trainerTypes = $this->trainerTypes($account);

        foreach ($this->plans() as $plan) {
            $classPassPlan = ClassPassPlan::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'slug' => $plan['slug'],
                ],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'price_cents' => $plan['price_cents'],
                    'currency' => $account->default_currency,
                    'sessions_count' => $plan['sessions_count'],
                    'validity_days' => 30,
                    'available_from_time' => null,
                    'available_until_time' => $plan['available_until_time'],
                    'allows_any_time' => false,
                    'any_time_addon_price_cents' => null,
                    'is_trial' => $plan['is_trial'],
                    'is_active' => true,
                    'sort_order' => $plan['sort_order'],
                ],
            );

            $classPassPlan->classTypes()->sync(collect($plan['class_types'])->map(fn (string $name): int => $classTypes[$name]->id)->all());
            $classPassPlan->trainerTypes()->sync(collect($plan['trainer_types'])->map(fn (string $name): int => $trainerTypes[$name]->id)->all());
            $classPassPlan->rooms()->sync(collect($plan['rooms'])->map(fn (string $name): int => $rooms[$name]->id)->all());
        }
    }

    /**
     * @return array<string, Room>
     */
    private function rooms(Account $account, Location $location): array
    {
        return collect([
            ['Великий зал', 'big-hall', 12],
            ['Малий зал', 'small-hall', 6],
        ])->mapWithKeys(function (array $room) use ($account, $location): array {
            [$name, $slug, $capacity] = $room;

            return [$name => $account->rooms()->firstOrCreate(
                [
                    'location_id' => $location->id,
                    'slug' => $slug,
                ],
                [
                    'name' => $name,
                    'capacity' => $capacity,
                    'is_active' => true,
                ],
            )];
        })->all();
    }

    /**
     * @return array<string, ClassType>
     */
    private function classTypes(Account $account): array
    {
        $directions = $account->activityDirections()->get()->keyBy('name');

        return collect([
            ['Pole Dance', 'Pole Dance', ScheduleKind::GroupClass, '#c7f000', 60, 12],
            ['Pole Kids', 'Kids', ScheduleKind::GroupClass, '#ffffff', 60, 8],
            ['Exot Easy', 'Exotic', ScheduleKind::GroupClass, '#c7f000', 60, 10],
            ['Exot', 'Exotic', ScheduleKind::GroupClass, '#ff008c', 60, 10],
            ['Exot Middle', 'Exotic', ScheduleKind::GroupClass, '#ffad00', 60, 10],
            ['Stretching', 'Stretching', ScheduleKind::GroupClass, '#ff2b2b', 60, 12],
            ['Tricks', 'Acro', ScheduleKind::GroupClass, '#ff008c', 60, 10],
            ['Acro class*', 'Acro', ScheduleKind::GroupClass, '#ffffff', 60, 12],
            ['Індивідуальне 60 хв', null, ScheduleKind::PrivateLesson, '#d80a7d', 60, 2],
            ['Індивідуальне 90 хв', null, ScheduleKind::PrivateLesson, '#d80a7d', 90, 2],
            ['Оренда 60 хв', null, ScheduleKind::RoomRental, '#3b223f', 60, 12],
            ['Оренда 90 хв', null, ScheduleKind::RoomRental, '#3b223f', 90, 12],
            ['Оренда 120 хв', null, ScheduleKind::RoomRental, '#3b223f', 120, 12],
        ])->mapWithKeys(function (array $classType) use ($account, $directions): array {
            [$name, $direction, $kind, $color, $duration, $capacity] = $classType;

            return [$name => $account->classTypes()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'activity_direction_id' => $direction ? $directions[$direction]?->id : null,
                    'name' => $name,
                    'description' => null,
                    'color' => $color,
                    'schedule_kind' => $kind->value,
                    'default_duration_minutes' => $duration,
                    'booking_cutoff_minutes' => 60,
                    'default_capacity' => $capacity,
                    'is_active' => true,
                ],
            )];
        })->all();
    }

    /**
     * @return array<string, TrainerType>
     */
    private function trainerTypes(Account $account): array
    {
        return collect([
            ['Тренер', 'user-round', '#3B223F', true, 10],
            ['ТОП-тренер', 'crown', '#D80A7D', false, 20],
        ])->mapWithKeys(function (array $trainerType) use ($account): array {
            [$name, $icon, $color, $isDefault, $sortOrder] = $trainerType;

            return [$name => $account->trainerTypes()->updateOrCreate(
                ['name' => $name],
                [
                    'icon' => $icon,
                    'color' => $color,
                    'is_default' => $isDefault,
                    'sort_order' => $sortOrder,
                ],
            )];
        })->all();
    }

    /**
     * @return array<int, array{name: string, slug: string, description: string, price_cents: int, sessions_count: int, available_until_time: ?string, sort_order: int, is_trial: bool, class_types: array<int, string>, trainer_types: array<int, string>, rooms: array<int, string>}>
     */
    private function plans(): array
    {
        $groupClassTypes = ['Pole Dance', 'Pole Kids', 'Exot Easy', 'Exot', 'Exot Middle', 'Stretching', 'Tricks', 'Acro class*'];
        $trainerTypes = ['Тренер', 'ТОП-тренер'];

        return [
            ['name' => 'Пробне заняття', 'slug' => 'trial-class', 'description' => 'Пробне заняття для нового клієнта.', 'price_cents' => 25000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 5, 'is_trial' => true, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'START повний день', 'slug' => 'full-day-start', 'description' => 'Повний абонемент на 4 заняття.', 'price_cents' => 150000, 'sessions_count' => 4, 'available_until_time' => null, 'sort_order' => 10, 'is_trial' => false, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'AMATEUR повний день', 'slug' => 'full-day-amateur', 'description' => 'Повний абонемент на 6 занять.', 'price_cents' => 200000, 'sessions_count' => 6, 'available_until_time' => null, 'sort_order' => 20, 'is_trial' => false, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'BASE повний день', 'slug' => 'full-day-base', 'description' => 'Повний абонемент на 8 занять.', 'price_cents' => 250000, 'sessions_count' => 8, 'available_until_time' => null, 'sort_order' => 30, 'is_trial' => false, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'Semi pro повний день', 'slug' => 'full-day-semi-pro', 'description' => 'Повний абонемент на 12 занять.', 'price_cents' => 350000, 'sessions_count' => 12, 'available_until_time' => null, 'sort_order' => 40, 'is_trial' => false, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'Pro повний день', 'slug' => 'full-day-pro', 'description' => 'Повний абонемент на 16 занять.', 'price_cents' => 440000, 'sessions_count' => 16, 'available_until_time' => null, 'sort_order' => 50, 'is_trial' => false, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'START ранок', 'slug' => 'morning-start', 'description' => 'Ранковий абонемент на 4 заняття до 12:00.', 'price_cents' => 140000, 'sessions_count' => 4, 'available_until_time' => '12:00', 'sort_order' => 60, 'is_trial' => false, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'AMATEUR ранок', 'slug' => 'morning-amateur', 'description' => 'Ранковий абонемент на 6 занять до 12:00.', 'price_cents' => 190000, 'sessions_count' => 6, 'available_until_time' => '12:00', 'sort_order' => 70, 'is_trial' => false, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'BASE ранок', 'slug' => 'morning-base', 'description' => 'Ранковий абонемент на 8 занять до 12:00.', 'price_cents' => 240000, 'sessions_count' => 8, 'available_until_time' => '12:00', 'sort_order' => 80, 'is_trial' => false, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'Semi pro ранок', 'slug' => 'morning-semi-pro', 'description' => 'Ранковий абонемент на 12 занять до 12:00.', 'price_cents' => 310000, 'sessions_count' => 12, 'available_until_time' => '12:00', 'sort_order' => 90, 'is_trial' => false, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'Pro ранок', 'slug' => 'morning-pro', 'description' => 'Ранковий абонемент на 16 занять до 12:00.', 'price_cents' => 390000, 'sessions_count' => 16, 'available_until_time' => '12:00', 'sort_order' => 100, 'is_trial' => false, 'class_types' => $groupClassTypes, 'trainer_types' => $trainerTypes, 'rooms' => []],
            ['name' => 'TOP-1', 'slug' => 'private-top-60', 'description' => '1 год. з ТОП-тренером для 1 людини.', 'price_cents' => 110000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 200, 'is_trial' => false, 'class_types' => ['Індивідуальне 60 хв'], 'trainer_types' => ['ТОП-тренер'], 'rooms' => []],
            ['name' => 'TOP-1.5', 'slug' => 'private-top-90', 'description' => '1.5 год. з ТОП-тренером для 1 людини.', 'price_cents' => 160000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 210, 'is_trial' => false, 'class_types' => ['Індивідуальне 90 хв'], 'trainer_types' => ['ТОП-тренер'], 'rooms' => []],
            ['name' => 'STANDART-1', 'slug' => 'private-standard-60', 'description' => '1 год. з тренером для 1 людини.', 'price_cents' => 100000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 220, 'is_trial' => false, 'class_types' => ['Індивідуальне 60 хв'], 'trainer_types' => ['Тренер'], 'rooms' => []],
            ['name' => 'STANDART-1.5', 'slug' => 'private-standard-90', 'description' => '1.5 год. з тренером для 1 людини.', 'price_cents' => 140000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 230, 'is_trial' => false, 'class_types' => ['Індивідуальне 90 хв'], 'trainer_types' => ['Тренер'], 'rooms' => []],
            ['name' => 'Великий зал 1г', 'slug' => 'big-hall-rental-60', 'description' => 'Оренда великого залу на 1 годину.', 'price_cents' => 55000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 300, 'is_trial' => false, 'class_types' => ['Оренда 60 хв'], 'trainer_types' => [], 'rooms' => ['Великий зал']],
            ['name' => 'Великий зал 1.5г', 'slug' => 'big-hall-rental-90', 'description' => 'Оренда великого залу на 1.5 години.', 'price_cents' => 65000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 310, 'is_trial' => false, 'class_types' => ['Оренда 90 хв'], 'trainer_types' => [], 'rooms' => ['Великий зал']],
            ['name' => 'Великий зал 2г', 'slug' => 'big-hall-rental-120', 'description' => 'Оренда великого залу на 2 години.', 'price_cents' => 85000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 320, 'is_trial' => false, 'class_types' => ['Оренда 120 хв'], 'trainer_types' => [], 'rooms' => ['Великий зал']],
            ['name' => 'Малий зал 1г', 'slug' => 'small-hall-rental-60', 'description' => 'Оренда малого залу на 1 годину.', 'price_cents' => 40000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 330, 'is_trial' => false, 'class_types' => ['Оренда 60 хв'], 'trainer_types' => [], 'rooms' => ['Малий зал']],
            ['name' => 'Малий зал 1.5г', 'slug' => 'small-hall-rental-90', 'description' => 'Оренда малого залу на 1.5 години.', 'price_cents' => 60000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 340, 'is_trial' => false, 'class_types' => ['Оренда 90 хв'], 'trainer_types' => [], 'rooms' => ['Малий зал']],
            ['name' => 'Малий зал 2г', 'slug' => 'small-hall-rental-120', 'description' => 'Оренда малого залу на 2 години.', 'price_cents' => 70000, 'sessions_count' => 1, 'available_until_time' => null, 'sort_order' => 350, 'is_trial' => false, 'class_types' => ['Оренда 120 хв'], 'trainer_types' => [], 'rooms' => ['Малий зал']],
        ];
    }
}
