<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    // CÁCH 1: KHÔNG DÙNG LOCK (Sẽ bị lỗi Overselling khi tải cao)
    public function orderWithoutLock(Request $request)
    {
        $ticketId = 1; // Giả sử mua loại vé ID = 1
        $userId = 1; // Giả lập user ID ngẫu nhiên

        // 1. Kiểm tra tồn kho
        $ticket = Ticket::find($ticketId);

        if ($ticket->quantity > 0) {
            // Giả lập độ trễ hệ thống (Latency) để dễ xảy ra Race Condition hơn
            // Trong thực tế, độ trễ này là thời gian xử lý thanh toán, gửi mail...
            usleep(200000); // Ngủ 0.2 giây

            // 2. Trừ tồn kho
            $ticket->decrement('quantity');

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
        $ticketId = 1;
        $userId = 1;

        // Tạo một cái "khóa" tên là ticket_1, chờ tối đa 10s để lấy khóa
        // block(5): Nếu ai đó đang giữ khóa, tôi sẽ đứng chờ 5s. 
        // Sau 5s vẫn không lấy được thì bỏ cuộc.
        $lock = \Illuminate\Support\Facades\Cache::lock('ticket_lock_' . $ticketId, 10);

        try {
            // Cố gắng lấy lock
            $lock->block(5);
            
            // Vẫn giả lập độ trễ để chứng minh Lock hoạt động tốt
            usleep(200000); 
            
            DB::transaction(function () use ($ticketId, $userId, $lock) {
              $ticket = Ticket::lockForUpdate()->find($ticketId);
              if ($ticket->quantity > 0) {
                $ticket->decrement('quantity');
                Order::create([
                    'user_id' => $userId,
                    'ticket_id' => $ticketId
                ]);
              } else {
                $lock->release(); // Hết vé cũng phải mở khóa
                return response()->json(['message' => 'Hết vé!'], 400);
              }

            });

            // Mở khóa ngay khi xong việc để người khác vào
            $lock->release();

            return response()->json(['message' => 'Mua thành công (Locked)!']);            

            // --- KẾT THÚC VÙNG AN TOÀN ---

        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            // Chờ 5s mà vẫn không đến lượt -> Server quá tải
            return response()->json(['message' => 'Hệ thống đang bận, vui lòng thử lại!'], 429);
        }
    }
}