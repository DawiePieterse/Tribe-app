<?php

use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\GatheringController;
use App\Http\Controllers\GrandchildController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\ListItemController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// Afrikaans paths, since people will see them in the address bar and in shared links.

Route::middleware('guest')->group(function (): void {
    Route::get('/aanmeld', [LoginController::class, 'show'])->name('login');
    Route::post('/aanmeld', [LoginController::class, 'sendCode'])->middleware('throttle:login-send')->name('login.send');
    Route::get('/aanmeld/kode', [LoginController::class, 'showCode'])->name('login.code');
    Route::post('/aanmeld/kode', [LoginController::class, 'verify'])->middleware('throttle:login-verify')->name('login.verify');
});

Route::get('/uitnodiging/{token}', [InviteController::class, 'accept'])->middleware('throttle:login-verify')->name('invite');

Route::middleware('auth')->group(function (): void {
    Route::post('/afmeld', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', HomeController::class)->name('home');

    Route::get('/kalender', CalendarController::class)->name('calendar');
    Route::get('/kalender/nuut', [EventController::class, 'create'])->name('events.create');
    Route::post('/kalender', [EventController::class, 'store'])->name('events.store');
    Route::get('/kalender/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/kalender/{event}/wysig', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/kalender/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/kalender/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::post('/kalender/{event}/antwoord', [GatheringController::class, 'respond'])->name('gatherings.respond');
    Route::post('/kalender/{event}/bring', [GatheringController::class, 'addItem'])->name('gatherings.items.store');
    Route::post('/bring/{item}/ons', [GatheringController::class, 'claimItem'])->name('gatherings.items.claim');
    Route::delete('/bring/{item}', [GatheringController::class, 'removeItem'])->name('gatherings.items.destroy');

    Route::get('/lyste', [ListController::class, 'index'])->name('lists.index');
    Route::post('/lyste', [ListController::class, 'store'])->name('lists.store');
    Route::get('/lyste/{list}', [ListController::class, 'show'])->name('lists.show');
    Route::put('/lyste/{list}', [ListController::class, 'update'])->name('lists.update');
    Route::delete('/lyste/{list}', [ListController::class, 'destroy'])->name('lists.destroy');
    Route::post('/lyste/{list}/opruim', [ListController::class, 'clear'])->name('lists.clear');
    Route::post('/lyste/{list}/items', [ListItemController::class, 'store'])->name('items.store');
    Route::post('/items/{item}/afmerk', [ListItemController::class, 'toggle'])->name('items.toggle');
    Route::delete('/items/{item}', [ListItemController::class, 'destroy'])->name('items.destroy');

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

    Route::get('/meer', [SettingsController::class, 'more'])->name('more');
    Route::get('/instellings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/instellings', [SettingsController::class, 'update'])->name('settings.update');

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
