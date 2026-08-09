<?php

use App\Http\Controllers\Accreditation\ApplicationCompletenessController;
use App\Http\Controllers\Accreditation\ApplicationController;
use App\Http\Controllers\Accreditation\ApplicationDocumentController;
use App\Http\Controllers\Accreditation\ApplicationWizardController;
use App\Http\Controllers\Accreditation\ChecklistVersionController;
use App\Http\Controllers\Accreditation\DocumentReviewController;
use App\Http\Controllers\Accreditation\FindingController;
use App\Http\Controllers\Accreditation\InspectionController;
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

            /*
             * Applicant resubmission of a returned-for-revision application.
             */
            Route::post('resubmit', [ApplicationController::class, 'resubmit'])
                ->name('resubmit');
        });
    });

    /*
     * Reviewer completeness review (PSP/CART only).
     *
     * Administrative and documentary check only — never an accreditation
     * decision. Every action re-checks the policy, so a guessed id cannot move
     * another institution's application.
     */
    Route::prefix('reviewer/completeness')->name('reviewer.completeness.')->group(function () {
        Route::get('applications', [ApplicationCompletenessController::class, 'dashboard'])
            ->name('dashboard');
        Route::get('applications/{application}', [ApplicationCompletenessController::class, 'show'])
            ->name('show');
        Route::post('applications/{application}/begin', [ApplicationCompletenessController::class, 'beginReview'])
            ->name('begin');
        Route::post('applications/{application}/return', [ApplicationCompletenessController::class, 'returnForRevision'])
            ->name('return');
        Route::post('applications/{application}/complete', [ApplicationCompletenessController::class, 'markComplete'])
            ->name('complete');
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
     * Inspection scheduling & checklist (PSP/CART create/schedule/review;
     * assigned inspectors conduct; institution users view their own).
     */
    Route::prefix('inspections')->name('inspections.')->group(function () {
        Route::get('/', [InspectionController::class, 'index'])->name('index');
        Route::get('eligible', [InspectionController::class, 'eligible'])->name('eligible');
        Route::get('applications/{application}/create', [InspectionController::class, 'create'])->name('create');
        Route::post('applications/{application}', [InspectionController::class, 'store'])->name('store');

        Route::prefix('{inspection}')->group(function () {
            Route::get('/', [InspectionController::class, 'show'])->name('show');
            Route::post('start', [InspectionController::class, 'start'])->name('start');
            Route::post('submit', [InspectionController::class, 'submit'])->name('submit');
            Route::post('return', [InspectionController::class, 'returnForRevision'])->name('return');
            Route::post('complete', [InspectionController::class, 'complete'])->name('complete');
            Route::post('cancel', [InspectionController::class, 'cancel'])->name('cancel');
            Route::post('reschedule', [InspectionController::class, 'reschedule'])->name('reschedule');
            Route::post('inspectors', [InspectionController::class, 'addInspector'])->name('inspectors.add');
            Route::post('inspectors/lead', [InspectionController::class, 'changeLead'])->name('inspectors.lead');
            Route::post('inspectors/{assignment}', [InspectionController::class, 'removeInspector'])->name('inspectors.remove');
            Route::patch('criteria/{criterion}', [InspectionController::class, 'saveCriterion'])->name('criteria.save');
            Route::post('criteria/{criterion}/evidence', [InspectionController::class, 'linkEvidence'])->name('criteria.evidence.link');
            Route::post('evidence/{evidence}', [InspectionController::class, 'unlinkEvidence'])->name('evidence.unlink');

            // Findings raised from this inspection (nested create form).
            Route::get('findings', [FindingController::class, 'forInspection'])->name('inspections.findings.index');
            Route::get('findings/create', [FindingController::class, 'create'])->name('findings.create');
            Route::get('findings/{criterion}/create', [FindingController::class, 'create'])->name('findings.criterion.create');
        });
    });

    /*
     * Findings & corrective actions (cross-institution reviewers; institution
     * members respond for their own; auditors read-only).
     */
    Route::prefix('findings')->name('findings.')->group(function () {
        Route::get('/', [FindingController::class, 'index'])->name('index');
        Route::post('/', [FindingController::class, 'store'])->name('store');
        Route::get('{finding}', [FindingController::class, 'show'])->name('show');

        Route::post('{finding}/open', [FindingController::class, 'open'])->name('open');
        Route::post('{finding}/acknowledge', [FindingController::class, 'acknowledge'])->name('acknowledge');
        Route::post('{finding}/require-corrective-action', [FindingController::class, 'requireCorrectiveAction'])->name('require-corrective-action');
        Route::post('{finding}/close', [FindingController::class, 'close'])->name('close');
        Route::post('{finding}/reopen', [FindingController::class, 'reopen'])->name('reopen');
        Route::post('{finding}/waive', [FindingController::class, 'waive'])->name('waive');
        Route::post('{finding}/void', [FindingController::class, 'void'])->name('void');

        Route::post('{finding}/actions', [FindingController::class, 'storeAction'])->name('actions.store');
        Route::post('{finding}/actions/{action}/start', [FindingController::class, 'startAction'])->name('actions.start');
        Route::post('{finding}/actions/{action}/submit', [FindingController::class, 'submitAction'])->name('actions.submit');
        Route::post('{finding}/actions/{action}/accept', [FindingController::class, 'acceptAction'])->name('actions.accept');
        Route::post('{finding}/actions/{action}/reject', [FindingController::class, 'rejectAction'])->name('actions.reject');
        Route::post('{finding}/actions/{action}/withdraw', [FindingController::class, 'withdrawAction'])->name('actions.withdraw');
        Route::post('{finding}/actions/{action}/close', [FindingController::class, 'closeAction'])->name('actions.close');
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
