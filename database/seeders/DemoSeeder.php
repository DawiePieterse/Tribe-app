<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\DatePoll;
use App\Models\Event;
use App\Models\Household;
use App\Models\InboxItem;
use App\Models\Meal;
use App\Models\Member;
use App\Models\Recipe;
use App\Models\TodoList;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
        Event::query()->create(['kind' => Event::ANNIVERSARY, 'title' => 'Oupa en Ouma se troudag', 'starts_on' => $today->subYears(42)->addDays(12), 'repeats' => Event::YEARLY]);
        Event::query()->create(['kind' => Event::SCHOOL, 'title' => 'Skoolvakansie', 'starts_on' => $today->addDays(20), 'ends_on' => $today->addDays(30)]);
        Event::query()->create(['kind' => Event::EVENT, 'title' => 'Dokter', 'starts_on' => $today->addDays(2), 'start_time' => '09:15', 'household_id' => $grandparents->id, 'member_id' => $oupa->id]);

        Event::query()->create(['kind' => Event::EVENT, 'title' => 'Ben se rugby', 'starts_on' => $today->next('Tuesday'), 'start_time' => '15:00', 'end_time' => '16:30', 'repeats' => Event::WEEKLY, 'repeat_until' => $today->addMonths(2), 'household_id' => $north->id]);

        $melktert = Recipe::query()->create([
            'title' => 'Ouma se melktert', 'servings' => '8 porsies', 'time' => '1 uur',
            'ingredients' => "1 pak blaardeeg\n1 l melk\n3 eiers\n200 ml suiker\n60 ml koekmeel\n30 ml mielieblom\n30 g botter\nKaneelsuiker",
            'method' => "Voer ’n tertbord met die deeg uit en bak dit blind.\nKook die melk en botter.\nKlits eiers, suiker, meel en mielieblom en roer dit by die melk tot dik.\nGooi in die kors, laat afkoel en strooi kaneelsuiker oor.",
        ]);
        $bobotie = Recipe::query()->create([
            'title' => 'Bobotie', 'servings' => '6 porsies', 'time' => '1 uur 15 min',
            'ingredients' => "1 kg maalvleis\n2 uie\n2 snye brood\n250 ml melk\n2 eiers\n30 ml kerriepoeier\n60 ml blatjang\nLourierblare",
        ]);
        $monday = $today->startOfWeek(CarbonInterface::MONDAY);
        Meal::query()->create(['household_id' => $grandparents->id, 'date' => $monday->addDays(2), 'recipe_id' => $bobotie->id]);
        Meal::query()->create(['household_id' => $grandparents->id, 'date' => $monday->addDays(4), 'title' => 'Vis en skyfies']);
        Meal::query()->create(['household_id' => $grandparents->id, 'date' => $monday->addDays(6), 'slot' => 'lunch', 'recipe_id' => $melktert->id]);

        $poll = DatePoll::query()->create(['title' => 'Kersfees saam', 'created_by' => $oupa->id]);
        foreach ([24, 25, 26] as $day) {
            $poll->options()->create(['date' => $today->setDate($today->year, 12, $day)]);
        }

        InboxItem::query()->create(['member_id' => $oupa->id, 'text' => 'Ouma nooi almal vir middagete Sondag half een, bring asb ’n slaai', 'suggestion' => [
            'title' => 'Middagete by Ouma', 'kind' => 'gathering', 'starts_on' => $today->next('Sunday')->addWeek()->toDateString(), 'start_time' => '12:30', 'notes' => 'Bring ’n slaai', 'source' => 'simple',
        ]]);

        Contact::query()->create(['name' => 'Ambulans', 'category' => 'emergency', 'phone' => '10177']);
        Contact::query()->create(['name' => 'Dr. Smit', 'category' => 'doctor', 'phone' => '021 555 0101', 'household_id' => $grandparents->id]);
    }
}
