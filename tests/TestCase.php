<?php

namespace Sofa\Eloquence\Tests;

use Mockery;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * A query grammar. Since Laravel 12 a grammar takes the connection it quotes for; a partial mock is enough here.
     *
     * @param  string $class
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function newGrammar($class)
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();

        if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            return new $class(Mockery::mock(\Illuminate\Database\Connection::class)->makePartial());
        }

        return new $class;
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
    }
}
