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

class TransformerMatcherTest extends TestCase
{

    public function test_transforms_path_before_matching(): void
    {
        $transformer = new TransformerMatcher(fn(string $p) => strtolower($p));
        $matcher = $transformer->with(new ExactMatcher('about'));
        $request = $this->createMockRequest();

        $result = $matcher->match('ABOUT', $request);

        $this->assertNotNull($result);
        $this->assertEquals('about', $result->path);
        $this->assertArrayHasKey('original_path', $result->parameters);
        $this->assertEquals('ABOUT', $result->parameters['original_path']);
    }

    public function test_does_not_match_without_child_matcher(): void
    {
        $matcher = new TransformerMatcher(fn(string $p) => strtolower($p));
        $request = $this->createMockRequest();

        $result = $matcher->match('anything', $request);

        $this->assertNull($result);
    }

    public function test_transformer_can_reject_match_by_returning_null(): void
    {
        $transformer = new TransformerMatcher(function(string $p) {
            return str_starts_with($p, 'allowed') ? $p : null;
        });
        $matcher = $transformer->with(new PrefixMatcher('allowed'));
        $request = $this->createMockRequest();

        $allowedResult = $matcher->match('allowed/path', $request);
        $rejectedResult = $matcher->match('blocked/path', $request);

        $this->assertNotNull($allowedResult);
        $this->assertNull($rejectedResult);
    }

    public function test_child_matcher_can_reject_transformed_path(): void
    {
        $transformer = new TransformerMatcher(fn(string $p) => strtolower($p));
        $matcher = $transformer->with(new ExactMatcher('about'));
        $request = $this->createMockRequest();

        $result = $matcher->match('CONTACT', $request);

        $this->assertNull($result);
    }

