<?php

/*
 * This file is part of the PHP CS utility.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\CS;

/**
 * Collection of code tokens.
 * As a token prototype you should understand a single element generated by token_get_all.
 *
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 */
class Tokens extends \SplFixedArray
{
    const BLOCK_TYPE_PARENTHESIS_BRACE = 1;
    const BLOCK_TYPE_CURLY_BRACE = 2;

    /**
     * Array defining possible block edge.
     * @type array
     */
    static private $blockEdgeDefinition = array(
        self::BLOCK_TYPE_CURLY_BRACE => array(
            'start' => array('{'),
            'end' => array('}'),
        ),
        self::BLOCK_TYPE_PARENTHESIS_BRACE => array(
            'start' => array('('),
            'end' => array(')'),
        ),
    );

    /**
     * Static class cache.
     *
     * @var array
     */
    private static $cache = array();

    /**
     * crc32 hash of code string.
     *
     * @var array
     */
    private $codeHash;

    /**
     * Clear cache - one position or all of them.
     *
     * @param int|string|null $key position to clear, when null clear all
     */
    public static function clearCache($key = null)
    {
        if (null === $key) {
            self::$cache = array();

            return;
        }

        if (self::hasCache($key)) {
            unset(self::$cache[$key]);
        }
    }

    /**
     * Check if given tokens are equal.
     * If tokens are arrays, then only keys defined in second token are checked.
     *
     * @param string|array $tokenA token prototype
     * @param string|array $tokenB token prototype or only few keys of it
     *
     * @return bool
     */
    public static function compare($tokenA, $tokenB)
    {
        $tokenAIsArray = is_array($tokenA);
        $tokenBIsArray = is_array($tokenB);

        if ($tokenAIsArray !== $tokenBIsArray) {
            return false;
        }

        if (!$tokenAIsArray) {
            return $tokenA === $tokenB;
        }

        foreach ($tokenB as $key => $val) {
            if ($tokenA[$key] !== $val) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create token collection from array.
     *
     * @param array $array       the array to import
     * @param bool  $saveIndexes save the numeric indexes used in the original array, default is yes
     *
     * @return Tokens
     */
    public static function fromArray($array, $saveIndexes = null)
    {
        $tokens = new Tokens(count($array));

        if (null === $saveIndexes || $saveIndexes) {
            foreach ($array as $key => $val) {
                $tokens[$key] = $val;
            }

            return $tokens;
        }

        $index = 0;

        foreach ($array as $val) {
            $tokens[$index++] = $val;
        }

        return $tokens;
    }

    /**
     * Create token collection directly from code.
     *
     * @param string $code PHP code
     *
     * @return Tokens
     */
    public static function fromCode($code)
    {
        $codeHash = crc32($code);

        if (self::hasCache($codeHash)) {
            $tokens = self::getCache($codeHash);
            $tokens->clearEmptyTokens();

            return $tokens;
        }

        $tokens = token_get_all($code);

        foreach ($tokens as $index => $tokenPrototype) {
            $tokens[$index] = new Token($tokenPrototype);
        }

        $collection = self::fromArray($tokens);
        $collection->changeCodeHash($codeHash);

        return $collection;
    }

    /**
     * Get cache value for given key.
     *
     * @param int|string $key item key
     *
     * @return misc item value
     */
    private static function getCache($key)
    {
        if (!self::hasCache($key)) {
            throw new \OutOfBoundsException('Unknown cache key: '.$key);
        }

        return self::$cache[$key];
    }

    /**
     * Check if given key exists in cache.
     *
     * @param int|string $key item key
     *
     * @return bool
     */
    private static function hasCache($key)
    {
        return isset(self::$cache[$key]);
    }

    /**
     * Check whether passed method name is one of magic methods.
     *
     * @param string $content name of method
     *
     * @return bool is method a magical
     */
    public static function isMethodNameIsMagic($name)
    {
        static $magicMethods = array(
            '__construct', '__destruct', '__call', '__callStatic', '__get', '__set', '__isset', '__unset',
            '__sleep', '__wakeup', '__toString', '__invoke', '__set_state', '__clone',
        );

        return in_array($name, $magicMethods, true);
    }

    /**
     * Set cache item.
     *
     * @param int|string $key   item key
     * @param int|string $value item value
     */
    private static function setCache($key, $value)
    {
        self::$cache[$key] = $value;
    }

    /**
     * Apply token attributes.
     * Token at given index is prepended by attributes.
     *
     * @param int   $index   token index
     * @param array $attribs array of token attributes
     */
    public function applyAttribs($index, array $attribs)
    {
        $toInsert = array();

        foreach ($attribs as $attrib) {
            if (null !== $attrib && '' !== $attrib->content) {
                $toInsert[] = $attrib;
                $toInsert[] = new Token(' ');
            }
        }

        if (!empty($toInsert)) {
            $this->insertAt($index, $toInsert);
        }
    }

    /**
     * Change code hash.
     *
     * Remove old cache and set new one.
     *
     * @param string $codeHash new code hash
     */
    private function changeCodeHash($codeHash)
    {
        if (null !== $this->codeHash) {
            self::clearCache($this->codeHash);
        }

        $this->codeHash = $codeHash;
        self::setCache($this->codeHash, $this);
    }

    /**
     * Clear empty tokens.
     *
     * Empty tokens can occur e.g. after calling clear on element of collection.
     */
    public function clearEmptyTokens()
    {
        $count = 0;

        foreach ($this as $token) {
            if (!$token->isEmpty()) {
                $this[$count++] = $token;
            }
        }

        $this->setSize($count);
    }

    /**
     * Ensure that on given index is a whitespace with given kind.
     *
     * If there is a whitespace then it's content will be modified.
     * If not - the new Token will be added.
     *
     * @param int    $index       index
     * @param int    $indexOffset index offset for Token insertion
     * @param string $whitespace  whitespace to set
     *
     * @return bool if new Token was added
     */
    public function ensureWhitespaceAtIndex($index, $indexOffset, $whitespace)
    {
        $removeLastCommentLine = function ($token, $indexOffset) {
            // becouse comments tokens are greedy and may consume single \n if we are putting whitespace after it let trim that \n
            if (1 === $indexOffset && $token->isGivenKind(array(T_COMMENT, T_DOC_COMMENT)) && "\n" === $token->content[strlen($token->content) - 1]) {
                $token->content = substr($token->content, 0, -1);
            }
        };

        $token = $this[$index];

        if ($token->isWhitespace()) {
            $removeLastCommentLine($this[$index - 1], $indexOffset);
            $token->content = $whitespace;

            return false;
        }

        $removeLastCommentLine($token, $indexOffset);

        $this->insertAt(
            $index + $indexOffset,
            array(
                new Token(array(T_WHITESPACE, $whitespace)),
            )
        );

        return true;
    }

    /**
     * Find block end.
     *
     * @param  int  $type        type of block, BLOCK_TYPE_CURLY_BRACE or BLOCK_TYPE_PARENTHESIS_BRACE
     * @param  int  $searchIndex index of opening brace
     * @param  bool $findEnd     if method should find block's end, default true, otherwise method find block's start
     * @return int  index of closing brace
     */
    public function findBlockEnd($type, $searchIndex, $findEnd = true)
    {
        if (!isset(self::$blockEdgeDefinition[$type])) {
            throw new \InvalidArgumentException('Invalid param $type');
        }

        $startEdge = self::$blockEdgeDefinition[$type]['start'];
        $endEdge = self::$blockEdgeDefinition[$type]['end'];
        $startIndex = $searchIndex;
        $endIndex = $this->count() - 1;
        $indexOffset = 1;

        if (!$findEnd) {
            list($startEdge, $endEdge) = array($endEdge, $startEdge);
            $indexOffset = -1;
            $endIndex = 0;
        }

        if (!in_array($this[$startIndex]->content, $startEdge, true)) {
            throw new \InvalidArgumentException('Invalid param $startIndex - not a proper block start');
        }

        $blockLevel = 0;

        for ($index = $startIndex; $index !== $endIndex; $index += $indexOffset) {
            $token = $this[$index];

            if (in_array($token->content, $startEdge, true)) {
                ++$blockLevel;

                continue;
            }

            if (in_array($token->content, $endEdge, true)) {
                --$blockLevel;

                if (0 === $blockLevel) {
                    break;
                }

                continue;
            }
        }

        if (!in_array($this[$index]->content, $endEdge, true)) {
            throw new \UnexpectedValueException('Missing block end');
        }

        return $index;
    }

    /**
     * Find tokens of given kind.
     *
     * @param int|array $possibleKind kind or array of kind
     *
     * @return array array of tokens of given kinds or assoc array of arrays
     */
    public function findGivenKind($possibleKind)
    {
        $this->rewind();

        $elements = array();
        $possibleKinds = is_array($possibleKind) ? $possibleKind : array($possibleKind);

        foreach ($possibleKinds as $kind) {
            $elements[$kind] = array();
        }

        foreach ($this as $index => $token) {
            if ($token->isGivenKind($possibleKinds)) {
                $elements[$token->id][$index] = $token;
            }
        }

        return is_array($possibleKind) ? $elements : $elements[$possibleKind];
    }

    /**
     * Generate code from tokens.
     *
     * @return string
     */
    public function generateCode()
    {
        $code = $this->generatePartialCode(0, count($this) - 1);
        $this->changeCodeHash(crc32($code));

        return $code;
    }

    /**
     * Generate code from tokens between given indexes.
     *
     * @param  int    $start start index
     * @param  int    $end   end index
     * @return string
     */
    public function generatePartialCode($start, $end)
    {
        $code = '';

        for ($i = $start; $i <= $end; ++$i) {
            $code .= $this[$i]->content;
        }

        return $code;
    }

    /**
     * Get indexes of methods and properties in classy code (classes, interfaces and traits).
     */
    public function getClassyElements()
    {
        $this->rewind();

        $elements = array();
        $inClass = false;
        $curlyBracesLevel = 0;
        $bracesLevel = 0;

        foreach ($this as $index => $token) {
            if ($token->isGivenKind(T_ENCAPSED_AND_WHITESPACE)) {
                continue;
            }

            if (!$inClass) {
                $inClass = $token->isClassy();
                continue;
            }

            if ('(' === $token->content) {
                ++$bracesLevel;
                continue;
            }

            if (')' === $token->content) {
                --$bracesLevel;
                continue;
            }

            if ('{' === $token->content || $token->isGivenKind(array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES))) {
                ++$curlyBracesLevel;
                continue;
            }

            if ('}' === $token->content) {
                --$curlyBracesLevel;

                if (0 === $curlyBracesLevel) {
                    $inClass = false;
                }

                continue;
            }

            if (1 !== $curlyBracesLevel || !$token->isArray()) {
                continue;
            }

            if (T_VARIABLE === $token->id && 0 === $bracesLevel) {
                $elements[$index] = array('token' => $token, 'type' => 'property');
                continue;
            }

            if (T_FUNCTION === $token->id) {
                $elements[$index] = array('token' => $token, 'type' => 'method');
            }
        }

        return $elements;
    }

    /**
     * Get indexes of namespae uses.
     */
    public function getNamespaceUseIndexes()
    {
        $this->rewind();

        $uses = array();
        $bracesLevel = 0;

        $namespaceWithBraces = false;

        foreach ($this as $index => $token) {
            if (T_NAMESPACE === $token->id) {
                $nextToken = $this->getNextTokenOfKind($index, array(';', '{'));

                if ('{' === $nextToken->content) {
                    $namespaceWithBraces = true;
                }

                continue;
            }

            if ('{' === $token->content) {
                ++$bracesLevel;
                continue;
            }

            if ('}' === $token->content) {
                --$bracesLevel;
                continue;
            }

            if (T_USE !== $token->id || $bracesLevel > ($namespaceWithBraces ? 1 : 0)) {
                continue;
            }

            $nextToken = $this->getNextNonWhitespace($index);

            // ignore function () use ($foo) {}
            if ('(' === $nextToken->content) {
                continue;
            }

            $uses[] = $index;
        }

        return $uses;
    }

    /**
     * Get closest next token which is non whitespace.
     *
     * This method is shorthand for getNonWhitespaceSibling method.
     *
     * @param int      $index       token index
     * @param array    $opts        array of extra options for isWhitespace method
     * @param int|null &$foundIndex index of found token, if any
     *
     * @return Token
     */
    public function getNextNonWhitespace($index, array $opts = array(), &$foundIndex = null)
    {
        return $this->getNonWhitespaceSibling($index, 1, $opts, $foundIndex);
    }

    /**
     * Get closest next token of given kind.
     *
     * This method is shorthand for getTokenOfKindSibling method.
     *
     * @param int      $index       token index
     * @param array    $tokens      possible tokens
     * @param int|null &$foundIndex index of found token, if any
     *
     * @return Token
     */
    public function getNextTokenOfKind($index, array $tokens = array(), &$foundIndex = null)
    {
        return $this->getTokenOfKindSibling($index, 1, $tokens, $foundIndex);
    }

    /**
     * Get closest sibling token which is non whitespace.
     *
     * @param int      $index       token index
     * @param int      $direction   direction for looking, +1 or -1
     * @param array    $opts        array of extra options for isWhitespace method
     * @param int|null &$foundIndex index of found token, if any
     *
     * @return Token
     */
    public function getNonWhitespaceSibling($index, $direction, array $opts = array(), &$foundIndex = null)
    {
        while (true) {
            $index += $direction;

            if (!$this->offsetExists($index)) {
                return null;
            }

            $token = $this[$index];

            if (!$token->isWhitespace($opts)) {
                $foundIndex = $index;

                return $token;
            }
        }
    }

    /**
     * Get closest previous token which is non whitespace.
     *
     * This method is shorthand for getNonWhitespaceSibling method.
     *
     * @param int      $index       token index
     * @param array    $opts        array of extra options for isWhitespace method
     * @param int|null &$foundIndex index of found token, if any
     *
     * @return Token
     */
    public function getPrevNonWhitespace($index, array $opts = array(), &$foundIndex = null)
    {
        return $this->getNonWhitespaceSibling($index, -1, $opts, $foundIndex);
    }

    /**
     * Get closest previous token of given kind.
     * This method is shorthand for getTokenOfKindSibling method.
     *
     * @param int      $index       token index
     * @param array    $tokens      possible tokens
     * @param int|null &$foundIndex index of found token, if any
     *
     * @return Token
     */
    public function getPrevTokenOfKind($index, array $tokens = array(), &$foundIndex = null)
    {
        return $this->getTokenOfKindSibling($index, -1, $tokens, $foundIndex);
    }

    /**
     * Get closest sibling token of given kind.
     *
     * @param int      $index       token index
     * @param int      $direction   direction for looking, +1 or -1
     * @param array    $tokens      possible tokens
     * @param int|null &$foundIndex index of found token, if any
     *
     * @return Token
     */
    public function getTokenOfKindSibling($index, $direction, array $tokens = array(), &$foundIndex = null)
    {
        while (true) {
            $index += $direction;

            if (!$this->offsetExists($index)) {
                return null;
            }

            $token = $this[$index];

            foreach ($tokens as $tokenKind) {
                if (static::compare($token->getPrototype(), $tokenKind)) {
                    $foundIndex = $index;

                    return $token;
                }
            }
        }
    }

    /**
     * Get closest sibling token not of given kind.
     *
     * @param int      $index       token index
     * @param int      $direction   direction for looking, +1 or -1
     * @param array    $tokens      possible tokens
     * @param int|null &$foundIndex index of founded token, if any
     *
     * @return Token
     */
    public function getTokenNotOfKindSibling($index, $direction, array $tokens = array(), &$foundIndex = null)
    {
        while (true) {
            $index += $direction;

            if (!$this->offsetExists($index)) {
                return null;
            }

            $token = $this[$index];

            foreach ($tokens as $tokenKind) {
                if (static::compare($token->getPrototype(), $tokenKind)) {
                    continue 2;
                }
            }

            $foundIndex = $index;

            return $token;
        }
    }

    /**
     * Grab attributes before method token at gixen index.
     * It's a shorthand for grabAttribsBeforeToken method.
     *
     * @param int $index token index
     *
     * @return array array of grabbed attributes
     */
    public function grabAttribsBeforeMethodToken($index)
    {
        static $tokenAttribsMap = array(
            T_PRIVATE => 'visibility',
            T_PROTECTED => 'visibility',
            T_PUBLIC => 'visibility',
            T_ABSTRACT => 'abstract',
            T_FINAL => 'final',
            T_STATIC => 'static',
        );

        return $this->grabAttribsBeforeToken(
            $index,
            $tokenAttribsMap,
            array(
                'abstract' => null,
                'final' => null,
                'visibility' => new Token(array(T_PUBLIC, 'public')),
                'static' => null,
            )
        );
    }

    /**
     * Grab attributes before property token at gixen index.
     * It's a shorthand for grabAttribsBeforeToken method.
     *
     * @param int $index token index
     *
     * @return array array of grabbed attributes
     */
    public function grabAttribsBeforePropertyToken($index)
    {
        static $tokenAttribsMap = array(
            T_VAR => null, // destroy T_VAR token!
            T_PRIVATE => 'visibility',
            T_PROTECTED => 'visibility',
            T_PUBLIC => 'visibility',
            T_STATIC => 'static',
        );

        return $this->grabAttribsBeforeToken(
            $index,
            $tokenAttribsMap,
            array(
                'visibility' => new Token(array(T_PUBLIC, 'public')),
                'static' => null,
            )
        );
    }

    /**
     * Grab attributes before token at gixen index.
     *
     * Grabbed attributes are cleared by overriding them with empty string and should be manually applied with applyTokenAttribs method.
     *
     * @param int   $index           token index
     * @param array $tokenAttribsMap token to attribute name map
     * @param array $attribs         array of token attributes
     *
     * @return array array of grabbed attributes
     */
    public function grabAttribsBeforeToken($index, array $tokenAttribsMap, array $attribs)
    {
        while (true) {
            $token = $this[--$index];

            if (!$token->isArray()) {
                if (in_array($token->content, array('{', '}', '(', ')'), true)) {
                    break;
                }

                continue;
            }

            // if token is attribute
            if (array_key_exists($token->id, $tokenAttribsMap)) {
                // set token attribute if token map defines attribute name for token
                if ($tokenAttribsMap[$token->id]) {
                    $attribs[$tokenAttribsMap[$token->id]] = clone $token;
                }

                // clear the token and whitespaces after it
                $this[$index]->clear();
                $this[$index + 1]->clear();

                continue;
            }

            if ($token->isGivenKind(array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT))) {
                continue;
            }

            break;
        }

        return $attribs;
    }

