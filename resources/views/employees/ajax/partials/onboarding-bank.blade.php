{{-- $part 'confirm': re-enter the account number (beside the account number).
     $part 'branch': what the IFSC directory says about the code (beside the IFSC).
     $bankName / $bankBranch: what was saved (Edit). --}}
@if (($part ?? 'confirm') === 'confirm')
    <div class="col-lg-3 col-md-6">
        <x-forms.text fieldId="personal_account_number_confirmation" fieldLabel="Re-enter Account Number"
            fieldName="personal_account_number_confirmation" :fieldRequired="$confirmRequired ?? true"
            inputmode="numeric" maxlength="18" autocomplete="off" onpaste="return false" ondrop="return false"
            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 18);"
            :fieldPlaceholder="($confirmRequired ?? true) ? 'Type the account number again' : 'Only when changing the account number'">
        </x-forms.text>
    </div>
@else
    <div class="col-lg-3 col-md-6">
        <div class="my-3">
            <label class="f-14 text-dark-grey mb-1 d-block">Bank &amp; Branch</label>
            <div id="ifsc-result" class="f-13 {{ !empty($bankName) ? 'ok' : '' }}">
                @if (!empty($bankName))
                    <i class="fa fa-check-circle mr-1"></i>{{ $bankName }}{{ !empty($bankBranch) ? ', ' . $bankBranch : '' }}
                @else
                    <span class="text-lightest">Enter the IFSC to see the bank and branch.</span>
                @endif
            </div>
            <p class="f-11 text-lightest mb-0 mt-1">Checked against the RBI branch directory.</p>
        </div>
    </div>
@endif
