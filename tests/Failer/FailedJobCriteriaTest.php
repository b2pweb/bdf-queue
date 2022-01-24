<?php

namespace Failer;

use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobCriteria;
use PHPUnit\Framework\TestCase;

class FailedJobCriteriaTest extends TestCase
{
    public function test_empty()
    {
        $this->assertSame([], (new FailedJobCriteria())->toArray());
    }

    public function test_name()
    {
        $this->assertSame(['name' => ['=', 'foo']], (new FailedJobCriteria())->name('foo')->toArray());
        $this->assertSame(['name' => ['>', 'foo']], (new FailedJobCriteria())->name('foo', '>')->toArray());
        $this->assertSame(['name' => ['>', 'foo']], (new FailedJobCriteria())->name('> foo')->toArray());
        $this->assertSame(['name' => ['wildcard', 'f*f*f']], (new FailedJobCriteria())->name('f*f*f')->toArray());
    }

    public function test_connection()
    {
        $this->assertSame(['connection' => ['=', 'foo']], (new FailedJobCriteria())->connection('foo')->toArray());
        $this->assertSame(['connection' => ['=', 'f*f*f']], (new FailedJobCriteria())->connection('f*f*f')->toArray());
    }

    public function test_queue()
    {
        $this->assertSame(['queue' => ['=', 'foo']], (new FailedJobCriteria())->queue('foo')->toArray());
        $this->assertSame(['queue' => ['=', 'f*f*f']], (new FailedJobCriteria())->queue('f*f*f')->toArray());
    }

    public function test_error()
    {
        $this->assertSame(['error' => ['=', 'foo']], (new FailedJobCriteria())->error('foo')->toArray());
        $this->assertSame(['error' => ['>', 'foo']], (new FailedJobCriteria())->error('foo', '>')->toArray());
        $this->assertSame(['error' => ['>', 'foo']], (new FailedJobCriteria())->error('> foo')->toArray());
        $this->assertSame(['error' => ['wildcard', 'f*f*f']], (new FailedJobCriteria())->error('f*f*f')->toArray());
    }

    public function test_failedAt()
    {
        $this->assertEquals(['failedAt' => ['=', new \DateTime('2022-02-14')]], (new FailedJobCriteria())->failedAt('2022-02-14')->toArray());
        $this->assertEquals(['failedAt' => ['=', new \DateTime('2022-02-14')]], (new FailedJobCriteria())->failedAt(new \DateTime('2022-02-14'))->toArray());
        $this->assertEquals(['failedAt' => ['>', new \DateTime('2022-02-14')]], (new FailedJobCriteria())->failedAt('2022-02-14', '>')->toArray());
        $this->assertEquals(['failedAt' => ['>', new \DateTime('2022-02-14')]], (new FailedJobCriteria())->failedAt(new \DateTime('2022-02-14'), '>')->toArray());
        $this->assertEquals(['failedAt' => ['>', new \DateTime('2022-02-14')]], (new FailedJobCriteria())->failedAt('> 2022-02-14')->toArray());
        $this->assertSame(['failedAt' => ['wildcard', '2022-01-*']], (new FailedJobCriteria())->failedAt('2022-01-*')->toArray());
    }

    public function test_firstFailedAt()
    {
        $this->assertEquals(['firstFailedAt' => ['=', new \DateTime('2022-02-14')]], (new FailedJobCriteria())->firstFailedAt('2022-02-14')->toArray());
        $this->assertEquals(['firstFailedAt' => ['=', new \DateTime('2022-02-14')]], (new FailedJobCriteria())->firstFailedAt(new \DateTime('2022-02-14'))->toArray());
        $this->assertEquals(['firstFailedAt' => ['>', new \DateTime('2022-02-14')]], (new FailedJobCriteria())->firstFailedAt('2022-02-14', '>')->toArray());
        $this->assertEquals(['firstFailedAt' => ['>', new \DateTime('2022-02-14')]], (new FailedJobCriteria())->firstFailedAt(new \DateTime('2022-02-14'), '>')->toArray());
        $this->assertEquals(['firstFailedAt' => ['>', new \DateTime('2022-02-14')]], (new FailedJobCriteria())->firstFailedAt('> 2022-02-14')->toArray());
        $this->assertSame(['firstFailedAt' => ['wildcard', '2022-01-*']], (new FailedJobCriteria())->firstFailedAt('2022-01-*')->toArray());
    }

    public function test_attempts()
    {
        $this->assertSame(['attempts' => ['=', 42]], (new FailedJobCriteria())->attempts(42)->toArray());
        $this->assertSame(['attempts' => ['>', 42]], (new FailedJobCriteria())->attempts(42, '>')->toArray());
        $this->assertSame(['attempts' => ['>', 42]], (new FailedJobCriteria())->attempts('> 42')->toArray());
    }

