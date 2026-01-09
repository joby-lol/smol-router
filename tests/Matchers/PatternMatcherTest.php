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

class PatternMatcherTest extends TestCase
{

    public function test_matches_pattern_with_single_parameter(): void
    {
        $matcher = new PatternMatcher('users/:id');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123', $request);

        $this->assertNotNull($result);
        $this->assertEquals('users/123', $result->path);
        $this->assertSame($request, $result->request);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
    }

    public function test_matches_pattern_with_multiple_parameters(): void
    {
        $matcher = new PatternMatcher('posts/:post_id/comments/:comment_id');
        $request = $this->createMockRequest();

        $result = $matcher->match('posts/456/comments/789', $request);

        $this->assertNotNull($result);
        $this->assertEquals('posts/456/comments/789', $result->path);
        $this->assertArrayHasKey('post_id', $result->parameters);
        $this->assertArrayHasKey('comment_id', $result->parameters);
        $this->assertEquals('456', $result->parameters['post_id']);
        $this->assertEquals('789', $result->parameters['comment_id']);
    }

    public function test_does_not_match_when_path_is_different(): void
    {
        $matcher = new PatternMatcher('users/:id');
        $request = $this->createMockRequest();

        $result = $matcher->match('posts/123', $request);

        $this->assertNull($result);
    }

    public function test_does_not_match_when_segment_count_is_different(): void
    {
        $matcher = new PatternMatcher('users/:id');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123/posts', $request);

        $this->assertNull($result);
    }

    public function test_does_not_match_too_few_segments(): void
    {
        $matcher = new PatternMatcher('users/:id/posts');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123', $request);

        $this->assertNull($result);
    }

    public function test_parameters_do_not_match_slashes(): void
    {
        $matcher = new PatternMatcher('users/:id');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123/posts', $request);

        $this->assertNull($result);
    }

    public function test_matches_parameter_at_start(): void
    {
        $matcher = new PatternMatcher(':username/profile');
        $request = $this->createMockRequest();

        $result = $matcher->match('john/profile', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('username', $result->parameters);
        $this->assertEquals('john', $result->parameters['username']);
    }

    public function test_matches_parameter_at_end(): void
    {
        $matcher = new PatternMatcher('users/:id');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
    }

    public function test_matches_multiple_consecutive_static_segments(): void
    {
        $matcher = new PatternMatcher('/api/v1users/:id');
        $request = $this->createMockRequest();

        $result = $matcher->match('/api/v1users/123', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
    }

    public function test_matches_parameter_with_underscores_in_name(): void
    {
        $matcher = new PatternMatcher('items/:item_id');
        $request = $this->createMockRequest();

        $result = $matcher->match('items/abc123', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('item_id', $result->parameters);
        $this->assertEquals('abc123', $result->parameters['item_id']);
    }

    public function test_matches_parameter_with_numbers_in_name(): void
    {
        $matcher = new PatternMatcher('items/:id2');
        $request = $this->createMockRequest();

        $result = $matcher->match('items/xyz', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id2', $result->parameters);
        $this->assertEquals('xyz', $result->parameters['id2']);
    }

    public function test_matches_parameter_with_alphanumeric_value(): void
    {
        $matcher = new PatternMatcher('users/:id');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/abc123xyz', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('abc123xyz', $result->parameters['id']);
    }

    public function test_matches_parameter_with_hyphen_in_value(): void
    {
        $matcher = new PatternMatcher('users/:slug');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/john-doe', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('slug', $result->parameters);
        $this->assertEquals('john-doe', $result->parameters['slug']);
    }

    public function test_matches_pattern_with_special_characters_in_static_part(): void
    {
        $matcher = new PatternMatcher('items/:id.json');
        $request = $this->createMockRequest();

        $result = $matcher->match('items/123.json', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
    }

    public function test_does_not_match_when_static_part_is_different(): void
    {
        $matcher = new PatternMatcher('items/:id.json');
        $request = $this->createMockRequest();

        $result = $matcher->match('items/123.xml', $request);

        $this->assertNull($result);
    }

    public function test_matches_empty_parameter_value(): void
    {
        $matcher = new PatternMatcher(':category/items');
        $request = $this->createMockRequest();

        // Empty values can't be matched because parameters require at least one non-slash character
        $result = $matcher->match('/items', $request);

        $this->assertNull($result);
    }

    public function test_matches_three_parameters(): void
    {
        $matcher = new PatternMatcher(':category/:subcategory/:item');
        $request = $this->createMockRequest();

        $result = $matcher->match('electronics/phones/iphone', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('category', $result->parameters);
        $this->assertArrayHasKey('subcategory', $result->parameters);
        $this->assertArrayHasKey('item', $result->parameters);
        $this->assertEquals('electronics', $result->parameters['category']);
        $this->assertEquals('phones', $result->parameters['subcategory']);
        $this->assertEquals('iphone', $result->parameters['item']);
    }

    public function test_is_case_sensitive_for_static_segments(): void
    {
        $matcher = new PatternMatcher('Users/:id');
        $request = $this->createMockRequest();

        $result = $matcher->match('users/123', $request);

        $this->assertNull($result);
    }

    public function test_matches_pattern_with_parentheses_in_static_part(): void
    {
        $matcher = new PatternMatcher('items(new)/:id');
        $request = $this->createMockRequest();

        $result = $matcher->match('items(new)/123', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
    }

    public function test_matches_pattern_with_brackets_in_static_part(): void
    {
        $matcher = new PatternMatcher('items[test]/:id');
        $request = $this->createMockRequest();

        $result = $matcher->match('items[test]/123', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
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
