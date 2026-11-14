<?php

use Illuminate\Support\Facades\Route;
use AppModules\Finance\Http\Controllers\BankAccountController;
use AppModules\Finance\Http\Controllers\CreditNoteController;
use AppModules\Finance\Http\Controllers\EstimateController;
use AppModules\Finance\Http\Controllers\EstimateRequestController;
use AppModules\Finance\Http\Controllers\EstimateTemplateController;
use AppModules\Finance\Http\Controllers\ExpenseCategoryController;
use AppModules\Finance\Http\Controllers\ExpenseController;
use AppModules\Finance\Http\Controllers\InvoiceController;
use AppModules\Finance\Http\Controllers\InvoiceFilesController;
use AppModules\Finance\Http\Controllers\OrderController;
use AppModules\Finance\Http\Controllers\PaymentController;
use AppModules\Finance\Http\Controllers\ProductCategoryController;
use AppModules\Finance\Http\Controllers\ProductController;
use AppModules\Finance\Http\Controllers\ProductFileController;
use AppModules\Finance\Http\Controllers\ProductSubCategoryController;
use AppModules\Finance\Http\Controllers\RecurringExpenseController;
use AppModules\Finance\Http\Controllers\RecurringInvoiceController;
use AppModules\Finance\Http\Controllers\SubscriptionController;

