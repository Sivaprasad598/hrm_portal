@if (global_setting()->system_update == 1 &&  in_array('admin', user_roles()))
    @php
        // Asked of the update server at most every 6 hours: its version log does not
        // answer, the package only caches answers, so every dashboard load waited ~0.5 s
        $updateVersionInfo = cache()->remember('dashboard.update_version_info', now()->addHours(6),
            fn () => \Froiden\Envato\Functions\EnvatoUpdate::updateVersionInfo() ?: []);
    @endphp
    @if (isset($updateVersionInfo['lastVersion']))
        <div class="col-md-12">
            <x-alert type="info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fa fa-gift"></i> @lang('modules.update.newUpdate') <span
                            class="badge badge-success">{{ $updateVersionInfo['lastVersion'] }}</span>
                    </div>
                    <div>
                        <x-forms.link-primary :link="route('update-settings.index')" icon="arrow-right">
                            @lang('modules.update.updateNow')
                        </x-forms.link-primary>
                    </div>

                </div>
            </x-alert>
        </div>
    @endif

@endif
