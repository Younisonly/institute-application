<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class StringsAudit extends Command
{
    protected $signature = 'strings:audit
        {--dir= : Only scan this relative directory (e.g. Filament/Resources)}
        {--json : Output findings as JSON}';

    protected $description = 'Scan PHP and Blade sources for user-facing strings that are not wrapped in __()';

    private const ALLOWLIST = [
        'ar', 'en', 'rlt', 'ltr', 'auto', 'yes', 'no', 'on', 'off', 'null',
        'name', 'id', 'password', 'remember_token', 'created_at', 'updated_at',
        'deleted_at', 'text', 'date', 'time', 'datetime', 'number', 'email',
        'url', 'file', 'image', 'color', 'select', 'checkbox', 'toggle',
        'view', 'edit', 'delete', 'create', 'update', 'total', 'total_courses',
        'left', 'right', 'top', 'bottom', 'in', 'out', 'inventory', 'summary',
        'description', 'reason', 'notes', 'note', 'default', 'primary',
        'yyyymm', 'ddmmyyyy', 'heroicon',
    ];

    private const TEXT_METHODS = [
        'title', 'label', 'placeholder', 'helperText', 'hint', 'hintAction',
        'description', 'content', 'vlabel', 'notification', 'tooltip',
        'emptyDescription', 'emptyHeading', 'header', 'subheading', 'subtitle',
        'name', 'question', 'icon', 'color', 'formatStateUsing',
    ];

    private array $findings = [];

    public function handle(): int
    {
        $dir = $this->option('dir');
        $paths = $dir
            ? [app_path($dir)]
            : [app_path(), resource_path('views')];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                $this->error("Directory does not exist: {$path}");

                return self::FAILURE;
            }

            foreach ($this->phpFiles($path) as $file) {
                ($file->getExtension() === 'php')
                    ? $this->scanPhp($file->getPathname())
                    : $this->scanBlade($file->getPathname());
            }
        }

        if ($this->option('json')) {
            $this->output->writeln(json_encode($this->findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            foreach ($this->findings as $finding) {
                $this->warn(str_replace(base_path(), '.', $finding['file']).':'.$finding['line'].'  '.$finding['string']);
            }
            $count = count($this->findings);
            if ($count > 0) {
                $this->error("{$count} unlocalized string(s) found — wrap them in __() and add the key to BOTH lang/en and lang/ar.");

                return self::FAILURE;
            }
            $this->info('No unlocalized user-facing strings found.');
        }

        return self::SUCCESS;
    }

    private function phpFiles(string $path): \Generator
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'blade.php'], true)) {
                continue;
            }
            if (str_contains($file->getPathname(), '/vendor/') || str_contains($file->getPathname(), '/lang/')) {
                continue;
            }

            yield $file;
        }
    }

    private function scanPhp(string $path): void
    {
        $tokens = token_get_all(file_get_contents($path));
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_OBJECT_OPERATOR) {
                $next = $tokens[$i + 1] ?? null;
                $after = $tokens[$i + 2] ?? null;
                if (is_array($next) && $next[0] === T_STRING && in_array($next[1], self::TEXT_METHODS, true)
                    && $after === '(') {
                    $this->inspectArgument($tokens, $i + 3, $path);
                }
                continue;
            }

            if (is_array($token) && $token[0] === T_STRING && in_array($token[1], ['withMessages', 'abort'], true)) {
                $next = $tokens[$i + 1] ?? null;
                if ($next === '(') {
                    $this->inspectArgument($tokens, $i + 2, $path, true);
                }
                continue;
            }

            if (is_array($token) && $token[0] === T_THROW) {
                $this->inspectThrow($tokens, $i + 1, $path);
            }
        }
    }

    private function inspectThrow(array $tokens, int $i, string $path): void
    {
        $count = count($tokens);

        while ($i < $count && is_array($tokens[$i] ?? null) && $tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        if (! is_array($tokens[$i] ?? null) || $tokens[$i][0] !== T_NEW) {
            return;
        }

        $i++;
        while ($i < $count && is_array($tokens[$i] ?? null) && $tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        $class = $tokens[$i] ?? null;
        if (! is_array($class) || ! in_array($class[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)) {
            return;
        }

        $i++;
        while ($i < $count && is_array($tokens[$i] ?? null) && $tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        if (($tokens[$i] ?? null) !== '(') {
            return;
        }

        $this->inspectArgument($tokens, $i + 1, $path);
    }

    private function inspectArgument(array $tokens, int $i, string $path, bool $arrayValues = false): void
    {
        $count = count($tokens);
        $expectValue = false;

        while ($i < $count) {
            $token = $tokens[$i];
            $type = $token[0] ?? null;

            if ($type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT) {
                $i++;
                continue;
            }

            if (is_array($token) && $type === T_STRING && in_array($token[1], ['__', 'trans'], true) && ($tokens[$i + 1] ?? null) === '(') {
                $i = $this->skipParens($tokens, $i + 1);
                continue;
            }

            if ($type === T_CONSTANT_ENCAPSED_STRING) {
                if (! $arrayValues || $expectValue) {
                    $literal = $this->unquote($token[1]);
                    if ($this->isUnlocalizedLiteral($literal)) {
                        $this->addFinding($path, $literal, $token[2] ?? 0);
                    }
                }
                $i++;
                continue;
            }

            if ($token === '"') {
                $j = $i + 1;
                $text = '';
                while ($j < $count && $tokens[$j] !== '"') {
                    $text .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                    $j++;
                }
                if ($j < $count && (! $arrayValues || $expectValue) && $this->isUnlocalizedLiteral($text)) {
                    $this->addFinding($path, $text, is_array($tokens[$i + 1] ?? null) ? ($tokens[$i + 1][2] ?? 0) : 0);
                }
                $i = $j + 1;
                continue;
            }

            if ($arrayValues && $type === T_DOUBLE_ARROW) {
                $expectValue = true;
                $i++;
                continue;
            }

            if ($arrayValues && $type === ',') {
                $expectValue = false;
                $i++;
                continue;
            }

            if ($type === ')' || $type === ';' || $type === '{' || $type === '}' || $type === ']' || $type === '[') {
                return;
            }

            if (in_array($type, [T_VARIABLE, T_STRING], true)) {
                return;
            }

            $i++;
        }
    }

    private function addFinding(string $path, string $literal, int $line): void
    {
        if ($line === 0) {
            return;
        }

        $this->findings[] = ['file' => $path, 'line' => $line, 'string' => $literal];
    }

    private function skipParens(array $tokens, int $i): int
    {
        $depth = 0;
        $count = count($tokens);
        for (; $i < $count; $i++) {
            $type = $tokens[$i][0] ?? $tokens[$i];
            if ($type === '(') {
                $depth++;
            } elseif ($type === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i + 1;
                }
            }
        }

        return $i;
    }

    private function unquote(string $token): string
    {
        $token = trim($token);

        if ((str_starts_with($token, "'") && str_ends_with($token, "'"))
            || (str_starts_with($token, '"') && str_ends_with($token, '"'))) {
            return substr($token, 1, -1);
        }

        return $token;
    }

    private function isUnlocalizedLiteral(string $literal): bool
    {
        $literal = trim($literal);

        if ($literal === '' || mb_strlen($literal) < 3) {
            return false;
        }

        if (! $this->containsLetter($literal)) {
            return false;
        }

        if (str_starts_with($literal, 'heroicon-')) {
            return false;
        }

        $normalized = mb_strtolower(trim(preg_replace('/[^a-zA-Z0-9]/u', '', $literal)));
        if ($normalized === '' || in_array($normalized, self::ALLOWLIST, true)) {
            return false;
        }

        if (preg_match('/^[a-z0-9_]+$/', $literal)) {
            return false;
        }

        return ! preg_match('/^[0-9.-]+$/', $literal);
    }

    private function containsLetter(string $s): bool
    {
        return (bool) preg_match('/[a-zA-Z\x{0600}-\x{06FF}]/u', $s);
    }

    private function scanBlade(string $path): void
    {
        $content = file_get_contents($path);
        preg_match_all('/>([^<>{%][^<{]*?)</', $content, $matches);

        foreach ($matches[1] as $text) {
            $text = trim(html_entity_decode(strip_tags($text)));
            if ($text === '' || preg_match('/[{}@%]/', $text) || $text === 'سبارك') {
                continue;
            }
            if ($this->isUnlocalizedLiteral($text)) {
                $line = $this->lineNumber($content, $text);
                if ($line > 0) {
                    $this->addFinding($path, $text, $line);
                }
            }
        }
    }

    private function lineNumber(string $content, string $needle): int
    {
        $pos = strpos($content, $needle);
        if ($pos === false) {
            return 0;
        }

        return substr_count(substr($content, 0, $pos), "\n") + 1;
    }
}