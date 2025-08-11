<?php

namespace App\Benchmarks;

interface BenchmarkInterface
{
    public function setUp(): void;
    public function tearDown(): void;
}
