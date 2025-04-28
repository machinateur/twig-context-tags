<?php
/*
 * MIT License
 *
 * Copyright (c) 2025 machinateur
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

namespace Machinateur\Twig\Tests\Extension;

use Machinateur\Twig\Extension\ContextTagExtension;
use Machinateur\Twig\TaggedTemplateInterface;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Template;
use Twig\TemplateWrapper;

/**
 * @covers \Machinateur\Twig\Extension\ContextTagExtension
 * @covers \Machinateur\Twig\Node\ContextTagNode
 * @covers \Machinateur\Twig\Node\CompiledContextTagsNode
 * @covers \Machinateur\Twig\NodeVisitor\ContextTagNodeVisitor
 * @covers \Machinateur\Twig\TokenParser\ContextTagTokenParser
 */
class ContextTokenExtensionTest extends TestCase
{
    public function testParseContextTags(): void
    {
        $environment = new Environment(
            new ArrayLoader([
                '_base.html.twig' => /** @lang HTML */ <<<'TWIG'
{%- tag 'base-title' -%}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{% block title %}{{ title }}{% endblock %}</title>
</head>
<body>
{% block content %}
{% endblock %}
</body>
</html>
TWIG,
                'test.html.twig' => /** @lang HTML */ <<<'TWIG'
{% extends '_base.html.twig' %}

{% tag ['content'] %}

{% block content %}
    <p>This test is tagged and uses inheritance.</p>
{% endblock %}

{# Add some more tags, just for testing. #}
{% tag 'some-context-tag', 'some-other-context-tag' %}
TWIG,
            ]),
            ['debug' => false],
        );
        $environment->addExtension(new ContextTagExtension());

        $template    = $environment->load('test.html.twig');
        self::assertInstanceOf(TemplateWrapper::class, $template);

        $rawTemplate = $template->unwrap();
        self::assertInstanceOf(Template::class, $rawTemplate);
        self::assertTrue(\method_exists($rawTemplate, 'getContextTags'));
        /** @var Template&TaggedTemplateInterface $rawTemplate */

        // Note: By default, the parent's tags are not included.
        $contextTags = $rawTemplate->getContextTags();
        self::assertSame(['content', 'some-context-tag', 'some-other-context-tag'], $contextTags);

        // Note: Parent always comes first.
        $contextTags = $rawTemplate->getContextTags(true);
        self::assertSame(['base-title', 'content', 'some-context-tag', 'some-other-context-tag'], $contextTags);

        $result         = $template->render([
            'title' => 'Hello world',
        ]);
        $expectedResult = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hello world</title>
</head>
<body>
    <p>This test is tagged and uses inheritance.</p>
</body>
</html>
HTML;
        self::assertSame($expectedResult, $result);
    }
}