// Same URLs and route names as before the split.
Route::middleware('web')->group(function () {
Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('products/apply-quick-action', [ProductController::class, 'applyQuickAction'])->name('products.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('products/remove-cart-item/{id}', [ProductController::class, 'removeCartItem'])->name('products.remove_cart_item');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('products/options', [ProductController::class, 'allProductOption'])->name('products.options');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('products/add-cart-item', [ProductController::class, 'addCartItem'])->name('products.add_cart_item');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('products/cart', [ProductController::class, 'cart'])->name('products.cart');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('products/empty-cart', [ProductController::class, 'emptyCart'])->name('products.empty_cart');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(
        ['prefix' => 'products'],
        function () {
            Route::get('import', [ProductController::class, 'importProduct'])->name('products.import');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(
        ['prefix' => 'products'],
        function () {
            Route::post('import', [ProductController::class, 'importStore'])->name('products.import.store');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(
        ['prefix' => 'products'],
        function () {
            Route::post('import/process', [ProductController::class, 'importProcess'])->name('products.import.process');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('products', ProductController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('productCategory', ProductCategoryController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('getProductSubCategories/{id}', [ProductSubCategoryController::class, 'getSubCategories'])->name('get_product_sub_categories');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('productSubCategory', ProductSubCategoryController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('product-files/download/{id}', [ProductFileController::class, 'download'])->name('product-files.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('product-files/delete-image/{id}', [ProductFileController::class, 'deleteImage'])->name('product-files.delete_image');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('product-files/update-images', [ProductFileController::class, 'updateImages'])->name('product-files.update_images');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('product-files', ProductFileController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoice-files/download/{id}', [InvoiceFilesController::class, 'download'])->name('invoice-files.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('invoice-files', InvoiceFilesController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('orders/offline-payment-modal', [OrderController::class, 'offlinePaymentModal'])->name('orders.offline_payment_modal');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('orders/add-item', [OrderController::class, 'addItem'])->name('orders.add_item');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('orders/stripe-modal', [OrderController::class, 'stripeModal'])->name('orders.stripe_modal');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('orders/make-invoice/{orderId}', [OrderController::class, 'makeInvoice'])->name('orders.make_invoice');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('orders/payment-failed/{orderId}', [OrderController::class, 'paymentFailed'])->name('orders.payment_failed');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('orders/save-stripe-detail/', [OrderController::class, 'saveStripeDetail'])->name('orders.save_stripe_detail');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('orders/change-status/', [OrderController::class, 'changeStatus'])->name('orders.change_status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('orders/download/{id}', [OrderController::class, 'download'])->name('orders.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('orders/store-quantity/', [OrderController::class, 'storeQuantity'])->name('orders.store_quantity');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('orders', OrderController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/offline-method-description', [InvoiceController::class, 'offlineDescription'])->name('invoices.offline_method_description');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/offline-payment-modal', [InvoiceController::class, 'offlinePaymentModal'])->name('invoices.offline_payment_modal');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/stripe-modal', [InvoiceController::class, 'stripeModal'])->name('invoices.stripe_modal');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('invoices/save-stripe-detail/', [InvoiceController::class, 'saveStripeDetail'])->name('invoices.save_stripe_detail');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/delete-image', [InvoiceController::class, 'deleteInvoiceItemImage'])->name('invoices.delete_image');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('invoices/store-offline-payment', [InvoiceController::class, 'storeOfflinePayment'])->name('invoices.store_offline_payment');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('invoices/store_file', [InvoiceController::class, 'storeFile'])->name('invoices.store_file');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/file-upload', [InvoiceController::class, 'fileUpload'])->name('invoices.file_upload');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('invoices/delete-applied-credit/{id}', [InvoiceController::class, 'deleteAppliedCredit'])->name('invoices.delete_applied_credit');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/applied-credits/{id}', [InvoiceController::class, 'appliedCredits'])->name('invoices.applied_credits');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/payment-reminder/{invoiceID}', [InvoiceController::class, 'remindForPayment'])->name('invoices.payment_reminder');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('invoices/send-invoice/{invoiceID}', [InvoiceController::class, 'sendInvoice'])->name('invoices.send_invoice');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('invoices/approve-offline-invoice/{invoiceID}', [InvoiceController::class, 'approveOfflineInvoice'])->name('invoices.approve_offline_invoice');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('invoices/apply-quick-action', [InvoiceController::class, 'applyQuickAction'])->name('invoices.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/download/{id}', [InvoiceController::class, 'download'])->name('invoices.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/add-item', [InvoiceController::class, 'addItem'])->name('invoices.add_item');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/update-status/{invoiceID}', [InvoiceController::class, 'cancelStatus'])->name('invoices.update_status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/get-client-company/{projectID?}', [InvoiceController::class, 'getClientOrCompanyName'])->name('invoices.get_client_company');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('invoices/fetchTimelogs', [InvoiceController::class, 'fetchTimelogs'])->name('invoices.fetch_timelogs');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/check-shipping-address', [InvoiceController::class, 'checkShippingAddress'])->name('invoices.check_shipping_address');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/product-category/{id}', [InvoiceController::class, 'productCategory'])->name('invoices.product_category');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/toggle-shipping-address/{invoice}', [InvoiceController::class, 'toggleShippingAddress'])->name('invoices.toggle_shipping_address');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/shipping-address-modal/{invoice}', [InvoiceController::class, 'shippingAddressModal'])->name('invoices.shipping_address_modal');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('invoices/add-shipping-address/{clientId}', [InvoiceController::class, 'addShippingAddress'])->name('invoices.add_shipping_address');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/get-exchange-rate/{id}', [InvoiceController::class, 'getExchangeRate'])->name('invoices.get_exchange_rate');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('invoices/committed-modal', [InvoiceController::class, 'committedModal'])->name('invoices.committed_modal');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::post('recurring-invoice/change-status', [RecurringInvoiceController::class, 'changeStatus'])->name('recurring_invoice.change_status');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::get('recurring-invoice/export/{startDate}/{endDate}/{status}/{employee}', [RecurringInvoiceController::class, 'export'])->name('recurring_invoice.export');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::get('recurring-invoice/recurring-invoice/{id}', [RecurringInvoiceController::class, 'recurringInvoices'])->name('recurring_invoice.recurring_invoice');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::delete('recurring-invoice/delete-repeat-invoices/{id}', [RecurringInvoiceController::class, 'deleteInvoices'])->name('recurring_invoice.delete_repeat_invoices');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::resource('recurring-invoices', RecurringInvoiceController::class);
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::get('subscriptions', [\AppModules\Finance\Http\Controllers\SubscriptionController::class, 'index'])->name('subscriptions.index');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::patch('subscriptions/{id}/cancel', [\AppModules\Finance\Http\Controllers\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::patch('subscriptions/{id}/reactivate', [\AppModules\Finance\Http\Controllers\SubscriptionController::class, 'reactivate'])->name('subscriptions.reactivate');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::get('subscriptions/{id}/logs', [\AppModules\Finance\Http\Controllers\SubscriptionController::class, 'logs'])->name('subscriptions.logs');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::get('my-subscriptions', [\AppModules\Finance\Http\Controllers\SubscriptionController::class, 'clientIndex'])->name('client.subscriptions.index');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::patch('my-subscriptions/{id}/cancel', [\AppModules\Finance\Http\Controllers\SubscriptionController::class, 'clientCancel'])->name('client.subscriptions.cancel');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'invoices'], function () {
        Route::get('my-subscriptions/{id}/logs', [\AppModules\Finance\Http\Controllers\SubscriptionController::class, 'clientLogs'])->name('client.subscriptions.logs');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('invoices', InvoiceController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('estimates/delete-image', [EstimateController::class, 'deleteEstimateItemImage'])->name('estimates.delete_image');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('estimates/download/{id}', [EstimateController::class, 'download'])->name('estimates.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('estimates/send-estimate/{id}', [EstimateController::class, 'sendEstimate'])->name('estimates.send_estimate');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('estimates/change-status/{id}', [EstimateController::class, 'changeStatus'])->name('estimates.change_status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('estimates/accept/{id}', [EstimateController::class, 'accept'])->name('estimates.accept');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('estimates/decline/{id}', [EstimateController::class, 'decline'])->name('estimates.decline');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('estimates/add-item', [EstimateController::class, 'addItem'])->name('estimates.add_item');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('estimates', EstimateController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('payments/apply-quick-action', [PaymentController::class, 'applyQuickAction'])->name('payments.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('payments/download/{id}', [PaymentController::class, 'download'])->name('payments.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('payments/account-list', [PaymentController::class, 'accountList'])->name('payments.account_list');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('payments/offline-payments', [PaymentController::class, 'offlineMethods'])->name('offline.methods');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('payments/add-bulk-payments', [PaymentController::class, 'addBulkPayments'])->name('payments.add_bulk_payments');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('payments/save-bulk-payments', [PaymentController::class, 'saveBulkPayments'])->name('payments.save_bulk_payments');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('payments', PaymentController::class)->except(['edit', 'update']);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('creditnotes/store_file', [CreditNoteController::class, 'storeFile'])->name('creditnotes.store_file');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('creditnotes/file-upload', [CreditNoteController::class, 'fileUpload'])->name('creditnotes.file_upload');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('creditnotes/delete-credited-invoice/{id}', [CreditNoteController::class, 'deleteCreditedInvoice'])->name('creditnotes.delete_credited_invoice');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('creditnotes/credited-invoices/{id}', [CreditNoteController::class, 'creditedInvoices'])->name('creditnotes.credited_invoices');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('creditnotes/apply-invoice-credit/{id}', [CreditNoteController::class, 'applyInvoiceCredit'])->name('creditnotes.apply_invoice_credit');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('creditnotes/apply-to-invoice/{id}', [CreditNoteController::class, 'applyToInvoice'])->name('creditnotes.apply_to_invoice');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('creditnotes/download/{id}', [CreditNoteController::class, 'download'])->name('creditnotes.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('creditnotes/convert-invoice/{id}', [CreditNoteController::class, 'convertInvoice'])->name('creditnotes.convert-invoice');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('creditnotes', CreditNoteController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('bankaccount/apply-quick-action', [BankAccountController::class, 'applyQuickAction'])->name('bankaccounts.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('bankaccount/apply-transaction-quick-action', [BankAccountController::class, 'applyTransactionQuickAction'])->name('bankaccounts.apply_transaction_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('bankaccounts/create-transaction', [BankAccountController::class, 'createTransaction'])->name('bankaccounts.create_transaction');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('bankaccount/store-transaction', [BankAccountController::class, 'storeTransaction'])->name('bankaccounts.store_transaction');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('bankaccount/change-status', [BankAccountController::class, 'changeStatus'])->name('bankaccounts.change_status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('bankaccounts/view-transaction/{id}', [BankAccountController::class, 'viewTransaction'])->name('bankaccounts.view_transaction');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('bankaccount/destroy-transaction', [BankAccountController::class, 'destroyTransaction'])->name('bankaccounts.destroy_transaction');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('bankaccount/generate-statement/{id}', [BankAccountController::class, 'generateStatement'])->name('bankaccounts.generate_statement');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('bankaccount/get-bank-statement', [BankAccountController::class, 'getBankStatement'])->name('bankaccounts.get_bank_statement');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('bankaccounts', BankAccountController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'expenses'], function () {
        Route::post('recurring-expenses/change-status', [RecurringExpenseController::class, 'changeStatus'])->name('recurring-expenses.change_status');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'expenses'], function () {
        Route::resource('recurring-expenses', RecurringExpenseController::class);
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'expenses'], function () {
        Route::get('change-status', [ExpenseController::class, 'getEmployeeProjects'])->name('expenses.get_employee_projects');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'expenses'], function () {
        Route::get('category', [ExpenseController::class, 'getCategoryEmployee'])->name('expenses.get_category_employees');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'expenses'], function () {
        Route::post('change-status', [ExpenseController::class, 'changeStatus'])->name('expenses.change_status');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'expenses'], function () {
        Route::post('apply-quick-action', [ExpenseController::class, 'applyQuickAction'])->name('expenses.apply_quick_action');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'expenses'], function () {
        Route::get('import', [ExpenseController::class, 'import'])->name('expenses.import');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'expenses'], function () {
        Route::post('import', [ExpenseController::class, 'importStore'])->name('expenses.import.store');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::group(['prefix' => 'expenses'], function () {
        Route::post('import/process', [ExpenseController::class, 'importProcess'])->name('expenses.import.process');
});
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('expenses', ExpenseController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('expenseCategory', ExpenseCategoryController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('estimate-template/add-item', [EstimateTemplateController::class, 'addItem'])->name('estimate-template.add_item');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('estimate-template', EstimateTemplateController::class);
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('estimates-template/delete-image', [EstimateTemplateController::class, 'deleteEstimateItemImage'])->name('estimate-template.delete_image');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('estimate-template/download/{id}', [EstimateTemplateController::class, 'download'])->name('estimate-template.download');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('estimate-request/apply-quick-action', [EstimateRequestController::class, 'applyQuickAction'])->name('estimate-request.apply_quick_action');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('estimate-request/change-status/', [EstimateRequestController::class, 'changeStatus'])->name('estimate-request.change_status');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('estimate-request-confirm-rejected/{id}', [EstimateRequestController::class, 'rejectConfirmation'])->name('estimate-request.confirm_rejected');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::get('estimate-request/send-estimate-request', [EstimateRequestController::class, 'sendEstimateRequest'])->name('estimate-request.send_estimate_request');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::post('estimate-request/send_estimate_mail', [EstimateRequestController::class, 'sendEstimateMail'])->name('estimate-request.send_estimate_mail');
});

Route::group(['middleware' => 'auth', 'prefix' => 'account'], function () {
    Route::resource('estimate-request', EstimateRequestController::class);
});
});
