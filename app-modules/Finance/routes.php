<?php

use AppModules\Finance\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

// Finance -> Invoices (the same route names as before the split, so menus and links keep working)
Route::middleware(['web', 'auth'])->prefix('account')->group(function () {
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::post('invoices/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::get('invoices/settings', [InvoiceController::class, 'settingsForm'])->name('invoices.settings');
    Route::post('invoices/settings', [InvoiceController::class, 'saveSettings'])->name('invoices.settings.save');
    Route::get('invoices/client/{client}', [InvoiceController::class, 'client'])->whereNumber('client')->name('invoices.client');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
    Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->whereNumber('invoice')->name('invoices.edit');
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->whereNumber('invoice')->name('invoices.update');
    Route::get('invoices/{invoice}/sheet', [InvoiceController::class, 'sheet'])->whereNumber('invoice')->name('invoices.sheet');
    Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->whereNumber('invoice')->name('invoices.download');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->whereNumber('invoice')->name('invoices.cancel');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->whereNumber('invoice')->name('invoices.destroy');
});
