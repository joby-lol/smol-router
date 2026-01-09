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

class ExactMatcherTest extends TestCase
{

    public function test_matches_exact_path(): void
    {
        $matcher = new ExactMatcher('about');
        $request = $this->createMockRequest();

        $result = $matcher->match('about', $request);

        $this->assertNotNull($result);
        $this->assertEquals('about', $result->path);
        $this->assertSame($request, $result->request);
        $this->assertEquals([], $result->parameters);
    }

    public function test_does_not_match_different_path(): void
    {
        $matcher = new ExactMatcher('about');
        $request = $this->createMockRequest();

        $result = $matcher->match('contact', $request);

        $this->assertNull($result);
    }

    public function test_does_not_match_prefix(): void
    {
        $matcher = new ExactMatcher('about');
        $request = $this->createMockRequest();

        $result = $matcher->match('about/team', $request);

        $this->assertNull($result);
    }

    public function test_does_not_match_partial(): void
    {
        $matcher = new ExactMatcher('about');
        $request = $this->createMockRequest();

        $result = $matcher->match('abou', $request);

        $this->assertNull($result);
    }

    public function test_matches_root_path(): void
    {
        $matcher = new ExactMatcher('');
        $request = $this->createMockRequest();

        $result = $matcher->match('', $request);

        $this->assertNotNull($result);
        $this->assertEquals('', $result->path);
    }

    public function test_does_not_match_root_when_configured_for_different_path(): void
    {
        $matcher = new ExactMatcher('home');
        $request = $this->createMockRequest();

        $result = $matcher->match('', $request);

        $this->assertNull($result);
    }

    public function test_matches_complex_path(): void
    {
        $matcher = new ExactMatcher('users/123/posts/456');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123/posts/456', $request);

        $this->assertNotNull($result);
        $this->assertEquals('users/123/posts/456', $result->path);
        $this->assertEquals([], $result->parameters);
    }

    public function test_is_case_sensitive(): void
    {
        $matcher = new ExactMatcher('About');
        $request = $this->createMockRequest();

        $result = $matcher->match('about', $request);

        $this->assertNull($result);
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
