<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Services\MarketplaceSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function create()
    {
        $topics = StoreContactMessageRequest::topics();

        // لا نملأ ببريد noemail.local
        $contact = '';
        if (Auth::check()) {
            $u = Auth::user();
            if ($u->email && !Str::endsWith($u->email, '@noemail.local')) {
                $contact = $u->email;
            } elseif (!empty($u->phone)) {
                $contact = $u->phone;
            } elseif (!empty($u->mobile)) {
                $contact = $u->mobile;
            }
        }

        $prefill = ['contact' => $contact];

        return view('contact.create', compact('topics','prefill'));
    }

    public function store(StoreContactMessageRequest $request, MarketplaceSettings $settings)
    {
        $data = $request->validated();

        ContactMessage::create([
            'user_id' => auth()->id(),
            'topic' => $data['topic'],
            'contact' => $data['contact'],
            'ref' => $data['ref'] ?? null,
            'message' => $data['message'],
            'sla_due_at' => now()->addMinutes($settings->integer('support.first_response_sla_minutes')),
        ]);

        return back()->with('status','تم إرسال رسالتك إلى فريق الدعم. سنعاود التواصل معك قريبًا.');
    }
}
