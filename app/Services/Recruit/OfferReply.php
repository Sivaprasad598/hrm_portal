<?php

namespace App\Services\Recruit;

use App\Models\JobOffer;
use App\Models\User;
use App\Notifications\RecruitOfferMail;
use App\Notifications\RecruitOfferReplyNotice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * The candidate's own answer to an offer.
 *
 * When HR marks an offer sent they can email it: the email carries a private,
 * expiring link (signed, no login) to a page where the candidate reads the offer
 * and accepts or declines it. The answer is recorded as HR's would be - the offer
 * Accepted (the proof: when, the name they typed, from where) or Declined - the
 * application moves, the timeline keeps it, and HR is told.
 */
class OfferReply
{

    public function __construct(protected Pipeline $pipeline)
    {
    }

    /** Until when the link works: the offer's "valid until" (a week at least from now), else two weeks. */
    public static function expires(JobOffer $offer): CarbonInterface
    {
        $week = now()->addDays(7);

        if ($offer->valid_until) {
            $until = $offer->valid_until->copy()->endOfDay();

            return $until->greaterThan($week) ? $until : $week;
        }

        return now()->addDays(14);
    }

    /** The page the candidate opens (signed on its path, so it works on any host name of the portal). */
    public static function link(JobOffer $offer): string
    {
        return url(URL::temporarySignedRoute('offer-reply.show', self::expires($offer), ['offer' => $offer->id], false));
    }

    /** Where the page posts the answer: signed the same way, for as long as the page. */
    public static function answerLink(JobOffer $offer): string
    {
        return url(URL::temporarySignedRoute('offer-reply.answer', self::expires($offer), ['offer' => $offer->id], false));
    }

    /** Email the offer with the link. @throws RuntimeException */
    public function send(JobOffer $offer): void
    {
        $offer->loadMissing('application.candidate', 'application.position', 'designation');
        $candidate = $offer->application->candidate;

        if ($offer->status !== 'sent') {
            throw new RuntimeException('Only an offer that has been sent can be emailed for the candidate to answer.');
        }

        if (!$candidate->email) {
            throw new RuntimeException('There is no email on file for ' . $candidate->name . '.');
        }

        $mail = new RecruitOfferMail($offer, self::link($offer));
        Notification::route('mail', [$candidate->email => $candidate->name])->notify($mail);

        $offer->emailed_at = now();
        $offer->save();

        $this->pipeline->log($candidate->id, $offer->job_application_id, 'email',
            'To ' . $candidate->email . ' — ' . RecruitOfferMail::subject($offer) . "\n" . implode("\n", RecruitOfferMail::lines($offer))
            . "\nWith the link to accept or decline online (works until " . self::expires($offer)->format($offer->company?->date_format ?? 'd-m-Y') . ').',
            null, null, $offer->company_id);
    }

    /**
     * The candidate's answer from the page.
     *
     * @param string $answer accepted|declined
     * @throws RuntimeException
     */
    public function record(JobOffer $offer, string $answer, string $signature, ?string $note, ?string $ip): void
    {
        $offer->loadMissing('application.candidate', 'application.position');
        $application = $offer->application;
        $candidate = $application->candidate;

        if ($offer->status !== 'sent') {
            throw new RuntimeException('This offer has already been answered or is no longer open.');
        }

        if (!in_array($answer, ['accepted', 'declined'], true)) {
            throw new RuntimeException('Choose to accept or decline the offer.');
        }

        $format = ($offer->company?->date_format ?? 'd-m-Y') . ' h:i A';
        $tz = $offer->company?->timezone ?? config('app.timezone');
        $proof = ($answer === 'accepted' ? 'Accepted' : 'Declined') . ' online by the candidate on ' . now()->tz($tz)->format($format)
            . ' · signed as "' . $signature . '"' . ($ip ? ' · from ' . $ip : '')
            . ($note ? "\n" . $note : '');

        DB::transaction(function () use ($offer, $answer, $proof, $application, $note) {
            $offer->status = $answer;
            $offer->responded_at = now();
            $offer->response_note = $proof;
            $offer->save();

            $this->pipeline->log($application->candidate_id, $application->id, 'offer',
                'Offer ' . $answer . ($offer->reference_no ? ' (' . $offer->reference_no . ')' : '') . ' — ' . $proof, null, null, $offer->company_id);

            if ($answer === 'accepted') {
                $this->pipeline->move($application, 'offer_accepted', null, 'Accepted online by the candidate', false);
            } else {
                $this->pipeline->move($application, 'withdrawn', 'Offer declined', trim('Declined online by the candidate. ' . (string) $note), false);
            }
        });

        // HR is told: whoever made the offer, else the company's admins
        $people = collect();

        if ($offer->created_by && ($maker = User::withoutGlobalScopes()->where('status', 'active')->find($offer->created_by))) {
            $people->push($maker);
        }

        if ($people->isEmpty()) {
            $people = User::allAdmins($offer->company_id);
        }

        foreach ($people as $person) {
            try {
                $person->notify(new RecruitOfferReplyNotice($offer, $answer));
            } catch (\Throwable $e) {
                logger()->warning('Offer reply notice not sent', ['offer' => $offer->id, 'user' => $person->id, 'error' => $e->getMessage()]);
            }
        }
    }

}
