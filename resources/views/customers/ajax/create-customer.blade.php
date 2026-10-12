@php
    // Set when editing (Edit Customer page)
    $customer = $customer ?? null;
    $selectedOwnerId = $customer ? optional($employees->firstWhere('name', $customer->lead_owner))->id : null;
@endphp
<form id="createCustomerForm" novalidate>
    @csrf
    @if ($customer)
        @method('PUT')
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="customerName">Customer Name <span class="text-danger">*</span></label>
                <input type="text" name="customer_name" id="customerName" class="form-control" value="{{ $customer->customer_name ?? '' }}"
                       placeholder="Karthik Kumar" maxlength="191" autocomplete="off">
                <div class="invalid-feedback" data-error-for="customer_name"></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="companyName">Company Name <span class="text-danger">*</span></label>
                <input type="text" name="company_name" id="companyName" class="form-control" value="{{ $customer->company_name ?? '' }}"
                       placeholder="ABC Technologies Pvt. Ltd." maxlength="191" autocomplete="off">
                <small class="text-muted f-11">Letters and special characters only (no numbers).</small>
                <div class="invalid-feedback" data-error-for="company_name"></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="customerEmail">Email</label>
                <input type="text" name="email" id="customerEmail" class="form-control" value="{{ $customer->email ?? '' }}"
                       placeholder="name@gmail.com" maxlength="191" autocomplete="off">
                <div class="invalid-feedback" data-error-for="email"></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="customerMobile">Mobile</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white">+91</span>
                    </div>
                    <input type="text" name="mobile" id="customerMobile" class="form-control"
                           value="{{ $customer ? preg_replace('/^\+91\s*/', '', (string) $customer->mobile) : '' }}"
                           placeholder="9876543210" maxlength="10" inputmode="numeric" autocomplete="off">
                    <div class="invalid-feedback" data-error-for="mobile"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="leadOwner">Lead Owner</label>
                {{-- bootstrap-select: always opens downwards (data-dropup-auto=false), searchable, 8 rows then scroll --}}
                <select name="lead_owner" id="leadOwner" class="form-control select-picker"
                        data-live-search="true" data-dropup-auto="false" data-size="8"
                        title="Select Lead Owner">
                    <option value="">Select Lead Owner</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected($selectedOwnerId == $employee->id)>
                            {{ $employee->name }}{{ $employee->designation_name ? ' (' . $employee->designation_name . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="invalid-feedback" data-error-for="lead_owner"></div>
            </div>
        </div>
    </div>

    @if (!empty($fullPage))
        <div class="d-flex" style="gap: 8px;">
            <button type="submit" class="btn btn-primary" id="saveCustomerBtn">{{ $customer ? 'Update Customer' : 'Save Customer' }}</button>
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    @else
        <div class="modal-footer px-0 pb-0">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveCustomerBtn">Save Customer</button>
        </div>
    @endif
</form>

<script>
    $(document).ready(function () {
        var form = $('#createCustomerForm');

        $('#leadOwner').selectpicker();
        var emojiPattern = /[\u{1F000}-\u{1FAFF}\u{2300}-\u{23FF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}\u{FE0F}\u{200D}\u{20E3}]/u;

        // Same rules as CustomerController@storeCustomer
        var rules = {
            customer_name: function (value) {
                value = value.trim();
                if (!value) return 'Customer name is required.';
                if (emojiPattern.test(value)) return 'Emojis are not allowed.';
                if (!/^[A-Za-z ]+$/.test(value)) return 'Customer name can contain only letters and spaces.';
                return '';
            },
            company_name: function (value) {
                value = value.trim();
                if (!value) return 'Company name is required.';
                if (emojiPattern.test(value)) return 'Emojis are not allowed.';
                if (/[0-9]/.test(value)) return 'Company name cannot contain numbers.';
                if (!/[A-Za-z]/.test(value)) return 'Company name must contain letters.';
                return '';
            },
            email: function (value) {
                value = value.trim();
                if (!value) return '';
                if (emojiPattern.test(value)) return 'Emojis are not allowed.';
                if (!/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/.test(value)) return 'Enter a valid email address, e.g. name@gmail.com.';
                return '';
            },
            mobile: function (value) {
                value = value.trim();
                if (!value) return '';
                if (!/^[0-9]{10}$/.test(value)) return 'Enter exactly 10 digits.';
                if (!/^[6-9]/.test(value)) return 'Mobile number must start with 6, 7, 8 or 9.';
                return '';
            }
        };

        function showError(name, message) {
            var input = form.find('[name="' + name + '"]');
            input.toggleClass('is-invalid', !!message);
            form.find('[data-error-for="' + name + '"]').text(message || '');
        }

        function validateField(name) {
            var message = rules[name](form.find('[name="' + name + '"]').val() || '');
            showError(name, message);
            return !message;
        }

        // Block characters that can never be valid while typing
        $('#customerName').on('input', function () {
            this.value = this.value.replace(/[^A-Za-z ]/g, '');
        });
        $('#customerMobile').on('input', function () {
            // +91 is fixed beside the box: allow digits only, max 10 (drops a pasted +91 / 91 prefix)
            var digits = this.value.replace(/[^0-9]/g, '');
            if (digits.length > 10 && digits.indexOf('91') === 0) digits = digits.substring(2);
            this.value = digits.substring(0, 10);
        });
        $('#companyName').on('input', function () {
            this.value = this.value.replace(/[0-9]/g, '');
        });

        Object.keys(rules).forEach(function (name) {
            form.find('[name="' + name + '"]').on('input blur', function () {
                validateField(name);
            });
        });

        form.off('submit').on('submit', function (e) {
            e.preventDefault();

            var valid = true;
            Object.keys(rules).forEach(function (name) {
                if (!validateField(name)) valid = false;
            });

            if (!valid) {
                form.find('.is-invalid').first().focus();
                return;
            }

            var btn = $('#saveCustomerBtn');
            var saveLabel = btn.text();
            btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: "{{ $customer ? route('customers.update', $customer->id) : route('customers.store') }}",
                type: 'POST',
                data: form.serialize(),
                success: function (r) {
                    if (r.status === 'success') {
                        @if (!empty($fullPage))
                            // Add Customer page: back to the customer list
                            window.location.href = "{{ route('customers.index') }}";
                            return;
                        @endif

                        $('#customerModal').modal('hide');

                        // Opened from the meeting form's "+ Add": select the new customer there instead of reloading
                        if (typeof window.onCustomerCreated === 'function' && r.customer) {
                            window.onCustomerCreated(r.customer);
                            window.onCustomerCreated = null;
                            return;
                        }

                        location.reload();
                    } else {
                        btn.prop('disabled', false).text(saveLabel);
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false).text(saveLabel);
                    var errors = xhr.responseJSON && xhr.responseJSON.errors;

                    if (errors) {
                        Object.keys(errors).forEach(function (name) {
                            showError(name, errors[name][0]);
                        });
                        form.find('.is-invalid').first().focus();
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to save the customer. Please try again.' });
                    }
                }
            });
        });
    });
</script>
