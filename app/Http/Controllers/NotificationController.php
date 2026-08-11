<?php

namespace App\Http\Controllers;

use App\Models\PanelNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Halaman daftar notifikasi lengkap (filter + pagination).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = PanelNotification::relevantFor($user)->latest();

        if ($type = $request->query('type')) {
            $query->where('notification_type', $type);
        }
        if ($request->boolean('unread_only')) {
            $query->where('status', 'unread');
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        $notifications = $query->paginate(20)->withQueryString();
        $typeOptions = PanelNotification::typeOptions();
        $severityOptions = PanelNotification::severityOptions();

        $unreadCount = PanelNotification::relevantFor($user)->unread()->count();

        return view('notifications.index', [
            'notifications' => $notifications,
            'typeOptions' => $typeOptions,
            'severityOptions' => $severityOptions,
            'filters' => $request->only(['type', 'severity', 'unread_only']),
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * JSON feed untuk polling 30 detik dari bell icon (portal guru/wali).
     * Return: unread_count + 5 notif terbaru.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $relevant = PanelNotification::relevantFor($user);
        $unreadCount = (clone $relevant)->unread()->count();
        $recent = (clone $relevant)->latest()->take(5)->get();

        return response()->json([
            'unread_count' => $unreadCount,
            'notifications' => $recent->map(fn (PanelNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'severity' => $n->severity,
                'type' => $n->notification_type,
                'link_url' => $n->link_url,
                'status' => $n->status,
                'batch_count' => $n->batch_count,
                'created_at' => $n->created_at?->diffForHumans(),
                'created_at_iso' => $n->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Tandai satu notifikasi sebagai dibaca (auto saat user klik).
     */
    public function markAsRead(Request $request, PanelNotification $notification): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        // Hanya pemilik yang boleh mark read ( atau broadcast by role miliknya ).
        $owns = $notification->user_id === $user->id
            || ($notification->user_id === null && $user->hasRole($notification->audience_role));
        abort_unless($owns, 403);

        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    /**
     * Tandai SEMUA notifikasi sebagai dibaca.
     */
    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $roleNames = $user->roles()->pluck('name')->all();

        PanelNotification::query()
            ->whereNull('archived_at')
            ->where('status', 'unread')
            ->where(function ($q) use ($user, $roleNames) {
                $q->where('user_id', $user->id);
                if (! empty($roleNames)) {
                    $q->orWhereIn('audience_role', $roleNames);
                }
            })
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'Semua notifikasi ditandai dibaca.');
    }

    /**
     * Arsipkan (hapus dari tampilan) satu notifikasi.
     */
    public function archive(Request $request, PanelNotification $notification): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $owns = $notification->user_id === $user->id
            || ($notification->user_id === null && $user->hasRole($notification->audience_role));
        abort_unless($owns, 403);

        $notification->archive();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'Notifikasi dihapus.');
    }
}