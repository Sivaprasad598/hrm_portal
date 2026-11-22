<?php

namespace App\Http\Controllers;

use App\Models\JobOffer;
use App\Services\Recruit\OfferReply;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as PlainController;
use RuntimeException;

/**
 * The page a candidate opens from the offer email: the offer, and Accept / Decline.
 * No login: the link is signed and expires (OfferReply::link), and only an offer
 * that is out (sent) can be answered.
 */
class OfferReplyController extends PlainController
{

    public function show(Request $request, $offer)
    {
        $offer = $this->offer($offer);

        return view('recruitment.offers.reply', [
            'offer' => $offer,
            'company' => $offer->company,
            'answerUrl' => OfferReply::answerLink($offer),
            'done' => session('offer_reply_done'),
        ]);
    }

    public function answer(Request $request, $offer, OfferReply $reply)
    {
        $offer = $this->offer($offer);

        $request->validate([
            'answer' => 'required|in:accepted,declined',
            'signature' => 'required|string|max:120',
            'agree' => 'required_if:answer,accepted',
            'note' => 'nullable|string|max:2000',
        ], [
            'signature.required' => 'Type your full name to sign your answer.',
            'agree.required_if' => 'Tick the box to confirm you accept the offer.',
        ]);

        try {
            $reply->record($offer, $request->answer, trim($request->signature), trim((string) $request->note) ?: null, $request->ip());
        } catch (RuntimeException $e) {
            return back()->withErrors(['answer' => $e->getMessage()]);
        }

        return redirect()->to(OfferReply::link($offer->fresh()))->with('offer_reply_done', $request->answer);
    }

    private function offer($id): JobOffer
    {
        return JobOffer::withoutGlobalScopes()->with(['application' => fn ($q) => $q->withoutGlobalScopes(),
            'application.candidate' => fn ($q) => $q->withoutGlobalScopes(), 'application.position' => fn ($q) => $q->withoutGlobalScopes(),
            'designation' => fn ($q) => $q->withoutGlobalScopes(), 'company'])->findOrFail($id);
    }

}
