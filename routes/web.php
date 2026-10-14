<?php

use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarConnectionController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DatePollController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\GatheringController;
use App\Http\Controllers\GrandchildController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IcsController;
use App\Http\Controllers\LiftController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\ListItemController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\MealIdeaController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\ShoppingController;
use App\Models\CalendarConnection;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Afrikaans paths, since people will see them in the address bar and in shared links.

// Record ids are numbers, so /kalender/nuut and /kalender/<token>.ics never reach a record route.
foreach (['event', 'item', 'list', 'member', 'milestone', 'contact', 'household', 'recipe', 'meal', 'feed', 'poll', 'option', 'inboxItem', 'connection', 'idea'] as $parameter) {
    Route::pattern($parameter, '[0-9]+');
}

Route::middleware('guest')->group(function (): void {
    Route::get('/aanmeld', [LoginController::class, 'show'])->name('login');
    Route::post('/aanmeld', [LoginController::class, 'sendCode'])->middleware('throttle:login-send')->name('login.send');
    Route::get('/aanmeld/kode', [LoginController::class, 'showCode'])->name('login.code');
    Route::post('/aanmeld/kode', [LoginController::class, 'verify'])->middleware('throttle:login-verify')->name('login.verify');
});

// The calendar feed for the iPhone Calendar app: the secret in the link is the key.
Route::get('/kalender/{token}.ics', IcsController::class)->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:60,1')->name('ics');

