<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

final class DataProviderTest extends FunctionalTestBase
{
    /** @var ParaTestInvoker */
    private $invoker;

    public function setUp(): void
    {
        parent::setUp();
        $this->invoker = new ParaTestInvoker($this->fixture('dataprovider-tests/DataProviderTest.php'));
    }

    public function testFunctionalMode(): void
    {
        $proc = $this->invoker->execute([
            'functional' => true,
            'max-batch-size' => 50,
        ]);
        static::assertMatchesRegularExpression('/OK \(1150 tests, 1150 assertions\)/', $proc->getOutput());
    }

    public function testNumericDataSetInFunctionalModeWithMethodFilter(): void
    {
        $proc = $this->invoker->execute([
            'functional' => true,
            'max-batch-size' => 50,
            'filter' => 'testNumericDataProvider50',
        ]);
        static::assertMatchesRegularExpression('/OK \(50 tests, 50 assertions\)/', $proc->getOutput());
    }

    public function testNumericDataSetInFunctionalModeWithCustomFilter(): void
    {
        $proc = $this->invoker->execute([
            'functional' => true,
            'max-batch-size' => 50,
            'filter' => 'testNumericDataProvider50.*1',
        ]);
        static::assertMatchesRegularExpression('/OK \(14 tests, 14 assertions\)/', $proc->getOutput());
    }

    public function testNamedDataSetInFunctionalModeWithMethodFilter(): void
    {
        $proc = $this->invoker->execute([
            'functional' => true,
            'max-batch-size' => 50,
            'filter' => 'testNamedDataProvider50',
        ]);
        static::assertMatchesRegularExpression('/OK \(50 tests, 50 assertions\)/', $proc->getOutput());
    }

    public function testNamedDataSetInFunctionalModeWithCustomFilter(): void
    {
        $proc = $this->invoker->execute([
            'functional' => true,
            'max-batch-size' => 50,
            'filter' => 'testNamedDataProvider50.*name_of_test_.*1',
        ]);
        static::assertMatchesRegularExpression('/OK \(14 tests, 14 assertions\)/', $proc->getOutput());
    }

    public function testNumericDataSet1000InFunctionalModeWithFilterAndMaxBatchSize(): void
    {
        $proc = $this->invoker->execute([
            'functional' => true,
            'max-batch-size' => 50,
            'filter' => 'testNumericDataProvider1000',
        ]);
        static::assertMatchesRegularExpression('/OK \(1000 tests, 1000 assertions\)/', $proc->getOutput());
    }
}
