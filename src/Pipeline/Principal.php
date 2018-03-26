<?php
/*
 * Copyright 2017, 2018 Alexey Kopytko <alexey@kopytko.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Pipeline;

/**
 * Principal abstract pipeline with no defaults.
 *
 * You're not expected to use this class directly, but you may subclass it as you see fit.
 */
abstract class Principal implements Interfaces\Pipeline
{
    /**
     * Pre-primed pipeline.
     *
     * @var \Traversable
     */
    private $pipeline;

    /**
     * Optional source of data.
     *
     * @param \Traversable|null $input
     */
    public function __construct(\Traversable $input = null)
    {
        $this->pipeline = $input;
    }

    public function map(callable $func)
    {
        // If we know the callback is one of us, we can use a shortcut
        // This also allows inheriting classes to replace the pipeline
        if ($func instanceof self) {
            $this->pipeline = call_user_func($func);

            return $this;
        }

        if (!$this->pipeline) {
            $this->pipeline = call_user_func($func);

            // Not a generator means we were given a simple value to be treated as an array
            if (!($this->pipeline instanceof \Generator)) {
                // We do not cast to an array here because casting a null to an array results in
                // an empty array; that's surprising and not how map() works in other cases
                $this->pipeline = new \ArrayIterator([
                    $this->pipeline,
                ]);
            }

            return $this;
        }

        $this->pipeline = (static function ($previous) use ($func) {
            foreach ($previous as $value) {
                $result = $func($value);
                if ($result instanceof \Generator) {
                    yield from $result;
                } else {
                    // Case of a plain old mapping function
                    yield $result;
                }
            }
        })($this->pipeline);

        return $this;
    }

    public function filter(callable $func)
    {
        if ($this->pipeline) {
            $this->pipeline = new \CallbackFilterIterator($this->pipeline, $func);
        }

        return $this;
    }

    /**
     * @return \Traversable
     */
    public function getIterator()
    {
        return $this->pipeline;
    }

    public function toArray()
    {
        // Because `yield from` does not reset keys we have to ignore them on export to return every item.
        // http://php.net/manual/en/language.generators.syntax.php#control-structures.yield.from
        return iterator_to_array($this, false);
    }

    public function reduce(callable $func, $initial = null)
    {
        foreach ($this as $value) {
            $initial = $func($initial, $value);
        }

        return $initial;
    }

    public function __invoke()
    {
        yield from $this;
    }
}
