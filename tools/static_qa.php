<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$issues = [];
$checkedPrepareCalls = 0;
$checkedPairs = 0;

function significant(array $tokens, int $index, int $step = 1): ?int
{
    $count = count($tokens);
    for ($i = $index; $i >= 0 && $i < $count; $i += $step) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $i;
    }
    return null;
}

function tokenText(array|string $token): string
{
    return is_array($token) ? $token[1] : $token;
}

function callBounds(array $tokens, int $openIndex): ?array
{
    $depth = 0;
    $commas = 0;
    for ($i = $openIndex; $i < count($tokens); $i++) {
        $text = tokenText($tokens[$i]);
        if ($text === '(') {
            $depth++;
        } elseif ($text === ')') {
            $depth--;
            if ($depth === 0) return [$openIndex + 1, $i - 1, $commas, $i];
        } elseif ($text === ',' && $depth === 1) {
            $commas++;
        }
    }
    return null;
}

function literalSql(array $tokens, int $start, int $end): ?string
{
    $sql = '';
    $expectLiteral = true;
    for ($i = $start; $i <= $end; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
        $text = tokenText($token);
        if ($expectLiteral && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            try {
                /** @var string $part */
                $part = eval('return ' . $text . ';');
                $sql .= $part;
            } catch (Throwable) {
                return null;
            }
            $expectLiteral = false;
            continue;
        }
        if (!$expectLiteral && $text === '.') {
            $expectLiteral = true;
            continue;
        }
        return null;
    }
    return $expectLiteral ? null : $sql;
}

function arrayElementCount(array $tokens, int $start, int $end): ?int
{
    $first = significant($tokens, $start);
    if ($first === null || $first > $end) return 0;
    if (tokenText($tokens[$first]) !== '[') return null;
    $depth = 0;
    $commas = 0;
    $hasContent = false;
    $lastTopLevelWasComma = false;
    for ($i = $first; $i <= $end; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
        $text = tokenText($token);
        if ($text === '[' || $text === '(' || $text === '{') {
            $depth++;
            if ($depth > 1) $hasContent = true;
        } elseif ($text === ']' || $text === ')' || $text === '}') {
            $depth--;
            if ($depth === 0 && $text === ']') break;
        } elseif ($depth === 1 && $text === ',') {
            $commas++;
            $lastTopLevelWasComma = true;
        } else {
            $hasContent = true;
            if ($depth === 1) $lastTopLevelWasComma = false;
        }
    }
    return $hasContent ? $commas + ($lastTopLevelWasComma ? 0 : 1) : 0;
}

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') $files[] = $file->getPathname();
}
sort($files);

foreach ($files as $file) {
    $source = file_get_contents($file);
    if ($source === false) continue;
    $tokens = token_get_all($source);
    $preparedByVariable = [];
    for ($i = 0; $i < count($tokens); $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_OBJECT_OPERATOR) continue;
        $nameIndex = significant($tokens, $i + 1);
        if ($nameIndex === null || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING) continue;
        $method = strtolower($tokens[$nameIndex][1]);
        if (!in_array($method, ['prepare', 'query', 'exec', 'execute'], true)) continue;
        $openIndex = significant($tokens, $nameIndex + 1);
        if ($openIndex === null || tokenText($tokens[$openIndex]) !== '(') continue;
        $bounds = callBounds($tokens, $openIndex);
        if (!$bounds) {
            $issues[] = "$file: unclosed $method() call";
            continue;
        }
        [$argStart, $argEnd, $commas, $closeIndex] = $bounds;

        if (in_array($method, ['prepare', 'query', 'exec'], true)) {
            $checkedPrepareCalls++;
            if ($commas > 0) {
                $line = is_array($token) ? $token[2] : 0;
                $issues[] = "$file:$line $method() has more than one top-level argument";
            }
            if ($method !== 'prepare') continue;
            $sql = literalSql($tokens, $argStart, $argEnd);
            if ($sql === null) continue;
            $placeholderCount = substr_count($sql, '?');

            // Track `$variable = ...->prepare('literal')`.
            $statementStart = $i - 1;
            while ($statementStart >= 0 && tokenText($tokens[$statementStart]) !== ';' && tokenText($tokens[$statementStart]) !== '{' && tokenText($tokens[$statementStart]) !== '}') $statementStart--;
            for ($j = $statementStart + 1; $j < $i; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_VARIABLE) {
                    $eq = significant($tokens, $j + 1);
                    if ($eq !== null && tokenText($tokens[$eq]) === '=') {
                        $preparedByVariable[$tokens[$j][1]] = [$placeholderCount, is_array($tokens[$j]) ? $tokens[$j][2] : 0];
                    }
                }
            }

            // Check a directly chained ->execute([...]).
            $op = significant($tokens, $closeIndex + 1);
            $execName = $op === null ? null : significant($tokens, $op + 1);
            if ($op !== null && tokenText($tokens[$op]) === '->' && $execName !== null && is_array($tokens[$execName]) && strtolower($tokens[$execName][1]) === 'execute') {
                $execOpen = significant($tokens, $execName + 1);
                if ($execOpen !== null && tokenText($tokens[$execOpen]) === '(') {
                    $execBounds = callBounds($tokens, $execOpen);
                    if ($execBounds) {
                        $paramCount = arrayElementCount($tokens, $execBounds[0], $execBounds[1]);
                        if ($paramCount !== null) {
                            $checkedPairs++;
                            if ($placeholderCount !== $paramCount) $issues[] = "$file: direct prepare/execute placeholders=$placeholderCount parameters=$paramCount";
                        }
                    }
                }
            }
        } elseif ($method === 'execute') {
            $objectIndex = significant($tokens, $i - 1, -1);
            if ($objectIndex === null || !is_array($tokens[$objectIndex]) || $tokens[$objectIndex][0] !== T_VARIABLE) continue;
            $variable = $tokens[$objectIndex][1];
            if (!isset($preparedByVariable[$variable])) continue;
            $paramCount = arrayElementCount($tokens, $argStart, $argEnd);
            if ($paramCount === null) continue;
            [$placeholderCount] = $preparedByVariable[$variable];
            $checkedPairs++;
            if ($placeholderCount !== $paramCount) {
                $line = is_array($token) ? $token[2] : 0;
                $issues[] = "$file:$line $variable prepare/execute placeholders=$placeholderCount parameters=$paramCount";
            }
        }
    }
}

printf("[INFO] PHP files=%d DB calls=%d literal prepare/execute pairs=%d\n", count($files), $checkedPrepareCalls, $checkedPairs);
if ($issues) {
    foreach ($issues as $issue) echo "[FAIL] $issue\n";
    exit(1);
}
echo "[OK] Static database-call checks passed.\n";
