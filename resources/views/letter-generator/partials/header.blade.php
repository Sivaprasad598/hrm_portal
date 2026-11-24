{{-- The Letter Generator's switcher (Offer Letter | Relieving Letter | Experience Letter | Payslip) and its buttons. --}}
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <div class="btn-group lg-tabs" role="group">
        @if (\App\Http\Controllers\OfferLetterController::canUse())
            <a href="{{ route('offer-letters.generate', ['type' => 'offer']) }}" class="btn {{ $type === 'offer' ? 'btn-primary' : 'btn-secondary' }}">Offer Letter</a>
        @endif
        @if (\App\Http\Controllers\RelievingLetterController::canUse())
            <a href="{{ route('offer-letters.generate', ['type' => 'relieving']) }}" class="btn {{ $type === 'relieving' ? 'btn-primary' : 'btn-secondary' }}">Relieving Letter</a>
            <a href="{{ route('offer-letters.generate', ['type' => 'experience']) }}" class="btn {{ $type === 'experience' ? 'btn-primary' : 'btn-secondary' }}">Experience Letter</a>
        @endif
        @if (\App\Http\Controllers\LetterGeneratorController::canPayslip())
            <a href="{{ route('offer-letters.generate', ['type' => 'payslip']) }}" class="btn {{ $type === 'payslip' ? 'btn-primary' : 'btn-secondary' }}">Payslip</a>
        @endif
    </div>
    <div>
        @if (\App\Http\Controllers\OfferLetterController::canUse())
            <x-forms.link-secondary :link="route('offer-letters.index')" icon="list" class="mr-2">Issued Letters</x-forms.link-secondary>
        @endif
        @if (\App\Http\Controllers\OfferLetterSettingController::canEdit())
            <x-forms.link-secondary :link="route('offer-letter-settings.index')" icon="cog">Letter Settings</x-forms.link-secondary>
        @endif
    </div>
</div>
