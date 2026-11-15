<?php

namespace App\Http\Controllers;

use App\Actions\CrewBids\ReviewBidSubmission;
use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use App\Models\BidSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use SensitiveParameter;
use Throwable;

class CrewBidSubmissionController extends Controller
{
    public function __construct(private ReviewBidSubmission $reviews) {}

    public function create(Request $request, BidPackage $package, string $seat): View
    {
        $user = $this->user($request);
        $workspace = $this->reviews->workspace($user, $package, $seat);
        $pending = BidSubmission::query()->where('user_id', $user->id)->where('status', 'unknown')->latest()->first();

        return view('crew-bids.submission', compact('package', 'workspace', 'pending'));
    }

    public function review(#[SensitiveParameter] Request $request, BidPackage $package, string $seat): RedirectResponse
    {
        $this->reviews->workspace($this->user($request), $package, $seat);
        if (! $this->validCredentials($request) || ! ctype_digit((string) $request->input('revision'))) {
            return to_route('crew-bids.submissions.create', ['package' => $package, 'seat' => $seat])->with('bid_submission_error', 'Enter your employee number and password, and reopen your saved bid list.');
        }
        try {
            $submission = $this->reviews->handle($this->user($request), $package, $seat, (int) $request->input('revision'),
                $request->string('employee')->toString(), $request->string('portal_password')->toString());

            return to_route('crew-bids.submissions.show', $submission);
        } catch (BidPortalException $exception) {
            return to_route('crew-bids.submissions.create', ['package' => $package, 'seat' => $seat])->with('bid_submission_error', $exception->getMessage());
        } catch (Throwable) {
            return to_route('crew-bids.submissions.create', ['package' => $package, 'seat' => $seat])->with('bid_submission_error', 'The portal comparison failed. Your saved bid list is unchanged.');
        }
    }

    public function show(Request $request, BidSubmission $submission): View
    {
        $this->reviews->authorize($this->user($request), $submission);

        return view('crew-bids.submission-review', ['submission' => $submission, 'review' => $submission->review]);
    }

    public function submit(#[SensitiveParameter] Request $request, BidSubmission $submission): RedirectResponse
    {
        $this->reviews->authorize($this->user($request), $submission);
        if (! $this->validCredentials($request) || ! $request->boolean('confirmed')) {
            return to_route('crew-bids.submissions.show', $submission)->with('bid_submission_error', 'Enter your credentials again and confirm the exact replacement and your bid eligibility.');
        }
        try {
            $this->reviews->submit($this->user($request), $submission, $request->string('employee')->toString(), $request->string('portal_password')->toString());
        } catch (BidPortalException $exception) {
            return to_route('crew-bids.submissions.show', $submission)->with('bid_submission_error', $exception->getMessage());
        } catch (Throwable) {
            return to_route('crew-bids.submissions.show', $submission)->with('bid_submission_error', 'The operation could not be completed. Check the submission status before trying again.');
        }

        return to_route('crew-bids.submissions.show', $submission);
    }

    public function reconcile(#[SensitiveParameter] Request $request, BidSubmission $submission): RedirectResponse
    {
        $this->reviews->authorize($this->user($request), $submission);
        if (! $this->validCredentials($request)) {
            return to_route('crew-bids.submissions.show', $submission)->with('bid_submission_error', 'Enter your employee number and password to read the portal report.');
        }
        try {
            $this->reviews->reconcile($this->user($request), $submission, $request->string('employee')->toString(), $request->string('portal_password')->toString());
        } catch (BidPortalException $exception) {
            return to_route('crew-bids.submissions.show', $submission)->with('bid_submission_error', $exception->getMessage());
        } catch (Throwable) {
            return to_route('crew-bids.submissions.show', $submission)->with('bid_submission_error', 'The portal report could not be checked. The submission status remains unknown.');
        }

        return to_route('crew-bids.submissions.show', $submission);
    }

    private function validCredentials(#[SensitiveParameter] Request $request): bool
    {
        return ! Validator::make($request->only(['employee', 'portal_password']), [
            'employee' => ['required', 'string', 'regex:/\A\d{1,20}\z/'],
            'portal_password' => ['required', 'string', 'max:256'],
        ])->fails();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
