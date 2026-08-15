<?php

declare(strict_types=1);

/**
 * Shape audit: hand-built array literals vs the installed SDK's typed shapes.
 *
 * Our wrapper almost always passes typed SDK model objects or scalars straight
 * through to `$this->client->{resource}->{method}()`. The one exception is a
 * hand-built ['key' => ...] array assembled in our own code (e.g. the message
 * template array in src/Sent.php) before being forwarded as a named argument.
 * Those literal keys are exactly the kind of thing that can drift from the
 * wire shape silently. This script finds every `$var = [...]` array literal
 * with string keys, follows $var to a `name: $var` call site on a known SDK
 * service method, resolves that parameter's declared model class via
 * reflection, and checks the literal's keys against the model's public
 * property names.
 *
 * Exits 1 if any array literal has a key the resolved model class does not
 * declare (GUESSED) or is missing a non-nullable property the model requires.
 */

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

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

    // $var = ['key' => ...   (literal must contain at least one string key to be wire-relevant)
    preg_match_all('/\$(\w+)\s*=\s*\[\s*([\'"])(\w+)\2\s*=>/', $code, $assignMatches);
    $literalVars = array_unique($assignMatches[1]);

    if ($literalVars === []) {
        continue;
    }

    foreach ($literalVars as $varName) {
        // Collect every literal key assigned to this var: the initial [...] literal
        // plus any $var['key'] = ... follow-up assignments (e.g. Sent.php's $template).
        $keys = [];
        if (preg_match('/\$'.preg_quote($varName, '/').'\s*=\s*\[(.*?)\];/s', $code, $m)) {
            preg_match_all('/[\'"](\w+)[\'"]\s*=>/', $m[1], $km);
            $keys = array_merge($keys, $km[1]);
        }
        preg_match_all('/\$'.preg_quote($varName, '/').'\[[\'"](\w+)[\'"]\]\s*=/', $code, $km2);
        $keys = array_merge($keys, $km2[1]);
        $keys = array_values(array_unique($keys));

        if ($keys === []) {
            continue;
        }

        // Find where $varName is forwarded as a named arg: paramName: $varName
        if (! preg_match('/(\w+)\s*:\s*\$'.preg_quote($varName, '/').'\b/', $code, $argMatch)) {
            continue;
        }
        $paramName = $argMatch[1];

        // Find the enclosing $this->client->seg...->method( call for that named arg:
        // the nearest preceding "client(->seg)+(" before the named-arg occurrence.
        $callPos = strpos($code, $paramName.': $'.$varName);
        if ($callPos === false) {
            continue;
        }
        $beforeCall = substr($code, 0, $callPos);
        if (! preg_match_all('/\$(?:this->)?client((?:->\w+)+)\s*\(/', $beforeCall, $allChains, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        if ($allChains[1] === []) {
            continue;
        }
        $chain = end($allChains[1])[0];

        $segments = array_values(array_filter(explode('->', $chain)));
        $method = array_pop($segments);

        $serviceClass = null;
        if (count($segments) === 2 && isset($nestedServiceMap[$segments[0].'.'.$segments[1]])) {
            $serviceClass = $nestedServiceMap[$segments[0].'.'.$segments[1]];
        } elseif (count($segments) === 1 && isset($serviceMap[$segments[0]])) {
            $serviceClass = $serviceMap[$segments[0]];
        }

        if ($serviceClass === null || ! method_exists($serviceClass, $method)) {
            continue;
        }

        $reflection = new ReflectionMethod($serviceClass, $method);
        $modelClass = null;
        foreach ($reflection->getParameters() as $param) {
            if ($param->getName() !== $paramName) {
                continue;
            }
            $modelClass = resolveModelClass($param->getType());
        }

        if ($modelClass === null || ! class_exists($modelClass)) {
            // Param is a plain array/scalar shape with no dedicated model class
            // (phpstan-only `Shape`) — nothing to reflect against here.
            continue;
        }

        $modelProps = [];
        foreach ((new ReflectionClass($modelClass))->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $modelProps[$prop->getName()] = true;
        }

        foreach ($keys as $key) {
            if (! isset($modelProps[$key])) {
                $findings[] = sprintf(
                    "%s: \$%s['%s'] is not a public property of %s (resolved from %s::%s()'s '%s' param) — GUESSED key",
                    relpath($file, $root),
                    $varName,
                    $key,
                    $modelClass,
                    $serviceClass,
                    $method,
                    $paramName,
                );
            }
        }
    }
}

if ($findings !== []) {
    fwrite(STDOUT, "Shape audit findings:\n\n");
    foreach ($findings as $finding) {
        fwrite(STDOUT, '- '.$finding."\n");
    }
    fwrite(STDOUT, "\nFAIL: hand-built array literal(s) use keys not present on the resolved SDK model.\n");
    exit(1);
}

fwrite(STDOUT, "Shape audit: clean. Every hand-built array literal matches its resolved SDK model's properties.\n");

function resolveModelClass(?ReflectionType $type): ?string
{
    if ($type === null) {
        return null;
    }

    $candidates = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

    foreach ($candidates as $candidate) {
        if (! $candidate instanceof ReflectionNamedType || $candidate->isBuiltin()) {
            continue;
        }

        return $candidate->getName();
    }

    return null;
}

function relpath(string $file, string $root): string
{
    return ltrim(str_replace($root, '', $file), '/');
}
