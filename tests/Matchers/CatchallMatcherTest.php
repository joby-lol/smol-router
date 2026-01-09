<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router\Matchers;

use Joby\Smol\Request\Cookies\Cookies;
use Joby\Smol\Request\Headers\Headers;
use Joby\Smol\Request\Method;
use Joby\Smol\Request\Post\Post;
use Joby\Smol\Request\Request;
use Joby\Smol\Request\Source\Source;
use Joby\Smol\URL\URL;
use PHPUnit\Framework\TestCase;

class CatchallMatcherTest extends TestCase
{

    public function test_matches_any_path(): void
    {
        $matcher = new CatchallMatcher();
        $request = $this->createMockRequest();

        $result = $matcher->match('any/path', $request);

        $this->assertNotNull($result);
        $this->assertEquals('any/path', $result->path);
        $this->assertSame($request, $result->request);
        $this->assertEquals([], $result->parameters);
    }

    public function test_matches_root_path(): void
    {
        $matcher = new CatchallMatcher();
        $request = $this->createMockRequest();

        $result = $matcher->match('', $request);

        $this->assertNotNull($result);
        $this->assertEquals('', $result->path);
        $this->assertSame($request, $result->request);
        $this->assertEquals([], $result->parameters);
    }

    public function test_matches_empty_path(): void
    {
        $matcher = new CatchallMatcher();
        $request = $this->createMockRequest();

        $result = $matcher->match('', $request);

        $this->assertNotNull($result);
        $this->assertEquals('', $result->path);
        $this->assertSame($request, $result->request);
        $this->assertEquals([], $result->parameters);
    }

    public function test_matches_complex_path(): void
    {
        $matcher = new CatchallMatcher();
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123/posts/456/comments', $request);

        $this->assertNotNull($result);
        $this->assertEquals('users/123/posts/456/comments', $result->path);
        $this->assertSame($request, $result->request);
        $this->assertEquals([], $result->parameters);
    }

    private function createMockRequest(): Request
    {
        return new Request(
            $this->createStub(URL::class),
            Method::GET,
            $this->createStub(Headers::class),
            $this->createStub(Cookies::class),
            $this->createStub(Post::class),
            $this->createStub(Source::class),
        );
    }

}