    /**
     * Insert instances of Token inside collection.
     *
     * @param int                  $index start inserting index
     * @param Tokens|Token[]|Token $items instances of Token to insert
     */
    public function insertAt($key, $items)
    {
        $items = is_array($items) || $items instanceof self ? $items : array($items);
        $itemsCnt = count($items);
        $oldSize = count($this);

        $this->setSize($oldSize + $itemsCnt);

        for ($i = $oldSize + $itemsCnt - 1; $i >= $key; --$i) {
            $this[$i] = isset($this[$i - $itemsCnt]) ? $this[$i - $itemsCnt] : new Token('');
        }

        for ($i = 0; $i < $itemsCnt; ++$i) {
            $this[$i + $key] = $items[$i];
        }
    }

    /**
     * Check if the array at index uses the short-syntax.
     *
     * @param int $index
     *
     * @return bool
     */
    public function isArray($index)
    {
        return $this[$index]->isGivenKind(T_ARRAY) || $this->isShortArray($index);
    }

    /**
     * Check if the array at index is multiline.
     *
     * This only checks the root-level of the array.
     *
     * @param int $index
     *
     * @return bool
     */
    public function isArrayMultiLine($index)
    {
        $multiline = false;
        $bracesLevel = 0;

        // Skip only when its an array, for short arrays we need the brace for correct
        // level counting
        if ($this[$index]->isGivenKind(T_ARRAY)) {
            ++$index;
        }

        for ($c = $this->count(); $index < $c; ++$index) {
            $token = $this[$index];

            if ('(' === $token->content || '[' === $token->content) {
                ++$bracesLevel;
                continue;
            }

            if (1 === $bracesLevel && $token->isGivenKind(T_WHITESPACE) && false !== strpos($token->content, "\n")) {
                $multiline = true;
                break;
            }

            if (')' === $token->content || ']' === $token->content) {
                --$bracesLevel;

                if (0 === $bracesLevel) {
                    break;
                }
            }
        }

        return $multiline;
    }

