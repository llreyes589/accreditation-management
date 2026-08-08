<?php

use App\Http\Controllers\Accreditation\ApplicationController;
use App\Http\Controllers\Accreditation\ApplicationDocumentController;
use App\Http\Controllers\Accreditation\ApplicationWizardController;
use App\Http\Controllers\Accreditation\ChecklistVersionController;
use App\Http\Controllers\Accreditation\DocumentReviewController;
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

            /*
             * Applicant supporting documents (private storage, policy-gated).
             */
            Route::prefix('documents')->name('documents.')->group(function () {
                Route::get('/', [ApplicationDocumentController::class, 'index'])->name('index');
                Route::post('/', [ApplicationDocumentController::class, 'store'])->name('store');
                Route::get('{document}', [ApplicationDocumentController::class, 'show'])->name('show');
                Route::post('{document}/replace', [ApplicationDocumentController::class, 'replace'])->name('replace');
                Route::post('{document}/withdraw', [ApplicationDocumentController::class, 'withdraw'])->name('withdraw');
                Route::get('{document}/download', [ApplicationDocumentController::class, 'download'])->name('download');
                Route::get('{document}/preview', [ApplicationDocumentController::class, 'preview'])->name('preview');
            });
        });
    });

    /*
     * Reviewer document review (PSP/CART only).
     *
     * Reviewers operate across institutions but can never change applicant
     * state; every view/download/review re-checks the policy.
     */
    Route::prefix('reviewer/applications/{application}/documents')->name('reviewer.applications.documents.')->group(function () {
        Route::get('/', [DocumentReviewController::class, 'index'])->name('index');
    });

    Route::prefix('reviewer/documents')->name('reviewer.documents.')->group(function () {
        Route::get('{document}', [DocumentReviewController::class, 'show'])->name('show');
        Route::post('{document}/start-review', [DocumentReviewController::class, 'startReview'])->name('start-review');
        Route::post('{document}/review', [DocumentReviewController::class, 'review'])->name('review');
        Route::get('{document}/download', [DocumentReviewController::class, 'download'])->name('download');
        Route::get('{document}/preview', [DocumentReviewController::class, 'preview'])->name('preview');
    });

    /*
     * Configurable checklist administration (PSP/CART only).
     *
     * Every route re-checks authorization in the policy/request, so a guessed
     * id cannot leak or mutate a checklist version. PUBLISHED and RETIRED
     * versions are read-only.
     */
    Route::prefix('admin/checklists')->name('admin.checklists.')->group(function () {
        Route::get('/', [ChecklistVersionController::class, 'index'])->name('index');
        Route::get('create', [ChecklistVersionController::class, 'create'])->name('create');
        Route::post('/', [ChecklistVersionController::class, 'store'])->name('store');

        Route::get('{checklist_version}/preview', [ChecklistVersionController::class, 'preview'])
            ->name('preview');
        Route::get('{checklist_version}/publish', [ChecklistVersionController::class, 'publishConfirm'])
            ->name('publish.confirm');
        Route::post('{checklist_version}/publish', [ChecklistVersionController::class, 'publish'])
            ->name('publish');
        Route::get('{checklist_version}/retire', [ChecklistVersionController::class, 'retireConfirm'])
            ->name('retire.confirm');
        Route::post('{checklist_version}/retire', [ChecklistVersionController::class, 'retire'])
            ->name('retire');
        Route::post('{checklist_version}/clone', [ChecklistVersionController::class, 'clone'])
            ->name('clone');
        Route::delete('{checklist_version}', [ChecklistVersionController::class, 'destroy'])
            ->name('destroy');

        Route::post('{checklist_version}/sections', [ChecklistVersionController::class, 'storeSection'])
            ->name('sections.store');
        Route::post('{checklist_version}/sections/reorder', [ChecklistVersionController::class, 'reorderSections'])
            ->name('sections.reorder');
        Route::patch('{checklist_version}/sections/{section}', [ChecklistVersionController::class, 'updateSection'])
            ->name('sections.update');
        Route::post('{checklist_version}/sections/{section}/toggle', [ChecklistVersionController::class, 'deactivateSection'])
            ->name('sections.toggle');
        Route::delete('{checklist_version}/sections/{section}', [ChecklistVersionController::class, 'destroySection'])
            ->name('sections.destroy');

        Route::post('{checklist_version}/requirements', [ChecklistVersionController::class, 'storeRequirement'])
            ->name('requirements.store');
        Route::patch('{checklist_version}/requirements/{requirement}', [ChecklistVersionController::class, 'updateRequirement'])
            ->name('requirements.update');
        Route::post('{checklist_version}/sections/{section}/requirements/reorder', [ChecklistVersionController::class, 'reorderRequirements'])
            ->name('requirements.reorder');
        Route::post('{checklist_version}/requirements/{requirement}/toggle', [ChecklistVersionController::class, 'deactivateRequirement'])
            ->name('requirements.toggle');

        Route::get('{checklist_version}', [ChecklistVersionController::class, 'show'])->name('show');
        Route::get('{checklist_version}/edit', [ChecklistVersionController::class, 'edit'])->name('edit');
        Route::patch('{checklist_version}', [ChecklistVersionController::class, 'update'])->name('update');
    });
});

require __DIR__.'/settings.php';
