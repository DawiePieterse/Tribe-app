<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Event;
use App\Models\Household;
use App\Models\Member;
use App\Models\TodoList;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Made-up family for trying Tribe out locally (TRIBE_DEMO=true). Not real people.
 * Logins: oupa@example.com, ma@example.com, pa@example.com (use tribe:invite for a link).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = CarbonImmutable::today();

        $grandparents = Household::query()->create(['name' => 'Oupa en Ouma', 'colour' => Household::COLOURS[0]]);
        $north = Household::query()->create(['name' => 'Die Noordkant', 'colour' => Household::COLOURS[1]]);
        $south = Household::query()->create(['name' => 'Die Suidkant', 'colour' => Household::COLOURS[2]]);

        foreach ([$grandparents, $north, $south] as $household) {
            TodoList::query()->create(['household_id' => $household->id, 'name' => 'Inkopies', 'kind' => TodoList::SHOPPING]);
        }

        $oupa = Member::query()->create(['household_id' => $grandparents->id, 'name' => 'Oupa Koos', 'email' => 'oupa@example.com', 'is_admin' => true, 'birthday' => $today->subYears(68)->addDays(3)]);
        Member::query()->create(['household_id' => $grandparents->id, 'name' => 'Ouma Rina', 'email' => 'ouma@example.com', 'birthday' => $today->subYears(66)->addMonths(2)]);
        Member::query()->create(['household_id' => $north->id, 'name' => 'Ma Elna', 'email' => 'ma@example.com', 'birthday' => $today->subYears(38)->addMonths(5)]);
        Member::query()->create(['household_id' => $north->id, 'name' => 'Pa Jaco', 'email' => 'pa@example.com', 'birthday' => $today->subYears(40)->addMonths(7)]);
        Member::query()->create(['household_id' => $south->id, 'name' => 'Ma Lize', 'email' => 'lize@example.com', 'is_admin' => true, 'birthday' => $today->subYears(33)->addMonths(1)]);
        Member::query()->create(['household_id' => $south->id, 'name' => 'Pa Stefan', 'email' => 'stefan@example.com', 'birthday' => $today->subYears(34)->addMonths(9)]);

        foreach ([['Anri', 12, $north], ['Ben', 9, $north], ['Mila', 7, $north], ['Ruan', 3, $south], ['Jan', 1, $south]] as $i => [$name, $age, $house]) {
            Member::query()->create([
                'household_id' => $house->id, 'name' => $name, 'kind' => Member::CHILD,
                'birthday' => $today->subYears($age)->addDays(5 + $i * 23),
                'clothes_size' => $age > 2 ? ($age + 1).' jaar' : '12–18 maande',
                'allergies' => $name === 'Ben' ? 'Grondbone' : null,
            ]);
        }

        Event::query()->create(['kind' => Event::GATHERING, 'title' => 'Sondagete', 'starts_on' => $today->next('Sunday'), 'start_time' => '12:30', 'host_household_id' => $grandparents->id, 'created_by' => $oupa->id]);
        Event::query()->create(['kind' => Event::ANNIVERSARY, 'title' => 'Oupa en Ouma se troudag', 'starts_on' => $today->subYears(42)->addDays(12), 'yearly' => true]);
        Event::query()->create(['kind' => Event::SCHOOL, 'title' => 'Skoolvakansie', 'starts_on' => $today->addDays(20), 'ends_on' => $today->addDays(30)]);
        Event::query()->create(['kind' => Event::EVENT, 'title' => 'Dokter', 'starts_on' => $today->addDays(2), 'start_time' => '09:15', 'household_id' => $grandparents->id, 'member_id' => $oupa->id]);

        Contact::query()->create(['name' => 'Ambulans', 'category' => 'emergency', 'phone' => '10177']);
        Contact::query()->create(['name' => 'Dr. Smit', 'category' => 'doctor', 'phone' => '021 555 0101', 'household_id' => $grandparents->id]);
    }
}
