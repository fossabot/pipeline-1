<?php

namespace Pipeline;

/**
 * Not your general purpose pipeline
 */
class Generator implements \IteratorAggregate
{
    /**
     * Pre-primed pipeline
     * @var \Generator
     */
    private $pipeline;

    public function __construct(callable $func = null)
    {
        if ($func) {
            $this->pipeline = call_user_func($func);
        }
    }

    /**
     * @param callable $func - must yield values (return a generator)
     * @return Generator
     */
    public function map(callable $func)
    {
        if (!$this->pipeline) {
            $this->pipeline = call_user_func($func);
            return $this;
        }

        $previous = $this->pipeline;
        $this->pipeline = call_user_func(function () use ($previous, $func) {
            foreach ($previous as $value) {
                // `yield from` does not reset the keys
                // iterator_to_array() goes nuts
                // http://php.net/manual/en/language.generators.syntax.php#control-structures.yield.from
                foreach ($func($value) as $value) {
                    yield $value;
                }
            }
        });

        return $this;
    }

    /**
     * @param callable $func
     * @return Generator
     */
    public function filter(callable $func = null)
    {
        if (!$func) {
            // akin to default array_filter callback
            $func = function ($value) {
                return (bool) $value;
            };
        }

        $this->map(function ($value) use ($func) {
            if ($func($value)) {
                yield $value;
            }
        });

        return $this;
    }

    /**
     * @return \ArrayIterator|Generator
     */
    public function getIterator()
    {
        // with non-primed pipeline just return empty iterator
        if (!$this->pipeline) {
            return new \ArrayIterator([]);
        }

        return $this->pipeline;
    }

    /**
     * @param callable $func (mixed $carry, mixed $item)
     * @param mixed $initial
     * @return mixed
     */
    public function reduce(callable $func, $initial = null)
    {
        foreach ($this->pipeline as $value) {
            $initial = $func($initial, $value);
        }

        return $initial;
    }
}