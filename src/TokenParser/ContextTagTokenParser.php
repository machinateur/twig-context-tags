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

namespace Machinateur\Twig\TokenParser;

use Machinateur\Twig\Node\ContextTagNode;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * A {@see \Twig\TokenParser\TokenParserInterface `TokenParserInterface`} implementation
 *  for the following language construct:
 *
 * ```
 * {% tag 'some-context-tag', 'some-other-context-tag' %}
 * ```
 *
 * This allows to collect all tags when parsing and saving them to a custom `Template` method at compile-time,
 *  which in turn makes it possible to predict which context is required for the respective template at runtime,
 *  before rendering the actual contents.
 *
 * This especially helps in a `1:1` route to template-file kinds of situations.
 */
class ContextTagTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();

        // Copied from "extends" tag, which is similar in that regard.
        if ($this->parser->peekBlockStack()) {
            throw new SyntaxError('Cannot use "tag" in a block.', $token->getLine(), $stream->getSourceContext());
        } elseif ( ! $this->parser->isMainScope()) {
            throw new SyntaxError('Cannot use "tag" in a macro.', $token->getLine(), $stream->getSourceContext());
        }

        // Get the actual token the stream is pointing to.
        $token = $stream->getCurrent();

        // It's allowed to omit array tokens, but in that case we need to wrap the list of tags as array internally.
        if ( ! $token->test(Token::PUNCTUATION_TYPE, '[')) {
            $tokens = [];

            // Fast-forward until end-of-tag. And collect all tokens for re-injection.
            while ( ! $stream->test(Token::BLOCK_END_TYPE)) {
                $tokens[] = $stream->next();
            }
            // Remove the end-of-tag token at the end, to maintain correct token order in next step.
            //$eot = \array_pop($tokens);

            // Add required tokens "[" and "]" around the sequence, if they were omitted.
            $tokens = [
                new Token(Token::PUNCTUATION_TYPE, '[', -1),
                ...$tokens,
                new Token(Token::PUNCTUATION_TYPE, ']', -1),
                //$eot,
            ];

            // Re-inject the tokens for processing.
            $stream->injectTokens($tokens);
        }

        // Resolve and collect the raw tag values based on constant token expressions.
        $tags = \iterator_to_array(
            $this->resolveTags()
        );

        // Finish the tag, which never has a corresponding "endtag".
        $stream->expect(Token::BLOCK_END_TYPE);

        // Since there are no complex expressions allowed in tag nodes, there are no child nodes required.
        return new ContextTagNode($tags, $token->getLine());
    }

    public function getTag(): string
    {
        return 'tag';
    }

    /**
     * Parses the context tags sequence (`array`), and yield the raw string values.
     *
     * Since tags are static and (as of now) cannot be conditionally applied through complex expressions,
     *  the returned values will be simple string values.
     *
     * @return \Generator<array-key, string>
     *
     * @throws SyntaxError when the sequence contains invalid expressions
     */
    protected function resolveTags(): \Generator
    {
        $sequence = $this->parser->getExpressionParser()
            ->parseSequenceExpression();

        foreach ($sequence->getKeyValuePairs() as ['value' => $value]) {
            if ( ! $value instanceof ConstantExpression) {
                if ( ! $value instanceof AbstractExpression) {
                    throw new SyntaxError('Expected expression as tag value.', $value->getTemplateLine(),
                        $this->parser->getStream()
                            ->getSourceContext(),
                    );
                }

                throw new SyntaxError('Cannot use complex expression as tag value.', $value->getTemplateLine(),
                    $this->parser->getStream()
                        ->getSourceContext(),
                );
            }

            yield (string)$value->getAttribute('value');
        }
    }
}
