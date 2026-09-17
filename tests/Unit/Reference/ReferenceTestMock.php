<?php

namespace Lexide\Syringe\Test\Unit\Reference;

use Lexide\Syringe\Tag\TagIterator;

class ReferenceTestMock
{

    protected const MY_CONST = "not accessible";

    public function usingUntyped($collection)
    {}

    public function usingMixed(mixed $collection)
    {}

    public function usingArray(array $collection)
    {}

    public function usingIterable(iterable $collection)
    {}

    public function usingIterator(\Iterator $collection)
    {}

    public function usingTraversable(\Traversable $collection)
    {}

    public function usingArrayAccess(\ArrayAccess $collection)
    {}

    public function usingTagIterator(TagIterator $collection)
    {}

    public function usingMultipleArgs(bool $foo, int $bar, array $baz, string $fiz)
    {}

    public function usingUnionType(string|int|iterable|bool $collection, string|int|null|bool $foo)
    {}
}