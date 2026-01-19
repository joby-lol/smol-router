<?php

namespace Joby\Smol\Router\Matchers;

use Joby\Smol\Request\Cookies\Cookies;
use Joby\Smol\Request\Headers\Headers;
use Joby\Smol\Request\Method;
use Joby\Smol\Request\Post\Post;
use Joby\Smol\Request\Request;
use Joby\Smol\Request\Source\Source;
use Joby\Smol\URL\Path;
use Joby\Smol\URL\URL;
use PHPUnit\Framework\TestCase;

class SuffixPatternMatcherTest extends TestCase
{

    public function test_matches_single_parameter_suffix(): void
    {
        $matcher = new SuffixPatternMatcher('/api/:tenant');
        $request = $this->createRequest('users/api/acme');

        $match = $matcher->match('users/api/acme', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertEquals('users', $match->parameters['suffix_remainder']);
    }

    public function test_matches_multiple_parameter_suffix(): void
    {
        $matcher = new SuffixPatternMatcher('/api/v:version/:lang');
        $request = $this->createRequest('users/api/v2/en');

        $match = $matcher->match('users/api/v2/en', $request);

        $this->assertNotNull($match);
        $this->assertEquals('2', $match->parameters['version']);
        $this->assertEquals('en', $match->parameters['lang']);
        $this->assertEquals('users', $match->parameters['suffix_remainder']);
    }

    public function test_does_not_match_different_suffix(): void
    {
        $matcher = new SuffixPatternMatcher('/api/:tenant');
        $request = $this->createRequest('users/web/acme');

        $match = $matcher->match('users/web/acme', $request);

        $this->assertNull($match);
    }

    public function test_custom_remainder_parameter_name(): void
    {
        $matcher = new SuffixPatternMatcher('/api/:tenant', 'path');
        $request = $this->createRequest('users/123/api/acme');

        $match = $matcher->match('users/123/api/acme', $request);

        $this->assertNotNull($match);
        $this->assertEquals('users/123', $match->parameters['path']);
        $this->assertArrayNotHasKey('suffix_remainder', $match->parameters);
    }

    public function test_disable_remainder_capture(): void
    {
        $matcher = new SuffixPatternMatcher('/api/:tenant', null);
        $request = $this->createRequest('users/api/acme');

        $match = $matcher->match('users/api/acme', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertArrayNotHasKey('suffix_remainder', $match->parameters);
    }

    public function test_composition_with_pattern_matcher(): void
    {
        $matcher = new SuffixPatternMatcher('/api/:tenant');
        $composed = $matcher->with(new PatternMatcher('users/:id'));
        $request = $this->createRequest('users/123/api/acme');

        $match = $composed->match('users/123/api/acme', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertEquals('123', $match->parameters['id']);
        $this->assertEquals('users/123', $match->parameters['suffix_remainder']);
    }

    public function test_composition_with_exact_matcher(): void
    {
        $matcher = new SuffixPatternMatcher('/api/:tenant');
        $composed = $matcher->with(new ExactMatcher('status'));
        $request = $this->createRequest('status/api/acme');

        $match = $composed->match('status/api/acme', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
    }

    public function test_composition_fails_when_child_does_not_match(): void
    {
        $matcher = new SuffixPatternMatcher('/api/:tenant');
        $composed = $matcher->with(new ExactMatcher('status'));
        $request = $this->createRequest('users/api/acme');

        $match = $composed->match('users/api/acme', $request);

        $this->assertNull($match);
    }

    public function test_nested_composition(): void
    {
        $tenant = new SuffixPatternMatcher('/:tenant');
        $versioned = $tenant->with(new SuffixPatternMatcher('/api/v:version'));
        $composed = $versioned->with(new PatternMatcher('users/:id'));

        $request = $this->createRequest('users/123/api/v2/acme');
        $match = $composed->match('users/123/api/v2/acme', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertEquals('2', $match->parameters['version']);
        $this->assertEquals('123', $match->parameters['id']);
    }

    public function test_without_leading_slash_matches_adjacent_text(): void
    {
        $matcher = new SuffixPatternMatcher(':tenant');
        $request = $this->createRequest('users/apiacme');

        $match = $matcher->match('users/apiacme', $request);

        $this->assertNotNull($match);
        $this->assertEquals('apiacme', $match->parameters['tenant']);
    }

    public function test_with_leading_slash_requires_slash(): void
    {
        $matcher = new SuffixPatternMatcher('/:tenant');
        $request = $this->createRequest('usersapiacme');

        $match = $matcher->match('usersapiacme', $request);
        $this->assertNull($match);

        $match = $matcher->match('users/apiacme', $request);
        $this->assertNotNull($match);
    }

    public function test_matches_empty_remainder(): void
    {
        $matcher = new SuffixPatternMatcher('/api/:tenant');
        $request = $this->createRequest('/api/acme');

        $match = $matcher->match('/api/acme', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertEquals('', $match->parameters['suffix_remainder']);
    }

    public function test_parameter_names_with_underscores(): void
    {
        $matcher = new SuffixPatternMatcher('/:api_version/api/:tenant_id');
        $request = $this->createRequest('users/v2/api/acme123');

        $match = $matcher->match('users/v2/api/acme123', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme123', $match->parameters['tenant_id']);
        $this->assertEquals('v2', $match->parameters['api_version']);
    }

    public function test_reusable_matcher_composition(): void
    {
        $tenant = new SuffixPatternMatcher('/api/:tenant');
        $request = $this->createRequest('users/123/api/acme');

        $users = $tenant->with(new PatternMatcher('users/:id'));
        $posts = $tenant->with(new PatternMatcher('posts/:id'));

        $userMatch = $users->match('users/123/api/acme', $request);
        $postMatch = $posts->match('posts/456/api/acme', $request);

        $this->assertNotNull($userMatch);
        $this->assertEquals('123', $userMatch->parameters['id']);

        $this->assertNotNull($postMatch);
        $this->assertEquals('456', $postMatch->parameters['id']);
    }

    public function test_composition_preserves_all_parameters(): void
    {
        $matcher = new SuffixPatternMatcher('/api/:subdomain/:tenant', 'api_path');
        $composed = $matcher->with(new PatternMatcher(':resource/:id'));
        $request = $this->createRequest('users/123/api/admin/acme');

        $match = $composed->match('users/123/api/admin/acme', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertEquals('admin', $match->parameters['subdomain']);
        $this->assertEquals('users', $match->parameters['resource']);
        $this->assertEquals('123', $match->parameters['id']);
        $this->assertEquals('users/123', $match->parameters['api_path']);
    }

    private function createRequest(string $path): Request
    {
        $url = new URL(Path::fromString($path));

        return new Request(
            $url,
            Method::GET,
            $this->createStub(Headers::class),
            $this->createStub(Cookies::class),
            $this->createStub(Post::class),
            $this->createStub(Source::class),
        );
    }

}
