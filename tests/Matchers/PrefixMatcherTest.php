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

class PrefixMatcherTest extends TestCase
{

    public function test_matches_path_with_prefix(): void
    {
        $matcher = new PrefixMatcher('api/');
        $request = $this->createMockRequest();

        $result = $matcher->match('api/users', $request);

        $this->assertNotNull($result);
        $this->assertEquals('api/users', $result->path);
        $this->assertSame($request, $result->request);
        $this->assertArrayHasKey('prefix_remainder', $result->parameters);
        $this->assertEquals('users', $result->parameters['prefix_remainder']);
    }

    public function test_does_not_match_path_without_prefix(): void
    {
        $matcher = new PrefixMatcher('api/');
        $request = $this->createMockRequest();

        $result = $matcher->match('users', $request);

        $this->assertNull($result);
    }

    public function test_matches_exact_prefix(): void
    {
        $matcher = new PrefixMatcher('api');
        $request = $this->createMockRequest();

        $result = $matcher->match('api', $request);

        $this->assertNotNull($result);
        $this->assertEquals('api', $result->path);
        $this->assertArrayHasKey('prefix_remainder', $result->parameters);
        $this->assertEquals('', $result->parameters['prefix_remainder']);
    }

    public function test_matches_prefix_with_multiple_segments(): void
    {
        $matcher = new PrefixMatcher('api/v1/');
        $request = $this->createMockRequest();

        $result = $matcher->match('api/v1/users/123', $request);

        $this->assertNotNull($result);
        $this->assertEquals('api/v1/users/123', $result->path);
    }

    public function test_captures_remainder_when_configured(): void
    {
        $matcher = new PrefixMatcher('api/', 'path');
        $request = $this->createMockRequest();

        $result = $matcher->match('api/users/123', $request);

        $this->assertNotNull($result);
        $this->assertEquals('api/users/123', $result->path);
        $this->assertArrayHasKey('path', $result->parameters);
        $this->assertEquals('users/123', $result->parameters['path']);
    }

    public function test_captures_empty_remainder_when_path_equals_prefix(): void
    {
        $matcher = new PrefixMatcher('api/', 'path');
        $request = $this->createMockRequest();

        $result = $matcher->match('api/', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('path', $result->parameters);
        $this->assertEquals('', $result->parameters['path']);
    }

    public function test_captures_remainder_with_custom_parameter_name(): void
    {
        $matcher = new PrefixMatcher('files/', 'filename');
        $request = $this->createMockRequest();

        $result = $matcher->match('files/document.pdf', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('filename', $result->parameters);
        $this->assertEquals('document.pdf', $result->parameters['filename']);
    }

    public function test_does_not_capture_remainder_when_disabled(): void
    {
        $matcher = new PrefixMatcher('api/', null); // null disables capture
        $request = $this->createMockRequest();

        $result = $matcher->match('api/users/123', $request);

        $this->assertNotNull($result);
        $this->assertEquals([], $result->parameters);
    }

    public function test_matches_root_prefix(): void
    {
        $matcher = new PrefixMatcher('');
        $request = $this->createMockRequest();

        $result = $matcher->match('anything', $request);

        $this->assertNotNull($result);
        $this->assertEquals('anything', $result->path);
    }

    public function test_captures_remainder_with_root_prefix(): void
    {
        $matcher = new PrefixMatcher('', 'path');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('path', $result->parameters);
        $this->assertEquals('users/123', $result->parameters['path']);
    }

    public function test_is_case_sensitive(): void
    {
        $matcher = new PrefixMatcher('API/');
        $request = $this->createMockRequest();

        $result = $matcher->match('api/users', $request);

        $this->assertNull($result);
    }

    public function test_with_composes_child_matcher(): void
    {
        $prefix = new PrefixMatcher('api/');
        $matcher = $prefix->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('api/users/123', $request);

        $this->assertNotNull($result);
        $this->assertEquals('api/users/123', $result->path);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
        $this->assertArrayHasKey('prefix_remainder', $result->parameters);
        $this->assertEquals('users/123', $result->parameters['prefix_remainder']);
    }

    public function test_with_fails_when_child_matcher_fails(): void
    {
        $prefix = new PrefixMatcher('api/');
        $matcher = $prefix->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('api/posts/123', $request);

        $this->assertNull($result);
    }

    public function test_with_fails_when_prefix_fails(): void
    {
        $prefix = new PrefixMatcher('api/');
        $matcher = $prefix->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('admin/users/123', $request);

        $this->assertNull($result);
    }

    public function test_with_can_be_reused_multiple_times(): void
    {
        $apiV1 = new PrefixMatcher('api/v1/');
        $request = $this->createMockRequest();

        $usersMatcher = $apiV1->with(new PatternMatcher('users/:id'));
        $postsMatcher = $apiV1->with(new PatternMatcher('posts/:id'));

        $usersResult = $usersMatcher->match('api/v1/users/123', $request);
        $postsResult = $postsMatcher->match('api/v1/posts/456', $request);

        $this->assertNotNull($usersResult);
        $this->assertEquals('123', $usersResult->parameters['id']);

        $this->assertNotNull($postsResult);
        $this->assertEquals('456', $postsResult->parameters['id']);
    }

    public function test_with_preserves_custom_capture_remainder_name(): void
    {
        $prefix = new PrefixMatcher('api/', 'api_path');
        $matcher = $prefix->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('api/users/123', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('api_path', $result->parameters);
        $this->assertEquals('users/123', $result->parameters['api_path']);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
    }

    public function test_with_respects_disabled_capture_remainder(): void
    {
        $prefix = new PrefixMatcher('api/', null); // disable capture
        $matcher = $prefix->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('api/users/123', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
        $this->assertArrayNotHasKey('prefix_remainder', $result->parameters);
    }

    public function test_with_exact_matcher(): void
    {
        $prefix = new PrefixMatcher('api/');
        $matcher = $prefix->with(new ExactMatcher('users'));
        $request = $this->createMockRequest();

        $result = $matcher->match('api/users', $request);

        $this->assertNotNull($result);
        $this->assertEquals('api/users', $result->path);
        $this->assertArrayHasKey('prefix_remainder', $result->parameters);
        $this->assertEquals('users', $result->parameters['prefix_remainder']);
    }

    public function test_with_suffix_matcher(): void
    {
        $prefix = new PrefixMatcher('api/');
        $matcher = $prefix->with(new SuffixMatcher('.json'));
        $request = $this->createMockRequest();

        $result = $matcher->match('api/users.json', $request);

        $this->assertNotNull($result);
        $this->assertEquals('api/users.json', $result->path);
        $this->assertArrayHasKey('prefix_remainder', $result->parameters);
        $this->assertEquals('users.json', $result->parameters['prefix_remainder']);
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
