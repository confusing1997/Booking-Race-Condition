<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Ticket;
use Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    // Thêm vào TicketController.php
    public function getStatus() {
        $ticket = Ticket::find(1);
        return response()->json(['quantity' => $ticket->quantity]);
    }

    // CÁCH 1: KHÔNG DÙNG LOCK (Sẽ bị lỗi Overselling khi tải cao)
    public function orderWithoutLock(Request $request)
    {
        $ticketId = $request->input('ticket_id', 1); // Giả sử mua loại vé ID = 1
        $userId = $request->input('user_id', 1); // Giả lập user ID ngẫu nhiên

        // 1. Kiểm tra tồn kho
        $ticket = Ticket::find($ticketId);

        if ($ticket->quantity > 0) {
            // Giả lập độ trễ hệ thống (Latency) để dễ xảy ra Race Condition hơn
            // Trong thực tế, độ trễ này là thời gian xử lý thanh toán, gửi mail...
            usleep(300000); // Ngủ 0.2 giây

            // 2. Trừ tồn kho
            $newQuantity = $ticket->quantity - 1; // Tính toán bằng PHP thay vì SQL
            $ticket->update(['quantity' => $newQuantity]);

            // 3. Tạo đơn hàng
            Order::create([
                'user_id' => $userId,
                'ticket_id' => $ticketId
            ]);

            return response()->json(['message' => 'Mua thành công!']);
        }

        return response()->json(['message' => 'Hết vé!'], 400);
    }


    // CÁCH 2: SỬ DỤNG REDIS LOCK (An toàn tuyệt đối)
    public function orderWithRedisLock(Request $request)
    {
        // 1. Lấy ticket_id từ request, mặc định là 1 nếu không truyền (để demo vẫn chạy)
        $ticketId = $request->input('ticket_id', 1);
        $userId = 1; // Giả sử user đã login

        $lock = Cache::lock("ticket_lock_" . $ticketId, 10);

        try {
            // Chờ tối đa 5 giây để lấy khóa
            $lock->block(5);

            $ticket = Ticket::find($ticketId);

            // Kiểm tra xem vé có tồn tại không
            if (!$ticket) {
                return response()->json(['message' => 'Vé không tồn tại!'], 404);
            }

            if ($ticket->quantity > 0) {
                // Giả lập xử lý nặng
                usleep(200000); 

                $ticket->decrement('quantity');
                Order::create([
                    'user_id' => $userId,
                    'ticket_id' => $ticketId,
                ]);

                return response()->json(['message' => 'Mua vé thành công!']);
            }

            return response()->json(['message' => 'Hết vé!'], 400);

        } catch (LockTimeoutException $e) {
            return response()->json(['message' => 'Hệ thống đang bận, vui lòng thử lại!'], 429);
        } finally {
            $lock->release();
        }
    }
}