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

namespace Machinateur\Twig\NodeVisitor;

use Machinateur\Twig\Node\CompiledContextTagsNode;
use Machinateur\Twig\Node\ContextTagNode;
use Twig\Environment;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * This visitor will collect all tags after parsing a template and saving them to a custom `Template` method at compile-time,
 *  which in turn makes it possible to predict which context is required for the respective template at runtime,
 *  before rendering the actual contents.
 *
 * This strategy utilizes several core extendability features of the twig engine:
 *
 * - {@see ContextTagNode}
 * - {@see CompiledContextTagsNode}
 * - {@see \Machinateur\Twig\TokenParser\ContextTagTokenParser}
 *
 * This especially helps in a `1:1` route to template-file kinds of situations.
 */
class ContextTagNodeVisitor implements NodeVisitorInterface
{
    /**
     * A collection to keep track of nested `ModuleNode`s.
     *
     * @var array<ModuleNode>
     */
    private array $moduleStack = [];

    public function enterNode(Node $node, Environment $env): Node
    {
        // First make sure any `ModuleNode` in the tree has a meta-node to store tags.
        if ($node instanceof ModuleNode) {
            $metaNodeExists = $node->getNode('class_end')
                ->hasNode('get_context_tags');

            if ( ! $metaNodeExists) {
                $node->getNode('class_end')
                    ->setNode('get_context_tags', new CompiledContextTagsNode());
            }

            // Then push it onto the stack.
            //  Note: It remains unclear for now, if ModuleNodes are nested at all, but this implementation will fit all.
            $this->moduleStack[] = $node;

            return $node;
        }

        // If we encounter a `ContextTagNode` in the tree, shake it for tags, that might fall out,
        if ($node instanceof ContextTagNode) {
            // Get the compiled tags current module
            $compiledContextTags = \end($this->moduleStack)
                ->getNode('class_end')
                ->getNode('get_context_tags');

            // Add the `ContextTagNode` by index for processing at compile-time.
            $compiledContextTags->setNode((string)$compiledContextTags->count(), $node);
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($node instanceof ModuleNode) {
            // Discard the `ModuleNode` itself, as we're about to exit it anyway.
            \array_pop($this->moduleStack);
        }

        return $node;
    }

    /**
     * Priority `0` is the default.
     */
    public function getPriority(): int
    {
        return 0;
    }
}
