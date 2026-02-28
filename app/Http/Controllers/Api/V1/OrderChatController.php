<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\OrderMessageSent;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderChatController extends Controller
{
    use ApiResponse;

    public function index(Request $request, Order $order)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        if (!$this->isParticipant($user, $order)) {
            return $this->fail('Huruhusiwi kuona chat ya order hii.', 403);
        }

        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'before_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $limit = (int) ($data['limit'] ?? 50);
        $beforeId = array_key_exists('before_id', $data) ? (int) $data['before_id'] : null;

        $conversation = $this->conversationForOrder($order);

        Message::query()
            ->where('conversation_id', (int) $conversation->id)
            ->where('sender_id', '!=', (int) $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        $messagesQuery = $conversation->messages()
            ->with('sender:id,name,phone,role,profile_image_path')
            ->orderByDesc('id')
            ->limit($limit);

        if ($beforeId !== null && $beforeId > 0) {
            $messagesQuery->where('id', '<', $beforeId);
        }

        $messages = $messagesQuery->get()->reverse()->values();

        return $this->ok([
            'order_id' => (int) $order->id,
            'conversation_id' => (int) $conversation->id,
            'can_send' => !in_array((string) $order->status, ['completed', 'cancelled'], true),
            'messages' => $messages->map(
                fn (Message $message): array => $this->messagePayload($message, $user)
            )->values()->all(),
        ]);
    }

    public function store(Request $request, Order $order)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        if (!$this->isParticipant($user, $order)) {
            return $this->fail('Huruhusiwi kutuma ujumbe kwenye order hii.', 403);
        }

        if (in_array((string) $order->status, ['completed', 'cancelled'], true)) {
            return $this->fail('Chat imefungwa kwa order hii.', 422);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = $this->conversationForOrder($order);

        $message = Message::query()->create([
            'conversation_id' => (int) $conversation->id,
            'sender_id' => (int) $user->id,
            'body' => trim((string) $data['body']),
        ]);

        $message->load('sender:id,name,phone,role,profile_image_path');
        event(new OrderMessageSent($order, $message));

        $order->loadMissing('provider:id,user_id');
        $recipientUserId = (int) $order->client_id === (int) $user->id
            ? (int) data_get($order, 'provider.user_id', 0)
            : (int) $order->client_id;

        if ($recipientUserId > 0 && $recipientUserId !== (int) $user->id) {
            app(AppNotificationService::class)->sendToUsers(
                [$recipientUserId],
                'order_message',
                'Ujumbe mpya kwenye order ' . (string) ($order->order_no ?? '#' . $order->id),
                Str::limit((string) $message->body, 120),
                [
                    'order_id' => (string) (int) $order->id,
                    'conversation_id' => (string) (int) $conversation->id,
                    'target_screen' => 'order_chat',
                ],
                true
            );
        }

        return $this->ok([
            'order_id' => (int) $order->id,
            'conversation_id' => (int) $conversation->id,
            'message' => $this->messagePayload($message, $user),
        ], 'Ujumbe umetumwa.', 201);
    }

    private function conversationForOrder(Order $order): Conversation
    {
        return Conversation::query()->firstOrCreate([
            'client_id' => (int) $order->client_id,
            'provider_id' => (int) $order->provider_id,
            'order_id' => (int) $order->id,
        ]);
    }

    private function isParticipant(User $user, Order $order): bool
    {
        $order->loadMissing('provider:id,user_id');

        $isClient = (int) $order->client_id === (int) $user->id;
        $isProviderUser = (int) data_get($order, 'provider.user_id', 0) === (int) $user->id;

        return $isClient || $isProviderUser;
    }

    private function messagePayload(Message $message, User $viewer): array
    {
        return [
            'id' => (int) $message->id,
            'body' => (string) ($message->body ?? ''),
            'sender' => [
                'id' => (int) $message->sender_id,
                'name' => (string) data_get($message, 'sender.name', ''),
                'role' => (string) data_get($message, 'sender.role', ''),
                'phone' => (string) data_get($message, 'sender.phone', ''),
                'profile_image_url' => (string) data_get($message, 'sender.profile_image_url', ''),
            ],
            'is_mine' => (int) $message->sender_id === (int) $viewer->id,
            'read_at' => optional($message->read_at)->toIso8601String(),
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];
    }
}
