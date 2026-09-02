<?php

namespace Tests\Feature;

use App\Http\Middleware\SanitizeInput;
use Illuminate\Http\Request;
use Tests\TestCase;

class SanitizeInputTest extends TestCase
{
    public function test_strips_html_tags_from_plain_strings(): void
    {
        $middleware = new SanitizeInput();
        $request = Request::create('/api/test', 'POST', [
            'name' => '<script>alert(1)</script>John',
        ]);

        $response = $middleware->handle($request, fn ($req) => response()->json($req->input()));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('alert(1)John', $request->input('name'));
    }

    public function test_skips_password_fields(): void
    {
        $middleware = new SanitizeInput();
        $request = Request::create('/api/test', 'POST', [
            'password' => '<Pa$$w0rd>',
        ]);

        $middleware->handle($request, fn () => response()->noContent());

        $this->assertSame('<Pa$$w0rd>', $request->input('password'));
    }

    public function test_does_not_touch_uploaded_files(): void
    {
        $middleware = new SanitizeInput();
        $request = Request::create('/api/test', 'POST', [
            'title' => '<b>Title</b>',
        ]);

        $middleware->handle($request, fn () => response()->noContent());

        $this->assertSame('Title', $request->input('title'));
    }
}
