<?php

use App\Http\Controllers\Accreditation\ApplicationController;
use App\Http\Controllers\Accreditation\ApplicationWizardController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /*
     * Accreditation application wizard.
     *
     * Route model binding resolves the application; every action re-checks
     * authorization server-side so a guessed URL id cannot leak data.
     */
    Route::prefix('applications')->name('applications.')->group(function () {
        Route::get('/', [ApplicationController::class, 'index'])->name('index');
        Route::get('create', [ApplicationController::class, 'create'])->name('create');
        Route::post('/', [ApplicationController::class, 'store'])->name('store');

        Route::prefix('{application}')->group(function () {
            Route::get('wizard', [ApplicationWizardController::class, 'edit'])
                ->name('wizard.edit');
            Route::post('wizard/step', [ApplicationWizardController::class, 'saveStep'])
                ->name('wizard.step');
            Route::post('wizard/navigate', [ApplicationWizardController::class, 'navigate'])
                ->name('wizard.navigate');
            Route::post('wizard/checklist', [ApplicationWizardController::class, 'saveChecklistResponse'])
                ->name('wizard.checklist');
            Route::post('wizard/declaration', [ApplicationWizardController::class, 'acceptDeclaration'])
                ->name('wizard.declaration');
            Route::post('submit', [ApplicationWizardController::class, 'submit'])
                ->name('submit');
            Route::post('withdraw', [ApplicationController::class, 'withdraw'])
                ->name('withdraw');
            Route::get('confirmation', [ApplicationWizardController::class, 'confirmation'])
                ->name('confirmation');
        });
    });
});

require __DIR__.'/settings.php';
