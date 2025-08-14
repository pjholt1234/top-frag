<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_user()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $user = new User;

        $expectedFillable = ['name', 'email', 'password'];
        $this->assertEquals($expectedFillable, $user->getFillable());
    }

    #[Test]
    public function it_has_hidden_attributes()
    {
        $user = new User;

        $expectedHidden = ['password', 'remember_token'];
        $this->assertEquals($expectedHidden, $user->getHidden());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $user = User::factory()->create([
            'email_verified_at' => '2023-01-01 12:00:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $user->email_verified_at);
        $this->assertEquals('2023-01-01 12:00:00', $user->email_verified_at->toDateTimeString());
    }

    #[Test]
    public function it_hashes_password_when_set()
    {
        $user = User::factory()->create([
            'password' => 'plaintext_password',
        ]);

        $this->assertNotEquals('plaintext_password', $user->password);
        $this->assertTrue(Hash::check('plaintext_password', $user->password));
    }

    #[Test]
    public function it_can_be_created_with_factory()
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    #[Test]
    public function it_has_required_traits()
    {
        $user = new User;

        $this->assertTrue(method_exists($user, 'notify'));
        $this->assertTrue(method_exists($user, 'routeNotificationFor'));
    }
}