    /**
     * Check if the array at index uses the short-syntax.
     *
     * @param int $index
     *
     * @return bool
     */
    public function isShortArray($index)
    {
        $token = $this[$index];

        if ('[' !== $token->content) {
            return false;
        }

        $prevToken = $this->getPrevNonWhitespace($index);
        if (!$prevToken->isArray() && in_array($prevToken->content, array('=>', '=', '+', '(', '['), true)) {
            return true;
        }

        return false;
    }

    /**
     * If $index is below zero, we know that it does not exist.
     *
     * This was added to be compatible with HHVM 3.2.0.
     * Note that HHVM 3.3.0 no longer requires this work around.
     *
     * @param int $index
     *
     * @return bool
     */
    public function offsetExists($index)
    {
        return $index >= 0 && parent::offsetExists($index);
    }

    /**
     * Removes all the leading whitespace.
     *
     * @param int   $index
     * @param array $opts  optional array of extra options for Token::isWhitespace method
     */
    public function removeLeadingWhitespace($index, array $opts = array())
    {
        if (isset($this[$index - 1]) && $this[$index - 1]->isWhitespace($opts)) {
            $this[$index - 1]->clear();
        }
    }

    /**
     * Removes all the trailing whitespace.
     *
     * @param int   $index
     * @param array $opts  optional array of extra options for Token::isWhitespace method
     */
    public function removeTrailingWhitespace($index, array $opts = array())
    {
        if (isset($this[$index + 1]) && $this[$index + 1]->isWhitespace($opts)) {
            $this[$index + 1]->clear();
        }
    }

    /**
     * Set code. Clear all current content and replace it by new Token items generated from code directly.
     *
     * @param string $code PHP code
     */
    public function setCode($code)
    {
        // clear memory
        $this->setSize(0);

        $tokens = token_get_all($code);
        $this->setSize(count($tokens));

        foreach ($tokens as $index => $token) {
            $this[$index] = new Token($token);
        }

        $this->rewind();
        $this->changeCodeHash(crc32($code));
    }

    public function toJSON()
    {
        static $optNames = array('JSON_PRETTY_PRINT', 'JSON_NUMERIC_CHECK');

        $output = new \SplFixedArray(count($this));

        foreach ($this as $index => $token) {
            $output[$index] = $token->toArray();
        }

        $this->rewind();

        $options = 0;

        foreach ($optNames as $optName) {
            if (defined($optName)) {
                $options |= constant($optName);
            }
        }

        return json_encode($output, $options);
    }

    /**
     * Clone tokens collection.
     */
    public function __clone()
    {
        foreach ($this as $key => $val) {
            $this[$key] = clone $val;
        }
    }
}
