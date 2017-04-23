<?php

namespace DdOpenTracingTests\Unit;

use DdOpenTracing\Span;
use DdOpenTracing\Tracer;
use PHPUnit_Framework_TestCase;

final class SpanTest extends PHPUnit_Framework_TestCase
{
    public function testAnSpanIsCreatedWithExpectedValues()
    {
        $tracer = Tracer::noop();
        $operationName = 'test_operation';
        $service = '';
        $resource = '';
        $spanId = 123;
        $traceId = 456;
        $parentId = 789;

        $span = Span::create($tracer, $operationName, $service, $resource, $spanId, $traceId, $parentId);

        $this->assertEquals($operationName, $span->operationName());
        $this->assertEquals($spanId, $span->spanId());
        $this->assertEquals($traceId, $span->traceId());
        $this->assertEquals($parentId, $span->parentId());
    }
}