// "Stuur na Tribe" from the iPhone share sheet: an Apple Shortcut posts the message with the member's key.
Route::post('/api/stuur', [ShareController::class, 'receive'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:20,1')
    ->name('share.receive');

Route::get('/uitnodiging/{token}', [InviteController::class, 'accept'])->middleware('throttle:login-verify')->name('invite');

Route::middleware('auth')->group(function (): void {
    Route::post('/afmeld', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', HomeController::class)->name('home');
    Route::get('/vandag', [HomeController::class, 'summary'])->middleware('throttle:20,1')->name('home.summary');

    Route::get('/kalender', CalendarController::class)->name('calendar');
    Route::get('/kalender/nuut', [EventController::class, 'create'])->name('events.create');
    Route::post('/kalender', [EventController::class, 'store'])->name('events.store');
    Route::get('/kalender/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/kalender/{event}/wysig', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/kalender/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/kalender/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::post('/kalender/{event}/ry', [LiftController::class, 'store'])->name('lifts.store');
    Route::post('/kalender/{event}/oorslaan', [LiftController::class, 'skip'])->name('events.skip');

    Route::get('/meer', [SettingsController::class, 'more'])->name('more');
    Route::get('/instellings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/instellings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/kennisgewings', [PushController::class, 'store'])->name('push.store');
    Route::delete('/kennisgewings', [PushController::class, 'destroy'])->name('push.destroy');

    // Helpers (a nanny) see only the calendar and their own settings; the rest is the family's.
    Route::middleware('family')->group(function (): void {
        Route::post('/kalender/{event}/antwoord', [GatheringController::class, 'respond'])->name('gatherings.respond');
        Route::post('/kalender/{event}/bring', [GatheringController::class, 'addItem'])->name('gatherings.items.store');
        Route::post('/bring/{item}/ons', [GatheringController::class, 'claimItem'])->name('gatherings.items.claim');
        Route::delete('/bring/{item}', [GatheringController::class, 'removeItem'])->name('gatherings.items.destroy');

        Route::get('/datums', [DatePollController::class, 'index'])->name('polls.index');
        Route::get('/datums/nuut', [DatePollController::class, 'create'])->name('polls.create');
        Route::post('/datums', [DatePollController::class, 'store'])->name('polls.store');
        Route::get('/datums/{poll}', [DatePollController::class, 'show'])->name('polls.show');
        Route::post('/datums/{poll}/keuses', [DatePollController::class, 'vote'])->name('polls.vote');
        Route::post('/datums/opsie/{option}/kies', [DatePollController::class, 'choose'])->name('polls.choose');
        Route::delete('/datums/{poll}', [DatePollController::class, 'destroy'])->name('polls.destroy');

        Route::get('/lyste', [ListController::class, 'index'])->name('lists.index');
        Route::post('/lyste', [ListController::class, 'store'])->name('lists.store');
        Route::get('/lyste/{list}', [ListController::class, 'show'])->name('lists.show');
        Route::put('/lyste/{list}', [ListController::class, 'update'])->name('lists.update');
        Route::delete('/lyste/{list}', [ListController::class, 'destroy'])->name('lists.destroy');
        Route::post('/lyste/{list}/opruim', [ListController::class, 'clear'])->name('lists.clear');
        Route::post('/lyste/{list}/items', [ListItemController::class, 'store'])->name('items.store');
        Route::post('/items/{item}/afmerk', [ListItemController::class, 'toggle'])->name('items.toggle');
        Route::delete('/items/{item}', [ListItemController::class, 'destroy'])->name('items.destroy');

        Route::get('/resepte', [RecipeController::class, 'index'])->name('recipes.index');
        Route::get('/resepte/nuut', [RecipeController::class, 'create'])->name('recipes.create');
        Route::post('/resepte/invoer', [RecipeController::class, 'import'])->middleware('throttle:10,1')->name('recipes.import');
        Route::post('/resepte', [RecipeController::class, 'store'])->name('recipes.store');
        Route::get('/resepte/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
        Route::get('/resepte/{recipe}/wysig', [RecipeController::class, 'edit'])->name('recipes.edit');
        Route::put('/resepte/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
        Route::delete('/resepte/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
        Route::post('/resepte/{recipe}/gunsteling', [RecipeController::class, 'favourite'])->name('recipes.favourite');

        Route::get('/weekmenu', [MealController::class, 'week'])->name('meals.week');
        Route::post('/weekmenu', [MealController::class, 'store'])->name('meals.store');
        Route::delete('/weekmenu/{meal}', [MealController::class, 'destroy'])->name('meals.destroy');
        Route::get('/weekmenu/inkopies', [MealController::class, 'shopping'])->name('meals.shopping');

        Route::get('/kosidees', [MealIdeaController::class, 'index'])->name('ideas.index');
        Route::post('/kosidees', [MealIdeaController::class, 'suggest'])->middleware('throttle:20,1')->name('ideas.suggest');
        Route::post('/kosidees/{idea}/resep', [MealIdeaController::class, 'saveRecipe'])->name('ideas.recipe');
        Route::post('/kosidees/{idea}/weekmenu', [MealIdeaController::class, 'addToWeek'])->name('ideas.week');
        Route::post('/kosidees/gekoop', [MealIdeaController::class, 'readBought'])->middleware('throttle:10,1')->name('ideas.bought');
        Route::delete('/kosidees/gekoop', [MealIdeaController::class, 'forgetBought'])->name('ideas.forget');

        Route::post('/inkopies', [ShoppingController::class, 'add'])->name('shopping.add');

        Route::get('/kleinkinders', [GrandchildController::class, 'index'])->name('grandchildren.index');
        Route::get('/kleinkinders/{member}', [GrandchildController::class, 'show'])->name('grandchildren.show');
        Route::get('/kleinkinders/{member}/wysig', [GrandchildController::class, 'edit'])->name('grandchildren.edit');
        Route::put('/kleinkinders/{member}', [GrandchildController::class, 'update'])->name('grandchildren.update');
        Route::post('/kleinkinders/{member}/mylpale', [GrandchildController::class, 'addMilestone'])->name('milestones.store');
        Route::delete('/mylpale/{milestone}', [GrandchildController::class, 'removeMilestone'])->name('milestones.destroy');

        Route::get('/kontakte', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('/kontakte/nuut', [ContactController::class, 'create'])->name('contacts.create');
        Route::post('/kontakte', [ContactController::class, 'store'])->name('contacts.store');
        Route::get('/kontakte/{contact}/wysig', [ContactController::class, 'edit'])->name('contacts.edit');
        Route::put('/kontakte/{contact}', [ContactController::class, 'update'])->name('contacts.update');
        Route::delete('/kontakte/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

        Route::get('/kalenders', [CalendarFeedController::class, 'index'])->name('feeds.index');
        Route::post('/kalenders', [CalendarFeedController::class, 'store'])->middleware('throttle:10,1')->name('feeds.store');
        Route::post('/kalenders/nuwe-skakel', [CalendarFeedController::class, 'newLink'])->name('feeds.new-link');
        Route::post('/kalenders/{feed}/sinkroniseer', [CalendarFeedController::class, 'sync'])->middleware('throttle:10,1')->name('feeds.sync');
        Route::delete('/kalenders/{feed}', [CalendarFeedController::class, 'destroy'])->name('feeds.destroy');

        Route::get('/kalenders/koppel/icloud', [CalendarConnectionController::class, 'icloud'])->name('connections.icloud');
        Route::post('/kalenders/koppel/icloud', [CalendarConnectionController::class, 'storeICloud'])->middleware('throttle:10,1')->name('connections.icloud.store');
        Route::get('/kalenders/koppel/{provider}', [CalendarConnectionController::class, 'connect'])->whereIn('provider', CalendarConnection::OAUTH)->name('connections.connect');
        Route::get('/kalenders/koppel/{provider}/terug', [CalendarConnectionController::class, 'callback'])->whereIn('provider', CalendarConnection::OAUTH)->name('connections.callback');
        Route::get('/kalenders/koppelings/{connection}', [CalendarConnectionController::class, 'edit'])->name('connections.edit');
        Route::put('/kalenders/koppelings/{connection}', [CalendarConnectionController::class, 'update'])->name('connections.update');
        Route::post('/kalenders/koppelings/{connection}/sinkroniseer', [CalendarConnectionController::class, 'sync'])->middleware('throttle:10,1')->name('connections.sync');
        Route::delete('/kalenders/koppelings/{connection}', [CalendarConnectionController::class, 'destroy'])->name('connections.destroy');

        Route::get('/stuur', [ShareController::class, 'paste'])->name('share.paste');
        Route::post('/stuur', [ShareController::class, 'read'])->middleware('throttle:20,1')->name('share.read');
        Route::get('/stuur/opstel', [ShareController::class, 'setup'])->name('share.setup');
        Route::post('/stuur/sleutel', [ShareController::class, 'newKey'])->name('share.key');
        Route::get('/inkassie/{inboxItem}', [ShareController::class, 'open'])->name('inbox.open');
        Route::delete('/inkassie/{inboxItem}', [ShareController::class, 'discard'])->name('inbox.discard');

        Route::get('/familie', [FamilyController::class, 'index'])->name('family.index');
        Route::post('/familie/huise', [FamilyController::class, 'storeHousehold'])->name('family.households.store');
        Route::put('/familie/huise/{household}', [FamilyController::class, 'updateHousehold'])->name('family.households.update');
        Route::get('/familie/lede/nuut', [FamilyController::class, 'createMember'])->name('family.members.create');
        Route::post('/familie/lede', [FamilyController::class, 'storeMember'])->name('family.members.store');
        Route::get('/familie/lede/{member}/wysig', [FamilyController::class, 'editMember'])->name('family.members.edit');
        Route::put('/familie/lede/{member}', [FamilyController::class, 'updateMember'])->name('family.members.update');
        Route::delete('/familie/lede/{member}', [FamilyController::class, 'destroyMember'])->name('family.members.destroy');
        Route::post('/familie/lede/{member}/uitnodiging', [FamilyController::class, 'invite'])->name('family.members.invite');
    });
});
