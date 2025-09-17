<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;

class ThankYouController extends Controller
{
    /**
     * Show the thank you page after successful payment.
     */
    public function show(Request $request): View
    {
        $sessionId = $request->query('session_id');
        $subscriptionData = null;

        if ($sessionId) {
            try {
                // Retrieve the checkout session from Stripe
                $session = Session::retrieve($sessionId);

                if ($session && $session->payment_status === 'paid') {
                    $subscriptionData = [
                        'customer_email' => $session->customer_details->email ?? null,
                        'customer_name' => $session->customer_details->name ?? null,
                        'amount_total' => $session->amount_total,
                        'currency' => strtoupper($session->currency),
                        'subscription_id' => $session->subscription ?? null,
                    ];

                    // Get plan name from metadata if available
                    if (isset($session->metadata['plan_id'])) {
                        $planMap = [
                            'free' => 'Plan Gratuito',
                            'starter' => 'Plan Starter',
                            'business' => 'Plan Business',
                            'enterprise' => 'Plan Enterprise',
                        ];
                        $subscriptionData['plan_name'] = $planMap[$session->metadata['plan_id']] ?? 'Plan Desconocido';
                    }

                    Log::info('Thank you page loaded with subscription data', [
                        'session_id' => $sessionId,
                        'customer_email' => $subscriptionData['customer_email'],
                        'plan_name' => $subscriptionData['plan_name'] ?? 'Unknown',
                        'amount' => $subscriptionData['amount_total'],
                    ]);
                }

            } catch (ApiErrorException $e) {
                Log::warning('Failed to retrieve checkout session for thank you page', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return view('thank-you', [
            'subscriptionData' => $subscriptionData,
            'sessionId' => $sessionId,
        ]);
    }
}
