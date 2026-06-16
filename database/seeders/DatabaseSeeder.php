<?php

namespace Database\Seeders;

use App\Enums\AccountRole;
use App\Enums\ScheduleKind;
use App\Enums\ScheduleSeriesStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SystemRole;
use App\Actions\GenerateScheduleOccurrences;
use App\Models\ActivityDirection;
use App\Models\Account;
use App\Models\ClassType;
use App\Models\Customer;
use App\Models\Instructor;
use App\Models\Location;
use App\Models\Room;
use App\Models\ScheduleSeries;
use App\Models\ScheduledClass;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $platformUser = $this->user('Адміністратор платформи', 'platform@example.com', SystemRole::PlatformAdmin);
        $nastyaUser = $this->user('Власниця Настя', 'nastya@example.com');
        $oxanaUser = $this->user('Власниця Оксана', 'oxana@example.com');

        $nastyaAccount = $this->account('Студія Насті', 'studio-nastya', 'uk', 'UAH', '#e11d48');
        $oxanaAccount = $this->account('Студія Оксани', 'studio-oxana', 'uk', 'EUR', '#0f766e');
        $studioPlan = $this->subscriptionPlan();
        $nastyaAccount->subscription()->updateOrCreate(
            ['account_id' => $nastyaAccount->id],
            ['subscription_plan_id' => $studioPlan->id, 'status' => SubscriptionStatus::Active->value, 'started_at' => now()],
        );
        $oxanaAccount->subscription()->updateOrCreate(
            ['account_id' => $oxanaAccount->id],
            ['subscription_plan_id' => $studioPlan->id, 'status' => SubscriptionStatus::Trialing->value, 'started_at' => now()],
        );

        $nastyaAccount->users()->syncWithoutDetaching([
            $nastyaUser->id => ['role' => AccountRole::Owner->value],
        ]);
        $oxanaAccount->users()->syncWithoutDetaching([
            $oxanaUser->id => ['role' => AccountRole::Owner->value],
        ]);

        $nastyaLocationOne = $this->location($nastyaAccount, 'Локація 1', 'location-1', 'Київ, Поділ');
        $nastyaLocationTwo = $this->location($nastyaAccount, 'Локація 2', 'location-2', 'Київ, Печерськ');
        $oxanaLocation = $this->location($oxanaAccount, 'Головна студія', 'main-studio', 'Львів, центр');

        $this->seedStudioSchedule($nastyaAccount, [$nastyaLocationOne, $nastyaLocationTwo], ['Настя', 'Іра', 'Таня', 'Катя']);
        $this->seedStudioSchedule($oxanaAccount, [$oxanaLocation], ['Оксана']);

        $customer = Customer::updateOrCreate(
            ['email' => 'client@example.com'],
            [
                'name' => 'Демо клієнт',
                'phone' => '+380501112233',
                'password' => Hash::make('password'),
                'default_language' => 'uk',
            ],
        );

        $customer->accounts()->syncWithoutDetaching([$nastyaAccount->id, $oxanaAccount->id]);
    }

    private function user(string $name, string $email, ?SystemRole $systemRole = null): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'system_role' => $systemRole?->value,
                'email_verified_at' => now(),
            ],
        );
    }

    private function account(string $name, string $slug, string $language, string $currency, string $brandColor): Account
    {
        return Account::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'status' => 'active',
                'default_language' => $language,
                'default_currency' => $currency,
                'brand_color' => $brandColor,
                'timezone' => 'Europe/Kyiv',
            ],
        );
    }

    private function subscriptionPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::updateOrCreate(
            ['slug' => 'studio'],
            [
                'name' => 'Студія',
                'description' => 'Демо SaaS-тариф для студійних workspace.',
                'price_cents' => 4900,
                'currency' => 'UAH',
                'billing_interval' => 'monthly',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }

    private function location(Account $account, string $name, string $slug, string $address): Location
    {
        return Location::updateOrCreate(
            [
                'account_id' => $account->id,
                'slug' => $slug,
            ],
            [
                'name' => $name,
                'address' => $address,
                'phone' => '+380441234567',
                'email' => $slug.'@example.com',
                'timezone' => 'Europe/Kyiv',
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<int, Location>  $locations
     * @param  array<int, string>  $instructorNames
     */
    private function seedStudioSchedule(Account $account, array $locations, array $instructorNames): void
    {
        $account->scheduledClasses()->delete();
        $account->scheduleSeries()->delete();
        $account->classTypes()->delete();
        $account->activityDirections()->delete();
        $account->instructors()->delete();
        $account->rooms()->delete();

        $directions = collect([
            ['Екзотик пол-денс', 'Флоу, музикальність і техніка exotic pole.', '#e11d48'],
            ['Стретчинг', 'Мобільність і робота над гнучкістю.', '#0f766e'],
            ['Pole Fitness', 'Сила і базова pole-техніка.', '#2563eb'],
        ])->mapWithKeys(function (array $direction) use ($account): array {
            [$name, $description, $color] = $direction;

            $model = ActivityDirection::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                    'description' => $description,
                    'color' => $color,
                    'is_active' => true,
                ],
            );

            return [$name => $model];
        });

        $classTypes = collect([
            ['Pole для початківців', 'Вступне заняття з pole dance.', '#e11d48', 'Pole Fitness', ScheduleKind::GroupClass, 60, 120, 12],
            ['Pole хореографія', 'Заняття з фокусом на хореографію.', '#f97316', 'Pole Fitness', ScheduleKind::GroupClass, 60, 120, 12],
            ['Стретчинг', 'Заняття для мобільності та гнучкості.', '#0f766e', 'Стретчинг', ScheduleKind::GroupClass, 60, 60, 14],
            ['Екзотик Flow', 'Практика флоу та музикальності.', '#7c3aed', 'Екзотик пол-денс', ScheduleKind::GroupClass, 60, 180, 10],
            ['Індивідуальне заняття', 'Плейсхолдер для індивідуального тренування.', '#525252', 'Pole Fitness', ScheduleKind::PrivateLesson, 60, 240, 1],
            ['Оренда залу', 'Плейсхолдер для оренди залу.', '#737373', 'Pole Fitness', ScheduleKind::RoomRental, 60, null, null],
        ])->mapWithKeys(function (array $classType) use ($account, $directions): array {
            [$name, $description, $color, $directionName, $scheduleKind, $duration, $cutoff, $capacity] = $classType;

            $model = ClassType::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'activity_direction_id' => $directions[$directionName]->id,
                    'name' => $name,
                    'description' => $description,
                    'color' => $color,
                    'schedule_kind' => $scheduleKind->value,
                    'default_duration_minutes' => $duration,
                    'booking_cutoff_minutes' => $cutoff,
                    'default_capacity' => $capacity,
                    'is_active' => true,
                ],
            );

            return [$name => $model];
        });

        $instructors = collect($instructorNames)->mapWithKeys(function (string $name) use ($account): array {
            $model = Instructor::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                    'email' => Str::slug($name).'@example.com',
                    'phone' => '+380671234567',
                    'bio' => 'Демо профіль інструктора.',
                    'is_active' => true,
                ],
            );

            return [$name => $model];
        });

        $rooms = collect($locations)->flatMap(function (Location $location, int $locationIndex) use ($account): array {
            $roomNames = $locationIndex === 0
                ? [['Великий зал', 'big-hall', 12], ['Малий зал', 'small-hall', 6]]
                : [['Головний зал', 'main-hall', 10]];

            return collect($roomNames)->map(function (array $room) use ($account, $location): Room {
                [$name, $slug, $capacity] = $room;

                return Room::updateOrCreate(
                    [
                        'location_id' => $location->id,
                        'slug' => $slug,
                    ],
                    [
                        'account_id' => $account->id,
                        'name' => $name,
                        'description' => 'Демо зал для розділення розкладу.',
                        'capacity' => $capacity,
                        'is_active' => true,
                    ],
                );
            })->all();
        })->values();

        $seriesRows = [
            ['Pole для початківців', 2, '14:00', 0, 0],
            ['Стретчинг', 2, '11:00', 0, 1],
            ['Екзотик Flow', 4, '18:00', 0, 0],
            ['Pole хореографія', 6, '12:00', min(1, count($locations) - 1), 0],
        ];

        $generator = app(GenerateScheduleOccurrences::class);
        $baseStartDate = Carbon::now()->startOfDay();

        foreach ($seriesRows as $index => $row) {
            [$title, $weekday, $startTime, $locationIndex, $roomOffset] = $row;
            $location = $locations[$locationIndex];
            $room = $rooms->where('location_id', $location->id)->values()[$roomOffset] ?? $rooms->where('location_id', $location->id)->first();

            $series = ScheduleSeries::create([
                'account_id' => $account->id,
                'location_id' => $location->id,
                'room_id' => $room->id,
                'class_type_id' => $classTypes[$title]->id,
                'instructor_id' => $instructors->values()[$index % $instructors->count()]->id,
                'title' => null,
                'description' => null,
                'weekday' => $weekday,
                'start_time' => $startTime,
                'start_date' => $baseStartDate->toDateString(),
                'end_date' => null,
                'capacity' => null,
                'duration_minutes' => $title === 'Екзотик Flow' ? 90 : null,
                'booking_cutoff_minutes' => null,
                'status' => ScheduleSeriesStatus::Active->value,
            ]);

            $generator->execute($series);
        }

        foreach (['Індивідуальне заняття', 'Оренда залу'] as $index => $title) {
            $startsAt = Carbon::now()->startOfDay()->addDays($index + 1)->setTime(9 + $index, 0);
            $room = $rooms->first();
            ScheduledClass::create([
                    'account_id' => $account->id,
                    'location_id' => $room->location_id,
                    'room_id' => $room->id,
                    'class_type_id' => $classTypes[$title]->id,
                    'instructor_id' => null,
                    'title' => $title,
                    'description' => $classTypes[$title]->description,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addMinutes($classTypes[$title]->default_duration_minutes),
                    'capacity' => $classTypes[$title]->default_capacity,
                    'booking_cutoff_minutes' => $classTypes[$title]->booking_cutoff_minutes,
                    'is_generated' => false,
                    'is_public' => false,
                    'status' => 'scheduled',
            ]);
        }
    }
}
