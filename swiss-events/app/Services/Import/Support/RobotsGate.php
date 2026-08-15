<?php

namespace App\Services\Import\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Decides whether a URL may be fetched, according to the host's robots.txt.
 *
 * This site imports from third parties on a nightly schedule, so it is a
 * crawler whether or not it feels like one, and it identifies itself as such.
 * The gate applies to connectors that fetch arbitrary web pages; a documented
 * API accessed with an issued key is a different relationship, not something
 * robots.txt governs.
 *
 * Deliberately fails *open*: a missing, unreachable or unparseable robots.txt
 * means "no stated restrictions", which is what the standard implies. Only an
 * explicit Disallow blocks a fetch.
 */
class RobotsGate
{
    public const USER_AGENT = 'SwissEventsBot';

    /**
     * Parsed rules per host origin, so one import run fetches each robots.txt
     * once no matter how many pages it reads.
     *
     * @var array<string, list<array{allow: bool, path: string}>>
     */
    private array $rules = [];

    public function allows(string $url, string $userAgent = self::USER_AGENT): bool
    {
        $origin = $this->origin($url);

        if ($origin === null) {
            return true;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);
        $target = $query !== null ? "{$path}?{$query}" : $path;

        $match = null;

        foreach ($this->rulesFor($origin, $userAgent) as $rule) {
            if (! $this->pathMatches($target, $rule['path'])) {
                continue;
            }

            // Longest matching rule wins; Allow beats Disallow at equal length,
            // which is how the major crawlers resolve the ambiguity.
            if ($match === null
                || strlen($rule['path']) > strlen($match['path'])
                || (strlen($rule['path']) === strlen($match['path']) && $rule['allow'])) {
                $match = $rule;
            }
        }

        return $match === null || $match['allow'];
    }

    /**
     * @return list<array{allow: bool, path: string}>
     */
    private function rulesFor(string $origin, string $userAgent): array
    {
        $key = $origin.'|'.$userAgent;

        if (isset($this->rules[$key])) {
            return $this->rules[$key];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => $userAgent])
                ->get("{$origin}/robots.txt");

            $body = $response->successful() ? $response->body() : '';
        } catch (Throwable) {
            // Unreachable robots.txt is not a prohibition.
            $body = '';
        }

        return $this->rules[$key] = $this->parse($body, $userAgent);
    }

    /**
     * @return list<array{allow: bool, path: string}>
     */
    private function parse(string $body, string $userAgent): array
    {
        $userAgent = strtolower($userAgent);
        $groups = [];
        $currentAgents = [];
        $previousLineWasAgent = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = explode(':', $line, 2);
            $field = strtolower(trim($field));
            $value = trim($value);

            if ($field === 'user-agent') {
                // Consecutive User-agent lines share one group of rules.
                if (! $previousLineWasAgent) {
                    $currentAgents = [];
                }

                $currentAgents[] = strtolower($value);
                $previousLineWasAgent = true;

                continue;
            }

            $previousLineWasAgent = false;

            if ($field !== 'disallow' && $field !== 'allow') {
                continue;
            }

            foreach ($currentAgents as $agent) {
                // An empty Disallow means "nothing is disallowed" — skip it
                // rather than recording a rule matching every path.
                if ($field === 'disallow' && $value === '') {
                    continue;
                }

                $groups[$agent][] = ['allow' => $field === 'allow', 'path' => $value];
            }
        }

        // A group naming our agent wins outright; otherwise fall back to *.
        foreach ($groups as $agent => $rules) {
            if ($agent !== '*' && str_contains($userAgent, $agent)) {
                return $rules;
            }
        }

        return $groups['*'] ?? [];
    }

    /**
     * robots.txt paths are prefix matches, with * as a wildcard and $ anchoring
     * the end.
     */
    private function pathMatches(string $target, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        if (! str_contains($pattern, '*') && ! str_ends_with($pattern, '$')) {
            return str_starts_with($target, $pattern);
        }

        $anchored = str_ends_with($pattern, '$');
        $pattern = $anchored ? substr($pattern, 0, -1) : $pattern;

        $regex = implode('.*', array_map(
            fn (string $part) => preg_quote($part, '#'),
            explode('*', $pattern)
        ));

        return (bool) preg_match('#^'.$regex.($anchored ? '$' : '').'#', $target);
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$parts['scheme']}://{$parts['host']}{$port}";
    }
}