    public function test_match_empty()
    {
        $criteria = new FailedJobCriteria();

        $this->assertTrue($criteria->match(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
        ])));
    }

    /**
     * @dataProvider provideSingleFilter
     */
    public function test_match_single_filter($filter, array $arguments, $match)
    {
        $criteria = new FailedJobCriteria();
        $criteria->$filter(...$arguments);

        $this->assertEquals($match, $criteria->match(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
            'name' => 'Foo',
            'error' => 'my error',
            'failedAt' => new \DateTime('2022-01-10 12:45:00'),
            'firstFailedAt' => new \DateTime('2022-01-08 08:30:00'),
            'attempts' => 4,
        ])));
    }

    public function test_match_multiple_filter()
    {
        $criteria = new FailedJobCriteria();
        $criteria
            ->name('f*')
            ->attempts('> 3')
        ;

        $this->assertTrue($criteria->match(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
            'name' => 'Foo',
            'error' => 'my error',
            'failedAt' => new \DateTime('2022-01-10 12:45:00'),
            'firstFailedAt' => new \DateTime('2022-01-08 08:30:00'),
            'attempts' => 4,
        ])));

        $this->assertFalse($criteria->match(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
            'name' => 'Bar',
            'error' => 'my error',
            'failedAt' => new \DateTime('2022-01-10 12:45:00'),
            'firstFailedAt' => new \DateTime('2022-01-08 08:30:00'),
            'attempts' => 4,
        ])));

        $this->assertFalse($criteria->match(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
            'name' => 'Foo',
            'error' => 'my error',
            'failedAt' => new \DateTime('2022-01-10 12:45:00'),
            'firstFailedAt' => new \DateTime('2022-01-08 08:30:00'),
            'attempts' => 2,
        ])));
    }

    public function provideSingleFilter()
    {
        return [
            ['name', ['foo'], true],
            ['name', ['bar'], false],
            ['name', ['F', '>'], true],
            ['name', ['F', '<'], false],
            ['name', ['> F'], true],
            ['name', ['f*'], true],
            ['name', ['b*'], false],
            ['connection', ['test'], true],
            ['connection', ['other'], false],
            ['queue', ['queue'], true],
            ['queue', ['other'], false],
            ['error', ['my error'], true],
            ['error', ['*error*'], true],
            ['error', ['*other*'], false],
            ['error', ['my', '>'], true],
            ['failedAt', [new \DateTime('2022-01-10 12:45:00')], true],
            ['failedAt', ['2022-01-10 12:45:00'], true],
            ['failedAt', ['2022-01-10 12:45:30'], false],
            ['failedAt', [new \DateTime('2022-01-10'), '>'], true],
            ['failedAt', [new \DateTime('2022-01-10'), '<'], false],
            ['failedAt', [new \DateTime('2022-02-01'), '<'], true],
            ['failedAt', ['> 2022-01-10'], true],
            ['failedAt', ['< 2022-01-10'], false],
            ['failedAt', ['2022-01-*'], true],
            ['failedAt', ['2021-01-*'], false],
            ['firstFailedAt', [new \DateTime('2022-01-08 08:30:00')], true],
            ['firstFailedAt', ['2022-01-08 08:30:00'], true],
            ['firstFailedAt', ['2022-01-10 12:45:30'], false],
            ['firstFailedAt', [new \DateTime('2022-01-08'), '>'], true],
            ['firstFailedAt', [new \DateTime('2022-01-08'), '<'], false],
            ['firstFailedAt', [new \DateTime('2022-02-01'), '<'], true],
            ['firstFailedAt', ['> 2022-01-08'], true],
            ['firstFailedAt', ['< 2022-01-08'], false],
            ['firstFailedAt', ['2022-01-*'], true],
            ['firstFailedAt', ['2021-01-*'], false],
            ['attempts', [4], true],
            ['attempts', [5], false],
            ['attempts', [3, '>'], true],
            ['attempts', [4, '>'], false],
            ['attempts', [5, '<'], true],
            ['attempts', ['< 5'], true],
            ['attempts', ['> 5'], false],
        ];
    }

    /**
     * @dataProvider provideOperators
     */
    public function test_match_operators(int $value, string $operator, bool $match)
    {
        $criteria = new FailedJobCriteria();
        $criteria->attempts($value, $operator);

        $this->assertEquals($match, $criteria->match(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
            'name' => 'Foo',
            'error' => 'my error',
            'failedAt' => new \DateTime('2022-01-10 12:45:00'),
            'firstFailedAt' => new \DateTime('2022-01-08 08:30:00'),
            'attempts' => 4,
        ])));
    }

    public function provideOperators()
    {
        return [
            [4, '=', true],
            [4, '>=', true],
            [4, '<=', true],
            [4, '>', false],
            [4, '<', false],

            [3, '=', false],
            [3, '>=', true],
            [3, '<=', false],
            [3, '>', true],
            [3, '<', false],

            [5, '=', false],
            [5, '>=', false],
            [5, '<=', true],
            [5, '>', false],
            [5, '<', true],
        ];
    }

    public function test_apply()
    {
        $criteria = new FailedJobCriteria();
        $criteria
            ->name('f*')
            ->attempts('> 3')
        ;

        $calls = [];
        $criteria->apply(function (...$args) use(&$calls) { $calls[] = $args; });

        $this->assertSame([
            ['name', 'wildcard', 'f*'],
            ['attempts', '>', 3],
        ], $calls);
    }

    public function test_match_invalid_operator()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator invalid');

        $criteria = new FailedJobCriteria();
        $criteria->attempts(4, 'invalid');

        $criteria->match(new FailedJob([
            'connection' => 'test',
            'queue' => 'queue',
            'messageContent' => ['job' => 'showCommand@test'],
            'name' => 'Foo',
            'error' => 'my error',
            'failedAt' => new \DateTime('2022-01-10 12:45:00'),
            'firstFailedAt' => new \DateTime('2022-01-08 08:30:00'),
            'attempts' => 4,
        ]));
    }
}
