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
use Joby\Smol\URL\Path;
use Joby\Smol\URL\URL;
use PHPUnit\Framework\TestCase;

class PrefixPatternMatcherTest extends TestCase
{

    public function test_matches_single_parameter_prefix(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant/api/');
        $request = $this->createRequest('acme/api/users');

        $match = $matcher->match('acme/api/users', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertEquals('users', $match->parameters['prefix_remainder']);
    }

    public function test_matches_multiple_parameter_prefix(): void
    {
        $matcher = new PrefixPatternMatcher(':lang/v:version/api/');
        $request = $this->createRequest('/en/v2/api/users');

        $match = $matcher->match('en/v2/api/users', $request);

        $this->assertNotNull($match);
        $this->assertEquals('en', $match->parameters['lang']);
        $this->assertEquals('2', $match->parameters['version']);
        $this->assertEquals('users', $match->parameters['prefix_remainder']);
    }

    public function test_does_not_match_different_prefix(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant/api/');
        $request = $this->createRequest('/acme/web/users');

        $match = $matcher->match('acme/web/users', $request);

        $this->assertNull($match);
    }

    public function test_custom_remainder_parameter_name(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant/api/', 'path');
        $request = $this->createRequest('/acme/api/users/123');

        $match = $matcher->match('acme/api/users/123', $request);

        $this->assertNotNull($match);
        $this->assertEquals('users/123', $match->parameters['path']);
        $this->assertArrayNotHasKey('prefix_remainder', $match->parameters);
    }

    public function test_disable_remainder_capture(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant/api/', null);
        $request = $this->createRequest('/acme/api/users');

        $match = $matcher->match('acme/api/users', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertArrayNotHasKey('prefix_remainder', $match->parameters);
    }

    public function test_composition_with_pattern_matcher(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant/api/');
        $composed = $matcher->with(new PatternMatcher('users/:id'));
        $request = $this->createRequest('/acme/api/users/123');

        $match = $composed->match('acme/api/users/123', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertEquals('123', $match->parameters['id']);
        $this->assertEquals('users/123', $match->parameters['prefix_remainder']);
    }

    public function test_composition_with_exact_matcher(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant/api/');
        $composed = $matcher->with(new ExactMatcher('status'));
        $request = $this->createRequest('/acme/api/status');

        $match = $composed->match('acme/api/status', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
    }

    public function test_composition_fails_when_child_does_not_match(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant/api/');
        $composed = $matcher->with(new ExactMatcher('status'));
        $request = $this->createRequest('/acme/api/users');

        $match = $composed->match('acme/api/users', $request);

        $this->assertNull($match);
    }

    public function test_nested_composition(): void
    {
        $tenant = new PrefixPatternMatcher(':tenant/');
        $versioned = $tenant->with(new PrefixPatternMatcher('api/v:version/'));
        $composed = $versioned->with(new PatternMatcher('users/:id'));

        $request = $this->createRequest('/acme/api/v2/users/123');
        $match = $composed->match('acme/api/v2/users/123', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertEquals('2', $match->parameters['version']);
        $this->assertEquals('123', $match->parameters['id']);
    }

    public function test_without_trailing_slash_matches_adjacent_text(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant');
        $request = $this->createRequest('/acmeapi/users');

        $match = $matcher->match('acmeapi/users', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acmeapi', $match->parameters['tenant']);

    }

    public function test_with_trailing_slash_requires_slash(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant/');
        $request = $this->createRequest('/acmeapi/users');

        $match = $matcher->match('acmeapiusers', $request);
        $this->assertNull($match);

        $match = $matcher->match('acmeapi/users', $request);
        $this->assertNotNull($match);
    }

    public function test_matches_empty_remainder(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant/api/');
        $request = $this->createRequest('/acme/api/');

        $match = $matcher->match('acme/api/', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme', $match->parameters['tenant']);
        $this->assertEquals('', $match->parameters['prefix_remainder']);
    }

    public function test_parameter_names_with_underscores(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant_id/api/:api_version/');
        $request = $this->createRequest('/acme123/api/v2/users');

        $match = $matcher->match('acme123/api/v2/users', $request);

        $this->assertNotNull($match);
        $this->assertEquals('acme123', $match->parameters['tenant_id']);
        $this->assertEquals('v2', $match->parameters['api_version']);
    }

    public function test_reusable_matcher_composition(): void
    {
        $tenant = new PrefixPatternMatcher(':tenant/api/');
        $request = $this->createRequest('/acme/api/users/123');

        $users = $tenant->with(new PatternMatcher('users/:id'));
        $posts = $tenant->with(new PatternMatcher('posts/:id'));

        $userMatch = $users->match('acme/api/users/123', $request);
        $postMatch = $posts->match('acme/api/posts/456', $request);

        $this->assertNotNull($userMatch);
        $this->assertEquals('123', $userMatch->parameters['id']);

        $this->assertNotNull($postMatch);
        $this->assertEquals('456', $postMatch->parameters['id']);
    }

    public function test_composition_preserves_all_parameters(): void
    {
        $matcher = new PrefixPatternMatcher(':tenant/:subdomain/api/', 'api_path');
        $composed = $matcher->with(new PatternMatcher(':resource/:id'));
        $request = $this->createRequest('/acme/admin/api/users/123');

        $match = $composed->match('acme/admin/api/users/123', $request);

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
