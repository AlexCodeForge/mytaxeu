<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\StripePortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingPortalController extends Controller
{
    public function __construct(
        private readonly StripePortalService $portalService
    ) {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * Redirect user to Stripe Customer Portal.
     */
    public function redirect(Request $request): RedirectResponse
    {
        try {
            $user = $request->user();
            $returnUrl = url('/billing?portal_return=true');
            
            $session = $this->portalService->createPortalSession($user, $returnUrl);
            
            return redirect($session->url);
        } catch (\Exception $e) {
            return redirect()
                ->route('billing')
                ->with('error', 'Error al acceder al portal de facturación: ' . $e->getMessage());
        }
    }

    /**
     * Handle return from Stripe Customer Portal.
     */
    public function handleReturn(Request $request): RedirectResponse
    {
        // Set flash message for successful return
        if ($request->has('portal_return')) {
            session()->flash('portal_return', true);
        }

        return redirect()->route('billing');
    }
}
