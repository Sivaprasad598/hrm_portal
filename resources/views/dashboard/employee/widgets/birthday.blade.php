@if (in_array('birthday', $activeWidgets) && in_array('employees', user_modules()))
    <!-- EMP DASHBOARD BIRTHDAY START -->
    <div class="col-sm-12">
        @if (in_array('admin', user_roles()) && !is_null(user()->employeeDetail) && !is_null(user()->employeeDetail->designation_id))
            <div class="mb-3 text-right">
                <x-forms.link-primary :link="route('legacy.page', 'identity_card') . '?id=' . user()->id . '&designation_id=' . user()->employeeDetail->designation_id" icon="id-card">
                    @lang('app.menu.identityCard')
                </x-forms.link-primary>
            </div>
        @endif
        <x-cards.data class="e-d-info mb-2" :title="__('modules.dashboard.birthday')" padding="false"
                      otherClasses="h-200">
            <x-table>
                @forelse ($upcomingBirthdays as $upcomingBirthday)
                    <tr>
                        <td class="pl-20">
                            <x-employee :user="$upcomingBirthday->user"/>
                        </td>
                        <td>
                            <span class="badge badge-light p-2">
                                <i class="fa fa-birthday-cake"></i>
                                {{ $upcomingBirthday->date_of_birth->translatedFormat('d M') }}
                            </span>
                        </td>
                        <td class="pr-20" align="right">
                            @php
                                $currentYear = now(company()->timezone)->year;
                                $birthdayThisYear = $upcomingBirthday->date_of_birth
                                    ->copy()
                                    ->timezone(company()->timezone)
                                    ->setYear($currentYear);

                                $diffInDays = $birthdayThisYear->copy()->diffForHumans(now()->timezone(company()->timezone),[
                                    'syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_AUTO,
                                    'options' => \Carbon\Carbon::JUST_NOW | \Carbon\Carbon::ONE_DAY_WORDS | \Carbon\Carbon::TWO_DAY_WORDS,
                                ]);

                            @endphp

                            @if ($birthdayThisYear->isToday())
                                <span class="badge badge-light text-success p-2"><i class="fa fa-smile"></i> @lang('app.today')</span>
                            @else
                                <span class="badge badge-light p-2">{{ $diffInDays }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="shadow-none">
                            <x-cards.no-record icon="birthday-cake" :message="__('messages.noRecordFound')"/>
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </x-cards.data>
    </div>
    <!-- EMP DASHBOARD BIRTHDAY END -->
@endif
