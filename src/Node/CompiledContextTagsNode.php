<?php
/*
 * MIT License
 *
 * Copyright (c) 2021-2025 machinateur
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace Machinateur\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * A meta-node containing all the {@see ContextTagNode}s from the current `ModuleTag`.
 *
 * This node is then used to compile a custom method into the twig `Template` generated classes.
 *  The `getContextTags(...)` method will be available using `WrappedTemplate::unwrap()`.
 */
#[YieldReady()]
class CompiledContextTagsNode extends Node
{
    /**
     * Compile a custom method to insert into the template code.
     */
    public function compile(Compiler $compiler): void
    {
        $compiler->write(
            \sprintf(
<<<'PHP'
/**
     * Custom context tagging method created by machinateur.
     *
     * @codeCoverageIgnore
     *
     * @return array<string>
     */
    public function getContextTags(bool $includeParent = false): array
    {
        $tags = %s;
    
        if ($includeParent && $parent = $this->getParent([])) {
            $tags = \array_merge($parent->getContextTags(), $tags);
        }
    
        return $tags;
    }

PHP,
                \str_replace("\n", '', \var_export($this->getContextTags(), true)),
            )
        );
    }

    /**
     * Collect all tags from all contained {@see ContextTagNode}.
     *
     * The returned array will be unique and sorted by occurrence count.
     *
     * @return array<string>
     */
    protected function getContextTags(): array
    {
        /** @var array<string, int> $tags */
        $tags = [];

        // Loop all nodes and their tags (stored as attribute).
        foreach ($this->nodes as $node) {
            foreach ($node->getAttribute('tags') as $tag) {
                // Initialize count if not set.
                $tags[$tag] ??= 0;
                // Increment count by 1.
                ++$tags[$tag];
            }
        }

        // Sort the collected tags by count.
        \asort($tags, \SORT_NUMERIC);

        // Return only the keys. Like this, the array also remains unique without risky `\array_unique()` calls.
        return \array_keys($tags);
    }
}
