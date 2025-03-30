# Twig Context Tags

A [twig](https://github.com/twig/twig) extension that allows the following language construct in twig templates:

```
{% tag 'some-context-tag', 'some-other-context-tag' %}
```

This can be used to collect all tags defined when parsing and saving them to a custom `Template` method at compile-time,
 which in turn makes it possible to predict which context is required for the respective template at runtime,
 before rendering the actual contents.

This especially helps in a `1:1` route to template-file kinds of situations.

## Requirements

- PHP  `>=8.1.0`
- Twig `^3.15`

## Installation

Use composer to install this twig extension.

```bash
composer require machinateur/twig-context-tags
```

## Usage

This twig extension allows for defining certain context tags in template code, which will be evaluated at compile-time,
 but is available at runtime *before* actually rendering the content.

This mechanic allows for quite creative implementations, especially when combined with a content resolution framework.

The tags accept a wrapped or unwrapped sequence (i.e. with/without `[]`). Use it like this:

```twig
{% tag 'some-context-tag', 'some-other-context-tag' %}
{% tag ['the-third-tag'] %}
```

### Limitations

All tag values have to be constant expressions. This is inherent to how the extension works under the hood.

### Example

```php
$environment = new \Twig\Environment(/*...*/);
$environment->addExtension(new \Machinateur\Twig\Extension\ContextTagExtension());
/** @var \Twig\Template&\Machinateur\Twig\TaggedTemplateInterface $template */
$template    = $environment->load($view)
    ->unwrap();

$tags        = $template->getContextTags();

dump($tags);
```

This will output something like this:

```php
Array (
  0 => 'some-context-tag',
  1 => 'some-other-context-tag',
  2 => 'the-third-tag',
)
```

Tags are ordered by count and sequence of occurrence.

Then, after processing, to render the template:

```php
echo $template->render([
    // ...
]);
```

### Template inheritance

The extension also supports collecting tags across template inheritance boundaries,
 similar to how the `set` tag works.
The parent tags will be prepended, if any.

The feature is turned off by default, but by passing `true` to `getContextTags()` it can be activated.

```php
$tags        = $template->getContextTags(true);
// ...
```

### Note on potential abstractions

This pairs well with events, for example through [Symfony's EventDispatcher component](https://symfony.com/doc/current/components/event_dispatcher.html)
 in order to resolve the specific context entries by tag, before passing it to `render($context)`.

In such a setup, the responsibility to define context is shifted to the template designer and domain.
 At least as long as the underlying PHP application code can handle this kind of "dynamic context" resolution.

## Tests

Run tests using the composer script:

```bash
composer test
```

## License

It's MIT.
