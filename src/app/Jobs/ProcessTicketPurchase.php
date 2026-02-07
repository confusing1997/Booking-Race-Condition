<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Quan trọng: Interface này báo hiệu chạy ngầm
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessTicketPurchase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $ticketId;

    /**
     * Nhận dữ liệu đầu vào
     */
    public function __construct($userId, $ticketId)
    {
        $this->userId = $userId;
        $this->ticketId = $ticketId;
    }

    /**
     * Logic xử lý chính (Worker sẽ chạy cái này)
     */
    public function handle()
    {
        // Vẫn dùng Redis Lock để đảm bảo tính toàn vẹn khi có nhiều Worker cùng chạy
        $lock = Cache::lock("ticket_lock_" . $this->ticketId, 10);

        try {
            // Chờ lấy lock trong 5s
            $lock->block(5);

            $ticket = Ticket::find($this->ticketId);

            if ($ticket && $ticket->quantity > 0) {
                // Giả lập delay xử lý
                usleep(200000); 

                $ticket->decrement('quantity');
                
                Order::create([
                    'user_id' => $this->userId,
                    'ticket_id' => $this->ticketId,
                    'status' => 'success' // Thêm trạng thái đơn hàng
                ]);

                Log::info("User {$this->userId} mua vé thành công via Queue!");
            } else {
                Log::warning("User {$this->userId} mua thất bại: Hết vé.");
            }

        } catch (\Exception $e) {
            Log::error("Lỗi xử lý Queue: " . $e->getMessage());
            // Có thể release job lại vào queue nếu muốn retry
        } finally {
            $lock->release();
        }
    }
}