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

class SuffixMatcherTest extends TestCase
{

    public function test_matches_path_with_suffix(): void
    {
        $matcher = new SuffixMatcher('.json');
        $request = $this->createMockRequest();

        $result = $matcher->match('users.json', $request);

        $this->assertNotNull($result);
        $this->assertEquals('users.json', $result->path);
        $this->assertSame($request, $result->request);
        $this->assertArrayHasKey('suffix_base', $result->parameters);
        $this->assertEquals('users', $result->parameters['suffix_base']);
    }

    public function test_does_not_match_path_without_suffix(): void
    {
        $matcher = new SuffixMatcher('.json');
        $request = $this->createMockRequest();

        $result = $matcher->match('users', $request);

        $this->assertNull($result);
    }

    public function test_matches_exact_suffix(): void
    {
        $matcher = new SuffixMatcher('.json');
        $request = $this->createMockRequest();

        $result = $matcher->match('.json', $request);

        $this->assertNotNull($result);
        $this->assertEquals('.json', $result->path);
        $this->assertArrayHasKey('suffix_base', $result->parameters);
        $this->assertEquals('', $result->parameters['suffix_base']);
    }

    public function test_matches_suffix_with_multiple_segments(): void
    {
        $matcher = new SuffixMatcher('.json');
        $request = $this->createMockRequest();

        $result = $matcher->match('/apiusers/123.json', $request);

        $this->assertNotNull($result);
        $this->assertEquals('/apiusers/123.json', $result->path);
    }

    public function test_captures_base_when_configured(): void
    {
        $matcher = new SuffixMatcher('.json', 'path');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123.json', $request);

        $this->assertNotNull($result);
        $this->assertEquals('users/123.json', $result->path);
        $this->assertArrayHasKey('path', $result->parameters);
        $this->assertEquals('users/123', $result->parameters['path']);
    }

    public function test_captures_empty_base_when_path_equals_suffix(): void
    {
        $matcher = new SuffixMatcher('.json', 'path');
        $request = $this->createMockRequest();

        $result = $matcher->match('.json', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('path', $result->parameters);
        $this->assertEquals('', $result->parameters['path']);
    }

    public function test_captures_base_with_custom_parameter_name(): void
    {
        $matcher = new SuffixMatcher('.xml', 'resource');
        $request = $this->createMockRequest();

        $result = $matcher->match('api/data.xml', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('resource', $result->parameters);
        $this->assertEquals('api/data', $result->parameters['resource']);
    }

    public function test_does_not_capture_base_when_disabled(): void
    {
        $matcher = new SuffixMatcher('.json', null); // null disables capture
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123.json', $request);

        $this->assertNotNull($result);
        $this->assertEquals([], $result->parameters);
    }

    public function test_matches_multiple_extension_suffix(): void
    {
        $matcher = new SuffixMatcher('.tar.gz');
        $request = $this->createMockRequest();

        $result = $matcher->match('archive.tar.gz', $request);

        $this->assertNotNull($result);
        $this->assertEquals('archive.tar.gz', $result->path);
    }

    public function test_captures_base_with_multiple_extension_suffix(): void
    {
        $matcher = new SuffixMatcher('.tar.gz', 'filename');
        $request = $this->createMockRequest();

        $result = $matcher->match('downloads/archive.tar.gz', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('filename', $result->parameters);
        $this->assertEquals('downloads/archive', $result->parameters['filename']);
    }

    public function test_does_not_match_partial_suffix(): void
    {
        $matcher = new SuffixMatcher('.json');
        $request = $this->createMockRequest();

        $result = $matcher->match('users.jsonl', $request);

        $this->assertNull($result);
    }

    public function test_is_case_sensitive(): void
    {
        $matcher = new SuffixMatcher('.JSON');
        $request = $this->createMockRequest();

        $result = $matcher->match('users.json', $request);

        $this->assertNull($result);
    }

    public function test_with_composes_child_matcher(): void
    {
        $json = new SuffixMatcher('.json');
        $matcher = $json->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123.json', $request);

        $this->assertNotNull($result);
        $this->assertEquals('users/123.json', $result->path);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
        $this->assertArrayHasKey('suffix_base', $result->parameters);
        $this->assertEquals('users/123', $result->parameters['suffix_base']);
    }

    public function test_with_fails_when_child_matcher_fails(): void
    {
        $json = new SuffixMatcher('.json');
        $matcher = $json->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('posts/123.json', $request);

        $this->assertNull($result);
    }

    public function test_with_fails_when_suffix_fails(): void
    {
        $json = new SuffixMatcher('.json');
        $matcher = $json->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123.xml', $request);

        $this->assertNull($result);
    }

    public function test_with_can_be_reused_multiple_times(): void
    {
        $json = new SuffixMatcher('.json');
        $request = $this->createMockRequest();

        $usersMatcher = $json->with(new PatternMatcher('users/:id'));
        $postsMatcher = $json->with(new PatternMatcher('posts/:id'));

        $usersResult = $usersMatcher->match('users/123.json', $request);
        $postsResult = $postsMatcher->match('posts/456.json', $request);

        $this->assertNotNull($usersResult);
        $this->assertEquals('123', $usersResult->parameters['id']);

        $this->assertNotNull($postsResult);
        $this->assertEquals('456', $postsResult->parameters['id']);
    }

    public function test_with_preserves_custom_capture_base_name(): void
    {
        $json = new SuffixMatcher('.json', 'resource');
        $matcher = $json->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123.json', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('resource', $result->parameters);
        $this->assertEquals('users/123', $result->parameters['resource']);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
    }

    public function test_with_respects_disabled_capture_base(): void
    {
        $json = new SuffixMatcher('.json', null); // disable capture
        $matcher = $json->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123.json', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
        $this->assertArrayNotHasKey('suffix_base', $result->parameters);
    }

    public function test_with_exact_matcher(): void
    {
        $json = new SuffixMatcher('.json');
        $matcher = $json->with(new ExactMatcher('users'));
        $request = $this->createMockRequest();

        $result = $matcher->match('users.json', $request);

        $this->assertNotNull($result);
        $this->assertEquals('users.json', $result->path);
        $this->assertArrayHasKey('suffix_base', $result->parameters);
        $this->assertEquals('users', $result->parameters['suffix_base']);
    }

    public function test_with_prefix_matcher(): void
    {
        $json = new SuffixMatcher('.json');
        $matcher = $json->with(new PrefixMatcher('api/'));
        $request = $this->createMockRequest();

        $result = $matcher->match('api/users.json', $request);

        $this->assertNotNull($result);
        $this->assertEquals('api/users.json', $result->path);
        $this->assertArrayHasKey('suffix_base', $result->parameters);
        $this->assertEquals('api/users', $result->parameters['suffix_base']);
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
