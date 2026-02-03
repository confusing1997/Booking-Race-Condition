<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase; // Tự động xóa sạch DB sau mỗi lần test để đảm bảo môi trường sạch

    protected function setUp(): void
    {
        parent::setUp();
        // Tạo sẵn 1 vé ID=1 với số lượng 10 trước mỗi test
        Ticket::create(['id' => 2, 'name' => 'Concert VIP', 'quantity' => 10]);
        User::create(['id' => 2, 'name' => 'Lwu Hải Nam','email' => 'luuhainam.it@gmail.com', 'password' => Hash::make('12345')]);
      }

    /** @test */
    public function no_lock_purchase_works_normally()
    {
        $response = $this->postJson('/api/buy-no-lock', [
          'ticket_id' => 1,
          'user_id' => 1
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tickets', ['id' => 1, 'quantity' => 9]);
        $this->assertDatabaseCount('orders', 1);
    }

    /** @test */
    public function redis_lock_prevents_access_when_locked()
    {
        // GIẢ LẬP: Có một ai đó đang chiếm giữ khóa 'ticket_lock_1'
        $lock = Cache::lock('ticket_lock_1', 10);
        $lock->acquire(); 

        // Gửi request mua vé có lock
        // Vì lock đang bị chiếm giữ, request này sẽ đứng chờ (block(5)) 
        // Sau 5s không được nó sẽ báo lỗi 429 (Too Many Requests)
        $response = $this->postJson('/api/buy-with-lock', [
          'ticket_id' => 1,
          'user_id' => 1
        ]);

        $response->assertStatus(429); // Kiểm tra xem có trả về lỗi "Hệ thống bận" không
        $response->assertJsonFragment(['message' => 'Hệ thống đang bận, vui lòng thử lại!']);
        
        $lock->release(); // Giải phóng khóa sau khi test xong
    }

    /** @test */
    public function cannot_buy_when_tickets_are_sold_out()
    {
        // Set vé về 0
        Ticket::where('id', 1)->update(['quantity' => 0]);

        $response = $this->postJson('/api/buy-with-lock');

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Hết vé!']);
    }
}