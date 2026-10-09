<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ReassignmentRequestController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\FollowupController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/* ---------- public: login gateways ---------- */
Route::get('/', [LoginController::class, 'gateway'])->middleware('throttle:60,1')->name('gateway');

/* ---------- public: lead-capture form (shareable link, no auth) ---------- */
Route::get('/lead', [LeadController::class, 'publicForm'])->middleware('throttle:60,1')->name('lead.public');
Route::post('/lead', [LeadController::class, 'publicStore'])->middleware('throttle:10,1')->name('lead.store');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'showAdmin'])->middleware('throttle:60,1')->name('admin.login');
    Route::post('/admin/login', [LoginController::class, 'loginAdmin'])->middleware('throttle:10,1');
    Route::get('/staff/login', [LoginController::class, 'showStaff'])->middleware('throttle:60,1')->name('staff.login');
    Route::post('/staff/login', [LoginController::class, 'loginStaff'])->middleware('throttle:10,1');
});

/* ---------- authenticated (admin + customer service) ---------- */
Route::middleware(['auth', 'guard.app'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/my-activity', [DashboardController::class, 'myActivity'])->name('my.activity');
    Route::get('/summary', [SummaryController::class, 'index'])->middleware('perm:summary,view')->name('summary.index');
    Route::get('/summary/pdf', [SummaryController::class, 'pdf'])->middleware('perm:summary,view')->name('summary.pdf');

    // customers
    Route::get('/customers/lookup', [CustomerController::class, 'lookup'])->middleware(['throttle:60,1', 'perm:customers,create'])->name('customers.lookup');
    Route::get('/customers/check-phone', [CustomerController::class, 'checkPhone'])->middleware(['throttle:60,1', 'perm:customers,create'])->name('customers.check-phone');
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('perm:customers,view')->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->middleware('perm:customers,create')->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('perm:customers,create')->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('perm:customers,view')->name('customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->middleware('perm:customers,edit')->name('customers.edit');
    Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])->middleware('perm:customers,edit')->name('customers.update');
    Route::post('/customers/{customer}/assign', [CustomerController::class, 'assign'])->middleware('role:admin')->name('customers.assign');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('perm:customers,delete')->name('customers.destroy');
    Route::post('/customers/{customer}/vehicles', [CustomerController::class, 'storeVehicle'])->middleware('perm:customers,edit')->name('customers.vehicles.store');
    Route::delete('/customers/{customer}/vehicles/{vehicle}', [CustomerController::class, 'destroyVehicle'])->middleware('perm:customers,edit')->name('customers.vehicles.destroy');

    // deals, installments, payments
    Route::get('/customers/{customer}/deals/create', [DealController::class, 'create'])->middleware('perm:deals,create')->name('deals.create');
    Route::post('/customers/{customer}/deals', [DealController::class, 'store'])->middleware('perm:deals,create')->name('deals.store');
    Route::get('/deals/{deal}/edit', [DealController::class, 'edit'])->middleware('perm:deals,edit')->name('deals.edit');
    Route::put('/deals/{deal}', [DealController::class, 'update'])->middleware('perm:deals,edit')->name('deals.update');
    Route::get('/deals/{deal}/schedule', [DealController::class, 'schedule'])->middleware('perm:deals,view')->name('deals.schedule');
    Route::post('/deals/{deal}/payments', [DealController::class, 'addPayment'])->middleware('perm:payments,create')->name('deals.payments.store');
    Route::delete('/deals/{deal}', [DealController::class, 'destroy'])->middleware('perm:deals,delete')->name('deals.destroy');
    Route::delete('/payments/{payment}', [DealController::class, 'deletePayment'])->middleware('perm:payments,delete')->name('payments.destroy');

    // calculator
    Route::get('/calculator', [CalculatorController::class, 'index'])->middleware('perm:calculator,view')->name('calculator');
    Route::post('/calculator/calculate', [CalculatorController::class, 'calculate'])->middleware('perm:calculator,view')->name('calculator.calculate');
    Route::post('/calculator/export', [CalculatorController::class, 'export'])->middleware('perm:calculator,view')->name('calculator.export');

    // follow-ups
    Route::get('/followups', [FollowupController::class, 'index'])->middleware('perm:followups,view')->name('followups.index');
    Route::post('/followups', [FollowupController::class, 'store'])->middleware('perm:followups,create')->name('followups.store');
    Route::put('/followups/{followup}', [FollowupController::class, 'update'])->middleware('perm:followups,edit')->name('followups.update');
    Route::post('/followups/{followup}/complete', [FollowupController::class, 'complete'])->middleware('perm:followups,edit')->name('followups.complete');
    Route::delete('/followups/{followup}', [FollowupController::class, 'destroy'])->middleware('perm:followups,delete')->name('followups.destroy');

    // documents (PDF)
    Route::get('/documents', [DocumentController::class, 'index'])->middleware('perm:documents,view')->name('documents.index');
    Route::get('/documents/new/{type}', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents/new/{type}', [DocumentController::class, 'store'])->middleware('throttle:60,1')->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::get('/documents/{document}/pdf', [DocumentController::class, 'pdf'])->name('documents.pdf');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->middleware('role:admin')->name('documents.destroy');

    // stock (read-only list for staff; management for admin)
    Route::get('/vehicles', [VehicleController::class, 'index'])->middleware('perm:vehicles,view')->name('vehicles.index');
    Route::post('/vehicles', [VehicleController::class, 'store'])->middleware('perm:vehicles,create')->name('vehicles.store');
    Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->middleware('perm:vehicles,edit')->name('vehicles.update');
    Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->middleware('perm:vehicles,delete')->name('vehicles.destroy');

    // reports (view); Excel/PDF exports stay admin-only
    Route::get('/reports', [ReportController::class, 'index'])->middleware('perm:reports,view')->name('reports.index');
    Route::get('/reports/{key}', [ReportController::class, 'show'])->middleware('perm:reports,view')->name('reports.show');

    // products
    Route::get('/products', [ProductController::class, 'index'])->middleware('perm:products,view')->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->middleware('perm:products,create')->name('products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('perm:products,edit')->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('perm:products,delete')->name('products.destroy');

    // leads: convert is reachable by the assigned employee too (authorized in-controller); the inbox itself is admin-only below
    Route::get('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');

    Route::post('/customers/{customer}/reassignment-requests', [ReassignmentRequestController::class, 'store'])->middleware('perm:customers,view')->name('reassignments.store');

    // internal chat — available to every authenticated user, admin or staff
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/start', [ChatController::class, 'start'])->name('chat.start');
    Route::get('/chat/unread-count', [ChatController::class, 'unreadCount'])->name('chat.unread');
    Route::get('/chat/search-customers', [ChatController::class, 'searchCustomers'])->name('chat.search-customers');
    Route::get('/chat/voice/{message}', [ChatController::class, 'voice'])->name('chat.voice');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::get('/chat/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::post('/chat/{conversation}/messages', [ChatController::class, 'send'])->name('chat.send');

    /* ---------- admin only ---------- */
    Route::middleware('role:admin')->group(function () {
        Route::get('/reports/{key}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
        Route::get('/reports/{key}/export', [ReportController::class, 'export'])->name('reports.export');

        Route::get('/data', [DataController::class, 'index'])->name('data.index');
        Route::get('/data/template/{type}', [DataController::class, 'template'])->name('data.template');
        Route::post('/data/upload', [DataController::class, 'upload'])->name('data.upload');
        Route::get('/data/import/{import}', [DataController::class, 'preview'])->name('data.preview');
        Route::post('/data/import/{import}/confirm', [DataController::class, 'confirm'])->name('data.confirm');
        Route::post('/data/import/{import}/cancel', [DataController::class, 'cancel'])->name('data.cancel');
        Route::get('/data/import/{import}/result', [DataController::class, 'result'])->name('data.result');
        Route::get('/data/import/{import}/errors', [DataController::class, 'errors'])->name('data.errors');
        Route::get('/data/export/customers', [DataController::class, 'exportCustomers'])->name('data.export.customers');
        Route::get('/data/export/followups', [DataController::class, 'exportFollowups'])->name('data.export.followups');
        Route::get('/data/export/payments', [DataController::class, 'exportPayments'])->name('data.export.payments');

        Route::resource('users', UserController::class)->except(['show'])->parameters(['users' => 'user']);
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general');
        Route::post('/settings/lookups', [SettingsController::class, 'storeLookup'])->name('settings.lookups.store');
        Route::post('/settings/lookups/{lookup}/toggle', [SettingsController::class, 'toggleLookup'])->name('settings.lookups.toggle');
        Route::delete('/settings/lookups/{lookup}', [SettingsController::class, 'destroyLookup'])->name('settings.lookups.destroy');

        Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'run'])->name('backups.run');
        Route::post('/backups/settings', [BackupController::class, 'settings'])->name('backups.settings');
        Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
        Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');

        Route::get('/vehicles/export', [VehicleController::class, 'export'])->name('vehicles.export');

        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::post('/leads/assign', [LeadController::class, 'assign'])->name('leads.assign');
        Route::post('/leads/{lead}/reject', [LeadController::class, 'reject'])->name('leads.reject');

        Route::get('/reassignment-requests', [ReassignmentRequestController::class, 'index'])->name('reassignments.index');
        Route::post('/reassignment-requests/{reassignment}/approve', [ReassignmentRequestController::class, 'approve'])->name('reassignments.approve');
        Route::post('/reassignment-requests/{reassignment}/reject', [ReassignmentRequestController::class, 'reject'])->name('reassignments.reject');
    });
});
