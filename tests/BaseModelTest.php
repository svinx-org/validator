<?php

namespace Svinx\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Svinx\Validator\Examples\Models\Post;
use Svinx\Validator\Examples\Models\User;

class BaseModelTest extends TestCase
{
    public function testUserValidation(): void
    {
        $userData = [
            'user_id' => 42,
            'full_name' => 'Jean Dupont',
            'role' => 'admin',
        ];

        $user = new User($userData);

        $this->assertSame(42, $user->id);
        $this->assertSame('Jean Dupont', $user->name);
        $this->assertSame('admin', $user->role);
        $this->assertNull($user->birthday);
    }

    public function testNestedModel(): void
    {
        $userData = [
            'user_id' => 1,
            'full_name' => 'Alice',
            'role' => 'user',
        ];

        $postData = [
            'title' => 'Mon premier article',
            'content' => 'Lorem ipsum...',
            'author' => $userData,
        ];

        $post = new Post($postData);

        $this->assertInstanceOf(User::class, $post->author);
        $this->assertSame('Mon premier article', $post->title);
        $this->assertSame('Alice', $post->author->name);
    }

    public function testModelDump(): void
    {
        $user = new User([
            'user_id' => 10,
            'full_name' => 'Bob',
            'role' => 'guest',
        ]);

        $dumped = $user->model_dump();
        $this->assertArrayHasKey('user_id', $dumped);
        $this->assertArrayHasKey('full_name', $dumped);
        $this->assertArrayHasKey('role', $dumped);
        $this->assertArrayHasKey('birth_date', $dumped);
        $this->assertSame(10, $dumped['user_id']);
        $this->assertSame('Bob', $dumped['full_name']);
        $this->assertSame('guest', $dumped['role']);
        $this->assertNull($dumped['birth_date']);
    }
}