    public function test_captures_original_path_with_custom_parameter_name(): void
    {
        $transformer = new TransformerMatcher(
            fn(string $p) => strtolower($p),
            'raw_path'
        );
        $matcher = $transformer->with(new ExactMatcher('about'));
        $request = $this->createMockRequest();

        $result = $matcher->match('ABOUT', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('raw_path', $result->parameters);
        $this->assertEquals('ABOUT', $result->parameters['raw_path']);
        $this->assertArrayNotHasKey('original_path', $result->parameters);
    }

    public function test_does_not_capture_original_path_when_disabled(): void
    {
        $transformer = new TransformerMatcher(
            fn(string $p) => strtolower($p),
            null
        );
        $matcher = $transformer->with(new ExactMatcher('about'));
        $request = $this->createMockRequest();

        $result = $matcher->match('ABOUT', $request);

        $this->assertNotNull($result);
        $this->assertEquals([], $result->parameters);
    }

    public function test_preserves_parameters_from_child_matcher(): void
    {
        $transformer = new TransformerMatcher(fn(string $p) => strtolower($p));
        $matcher = $transformer->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('USERS/123', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('123', $result->parameters['id']);
        $this->assertArrayHasKey('original_path', $result->parameters);
        $this->assertEquals('USERS/123', $result->parameters['original_path']);
    }

    public function test_with_can_be_reused_multiple_times(): void
    {
        $lowercase = new TransformerMatcher(fn(string $p) => strtolower($p));
        $request = $this->createMockRequest();

        $aboutMatcher = $lowercase->with(new ExactMatcher('about'));
        $contactMatcher = $lowercase->with(new ExactMatcher('contact'));

        $aboutResult = $aboutMatcher->match('ABOUT', $request);
        $contactResult = $contactMatcher->match('CONTACT', $request);

        $this->assertNotNull($aboutResult);
        $this->assertEquals('about', $aboutResult->path);

        $this->assertNotNull($contactResult);
        $this->assertEquals('contact', $contactResult->path);
    }

    public function test_with_prefix_matcher(): void
    {
        $transformer = new TransformerMatcher(fn(string $p) => strtolower($p));
        $matcher = $transformer->with(new PrefixMatcher('api/'));
        $request = $this->createMockRequest();

        $result = $matcher->match('API/USERS', $request);

        $this->assertNotNull($result);
        $this->assertEquals('api/users', $result->path);
        $this->assertArrayHasKey('prefix_remainder', $result->parameters);
        $this->assertEquals('users', $result->parameters['prefix_remainder']);
        $this->assertArrayHasKey('original_path', $result->parameters);
        $this->assertEquals('API/USERS', $result->parameters['original_path']);
    }

    public function test_with_suffix_matcher(): void
    {
        $transformer = new TransformerMatcher(fn(string $p) => strtolower($p));
        $matcher = $transformer->with(new SuffixMatcher('.json'));
        $request = $this->createMockRequest();

        $result = $matcher->match('USERS.JSON', $request);

        $this->assertNotNull($result);
        $this->assertEquals('users.json', $result->path);
        $this->assertArrayHasKey('suffix_base', $result->parameters);
        $this->assertEquals('users', $result->parameters['suffix_base']);
        $this->assertArrayHasKey('original_path', $result->parameters);
        $this->assertEquals('USERS.JSON', $result->parameters['original_path']);
    }

    public function test_with_catchall_matcher(): void
    {
        $transformer = new TransformerMatcher(fn(string $p) => strtolower($p));
        $matcher = $transformer->with(new CatchallMatcher());
        $request = $this->createMockRequest();

        $result = $matcher->match('ANY/PATH', $request);

        $this->assertNotNull($result);
        $this->assertEquals('any/path', $result->path);
        $this->assertArrayHasKey('original_path', $result->parameters);
        $this->assertEquals('ANY/PATH', $result->parameters['original_path']);
    }

    public function test_removes_query_string_from_path(): void
    {
        $transformer = new TransformerMatcher(
            fn(string $p) => explode('?', $p)[0]
        );
        $matcher = $transformer->with(new ExactMatcher('search'));
        $request = $this->createMockRequest();

        $result = $matcher->match('search?q=test', $request);

        $this->assertNotNull($result);
        $this->assertEquals('search', $result->path);
        $this->assertArrayHasKey('original_path', $result->parameters);
        $this->assertEquals('search?q=test', $result->parameters['original_path']);
    }

    public function test_complex_transformation(): void
    {
        // Remove trailing slashes and lowercase
        $transformer = new TransformerMatcher(
            fn(string $p) => strtolower(rtrim($p, '/'))
        );
        $matcher = $transformer->with(new ExactMatcher('about'));
        $request = $this->createMockRequest();

        $result = $matcher->match('ABOUT///', $request);

        $this->assertNotNull($result);
        $this->assertEquals('about', $result->path);
        $this->assertArrayHasKey('original_path', $result->parameters);
        $this->assertEquals('ABOUT///', $result->parameters['original_path']);
    }

    public function test_transformed_path_is_returned_in_matched_route(): void
    {
        $transformer = new TransformerMatcher(fn(string $p) => 'transformed');
        $matcher = $transformer->with(new ExactMatcher('transformed'));
        $request = $this->createMockRequest();

        $result = $matcher->match('original', $request);

        $this->assertNotNull($result);
        $this->assertEquals('transformed', $result->path);
        $this->assertEquals('original', $result->parameters['original_path']);
    }

    public function test_with_preserves_custom_capture_name(): void
    {
        $transformer = new TransformerMatcher(
            fn(string $p) => strtolower($p),
            'before'
        );
        $matcher = $transformer->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('USERS/456', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('before', $result->parameters);
        $this->assertEquals('USERS/456', $result->parameters['before']);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('456', $result->parameters['id']);
    }

    public function test_with_respects_disabled_capture(): void
    {
        $transformer = new TransformerMatcher(
            fn(string $p) => strtolower($p),
            null
        );
        $matcher = $transformer->with(new PatternMatcher('users/:id'));
        $request = $this->createMockRequest();

        $result = $matcher->match('USERS/789', $request);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result->parameters);
        $this->assertEquals('789', $result->parameters['id']);
        $this->assertArrayNotHasKey('original_path', $result->parameters);
    }

    public function test_url_decode_transformer(): void
    {
        $transformer = new TransformerMatcher(fn(string $p) => urldecode($p));
        $matcher = $transformer->with(new PatternMatcher('search/:query'));
        $request = $this->createMockRequest();

        $result = $matcher->match('search/hello%20world', $request);

        $this->assertNotNull($result);
        $this->assertEquals('search/hello world', $result->path);
        $this->assertArrayHasKey('query', $result->parameters);
        $this->assertEquals('hello world', $result->parameters['query']);
        $this->assertArrayHasKey('original_path', $result->parameters);
        $this->assertEquals('search/hello%20world', $result->parameters['original_path']);
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
