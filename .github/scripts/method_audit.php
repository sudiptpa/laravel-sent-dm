<?php

declare(strict_types=1);

/**
 * Method audit: wrapper calls vs the installed sentdm/sent-dm-php SDK.
 *
 * This package delegates all HTTP transport to the official, Stainless-generated
 * `sentdm/sent-dm-php` SDK (the SDK itself is what's generated from the Sent.dm
 * OpenAPI spec — see openapi/sent-dm.json, vendored from .stats.yml). Our
 * Resources/Builders only ever call `$this->client->{resource}->{method}(name: ...)`
 * with named arguments, never raw HTTP. The risk surface that matters here is
 * drift between our wrapper code and the installed SDK version: a method or
 * named param we call that no longer exists (renamed/removed) on the vendor
 * SDK class. PHP reflection on the autoloaded vendor classes catches this
 * directly, with zero guessing — the installed SDK is the ground truth for
 * "what params exist", same role the OpenAPI spec plays for hand-rolled HTTP.
 *
 * Exits 1 if any wrapper call references a non-existent method or named
 * parameter on its mapped SDK service class.
 */

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

/** @var array<string, class-string> $serviceMap chain root segment -> SDK service FQCN */
$serviceMap = [
    'contacts' => 'SentDm\\Services\\ContactsService',
    'templates' => 'SentDm\\Services\\TemplatesService',
    'webhooks' => 'SentDm\\Services\\WebhooksService',
    'profiles' => 'SentDm\\Services\\ProfilesService',
    'users' => 'SentDm\\Services\\UsersService',
    'messages' => 'SentDm\\Services\\MessagesService',
    'numbers' => 'SentDm\\Services\\NumbersService',
    'me' => 'SentDm\\Services\\MeService',
    'conversations' => 'SentDm\\Services\\ConversationsService',
];

/** @var array<string, class-string> $nestedServiceMap "root.segment" -> SDK service FQCN */
$nestedServiceMap = [
    'profiles.campaigns' => 'SentDm\\Services\\Profiles\\CampaignsService',
];

$srcDir = $root.'/src';
$files = [];
foreach (['Resources', 'Builders'] as $dir) {
    foreach (glob($srcDir.'/'.$dir.'/*.php') ?: [] as $file) {
        $files[] = $file;
    }
}
$files[] = $srcDir.'/Sent.php';

$findings = [];

foreach ($files as $file) {
    $code = file_get_contents($file);
    if ($code === false) {
        continue;
    }

    // Match $this->client->seg1->seg2->...->method(   or   $client->seg1->...->method(
    preg_match_all(
        '/\$(?:this->)?client((?:->\w+)+)\s*\(/',
        $code,
        $matches,
        PREG_OFFSET_CAPTURE,
    );

    foreach ($matches[1] as $i => [$chain, $chainOffset]) {
        $segments = array_values(array_filter(explode('->', $chain)));
        $method = array_pop($segments);
        $root1 = $segments[0] ?? null;

        $serviceClass = null;
        if (count($segments) === 2 && isset($nestedServiceMap[$segments[0].'.'.$segments[1]])) {
            $serviceClass = $nestedServiceMap[$segments[0].'.'.$segments[1]];
        } elseif (count($segments) === 1 && isset($serviceMap[$segments[0]])) {
            $serviceClass = $serviceMap[$segments[0]];
        }

        if ($serviceClass === null) {
            // Unknown chain (e.g. ->raw->...): not one of our mapped resources, skip.
            continue;
        }

        if (! class_exists($serviceClass)) {
            $findings[] = sprintf('%s: SDK class %s not found (installed SDK version mismatch?)', relpath($file, $root), $serviceClass);

            continue;
        }

        if (! method_exists($serviceClass, $method)) {
            $findings[] = sprintf('%s: %s->%s() does not exist on installed %s (GUESSED or stale method)', relpath($file, $root), implode('->', $segments), $method, $serviceClass);

            continue;
        }

        $openParenOffset = $chainOffset + strlen($chain);
        $argsText = extractBalancedArgs($code, $openParenOffset);
        $namedArgs = extractTopLevelNamedArgs($argsText);

        $reflection = new ReflectionMethod($serviceClass, $method);
        $validParams = [];
        foreach ($reflection->getParameters() as $param) {
            $validParams[$param->getName()] = true;
        }

        foreach ($namedArgs as $argName) {
            if (! isset($validParams[$argName])) {
                $findings[] = sprintf(
                    "%s: %s->%s() called with named arg '%s:' which is not a parameter of %s::%s() (GUESSED or stale param)",
                    relpath($file, $root),
                    implode('->', $segments),
                    $method,
                    $argName,
                    $serviceClass,
                    $method,
                );
            }
        }
    }
}

if ($findings !== []) {
    fwrite(STDOUT, "Method audit findings:\n\n");
    foreach ($findings as $finding) {
        fwrite(STDOUT, '- '.$finding."\n");
    }
    fwrite(STDOUT, "\nFAIL: wrapper calls reference methods/params not present on the installed sentdm/sent-dm-php SDK.\n");
    exit(1);
}

fwrite(STDOUT, "Method audit: clean. Every wrapper call matches the installed SDK's method/param names.\n");

/**
 * Find the substring inside the parentheses that open at $openParenOffset,
 * respecting nested parens/brackets/braces and string literals.
 */
function extractBalancedArgs(string $code, int $openParenOffset): string
{
    $depth = 0;
    $start = $openParenOffset;
    $len = strlen($code);
    $inString = null;

    for ($i = $openParenOffset; $i < $len; $i++) {
        $char = $code[$i];

        if ($inString !== null) {
            if ($char === '\\') {
                $i++;
            } elseif ($char === $inString) {
                $inString = null;
            }

            continue;
        }

        if ($char === '"' || $char === "'") {
            $inString = $char;

            continue;
        }

        if ($char === '(') {
            $depth++;
            if ($depth === 1) {
                $start = $i + 1;
            }
        } elseif ($char === ')') {
            $depth--;
            if ($depth === 0) {
                return substr($code, $start, $i - $start);
            }
        }
    }

    return '';
}

/**
 * @return list<string> top-level "name:" argument names (skips positional args)
 */
function extractTopLevelNamedArgs(string $argsText): array
{
    $names = [];
    $depth = 0;
    $inString = null;
    $buffer = '';
    $chunks = [];
    $len = strlen($argsText);

    for ($i = 0; $i < $len; $i++) {
        $char = $argsText[$i];

        if ($inString !== null) {
            $buffer .= $char;
            if ($char === '\\') {
                $i++;
                $buffer .= $argsText[$i] ?? '';
            } elseif ($char === $inString) {
                $inString = null;
            }

            continue;
        }

        if ($char === '"' || $char === "'") {
            $inString = $char;
            $buffer .= $char;

            continue;
        }

        if (in_array($char, ['(', '[', '{'], true)) {
            $depth++;
        } elseif (in_array($char, [')', ']', '}'], true)) {
            $depth--;
        }

        if ($char === ',' && $depth === 0) {
            $chunks[] = $buffer;
            $buffer = '';

            continue;
        }

        $buffer .= $char;
    }
    if (trim($buffer) !== '') {
        $chunks[] = $buffer;
    }

    foreach ($chunks as $chunk) {
        if (preg_match('/^\s*(\w+)\s*:(?!:)/', $chunk, $m)) {
            $names[] = $m[1];
        }
    }

    return $names;
}

function relpath(string $file, string $root): string
{
    return ltrim(str_replace($root, '', $file), '/');
}
