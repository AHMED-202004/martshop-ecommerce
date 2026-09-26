<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignSupportTicketRequest;
use App\Http\Requests\Admin\ReplySupportTicketRequest;
use App\Http\Requests\Admin\TransitionSupportTicketRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SupportTicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('contact-messages.view'), 403);

        return view('admin.contact-messages.index', [
            'messages' => ContactMessage::query()
                ->select(['id', 'user_id', 'topic', 'created_at'])
                ->with('user:id,name')
                ->latest('id')
                ->paginate(30),
        ]);
    }

    public function show(Request $request, string $contactMessage, AuditLogger $audit): View
    {
        abort_unless($request->user()->hasPermission('contact-messages.view'), 403);

        $message = ContactMessage::query()
            ->select([
                'id', 'user_id', 'topic', 'contact', 'ref', 'message', 'status', 'assigned_to',
                'assigned_at', 'first_response_at', 'resolved_at', 'closed_at', 'sla_due_at',
                'reopened_count', 'lock_version', 'created_at',
            ])
            ->with([
                'user:id,name',
                'assignee:id,name,employee_number',
                'replies' => fn ($replies) => $replies
                    ->select(['id', 'contact_message_id', 'author_id', 'body', 'is_internal', 'created_at'])
                    ->with('author:id,name'),
            ])
            ->findOrFail($contactMessage);
        $audit->record('contact-message.viewed', $message);

        $canManage = $request->user()->hasPermission('contact-messages.manage');
        $agents = $canManage ? User::query()
            ->select(['id', 'name', 'employee_number'])
            ->where('account_status', AccountStatus::Active->value)
            ->where(fn (Builder $users) => $users
                ->whereHas('directPermissions', fn (Builder $permissions) => $permissions->where('slug', 'contact-messages.manage'))
                ->orWhereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('slug', 'contact-messages.manage')))
            ->orderBy('name')
            ->get() : collect();

        return view('admin.contact-messages.show', [
            'contactMessage' => $message,
            'canManage' => $canManage,
            'agents' => $agents,
            'statuses' => SupportTicketStatus::cases(),
        ]);
    }

    public function assign(AssignSupportTicketRequest $request, string $contactMessage, SupportTicketService $tickets): RedirectResponse
    {
        $tickets->assign($request->user(), (int) $contactMessage, $request->integer('assigned_to'), $request->integer('expected_version'));

        return back()->with('success', 'تم إسناد التذكرة.');
    }

    public function reply(ReplySupportTicketRequest $request, string $contactMessage, SupportTicketService $tickets): RedirectResponse
    {
        $tickets->reply(
            $request->user(),
            (int) $contactMessage,
            $request->validated('body'),
            $request->boolean('is_internal'),
            $request->integer('expected_version'),
        );

        return back()->with('success', 'تم حفظ الرد.');
    }

    public function transition(TransitionSupportTicketRequest $request, string $contactMessage, SupportTicketService $tickets): RedirectResponse
    {
        $tickets->transition(
            $request->user(),
            (int) $contactMessage,
            SupportTicketStatus::from($request->validated('status')),
            $request->integer('expected_version'),
        );

        return back()->with('success', 'تم تحديث حالة التذكرة.');
    }
}
