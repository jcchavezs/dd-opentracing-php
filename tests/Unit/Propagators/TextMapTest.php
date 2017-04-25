<?php

namespace DdOpenTracingTests\Unit\Propagators;

use DdOpenTracing\Propagators\TextMap;
use DdOpenTracing\Tracer;
use DdTrace\Span as DdSpan;
use OpenTracing\Carriers\HttpHeaders;
use OpenTracing\Exceptions\SpanContextNotFound;
use OpenTracing\SpanContext;
use PHPUnit_Framework_TestCase;

final class TextMapTest extends PHPUnit_Framework_TestCase
{
    const FIELD_TRACE_ID = "dd-trace-traceid";
    const FIELD_SPAN_ID = "dd-trace-spanid";
    const DATA_DOG_TRACE_SPAN = 'datadog_trace_span';
    const TEST_SPAN_ID = 2;
    const TEST_TRACE_ID = 1;

    private $carrier;

    /** @var SpanContext */
    private $spanContext;

    public function testExtractFailsWhenHeadersAreNotPresent()
    {
        $this->givenACarrierWithNoHeaders();
        $this->thenASpanContextNotFoundExceptionIsThrown();
        $this->whenExtractingTheSpanContext();
    }

    public function testExtractSuccessWhenHeadersArePresent()
    {
        $this->givenACarrierWithExpectedHeader();
        $this->whenExtractingTheSpanContext();
        $this->thenTheExtractedSpanContextIsTheExpected();

    }

    private function givenACarrierWithNoHeaders()
    {
        $this->carrier = HttpHeaders::withHeaders([]);
    }

    private function givenACarrierWithExpectedHeader()
    {
        $this->carrier = HttpHeaders::withHeaders([
            self::FIELD_TRACE_ID => self::TEST_TRACE_ID,
            self::FIELD_SPAN_ID => self::TEST_SPAN_ID
        ]);
    }

    private function thenASpanContextNotFoundExceptionIsThrown()
    {
        $this->expectException(SpanContextNotFound::class);
    }

    private function whenExtractingTheSpanContext()
    {
        $textMap = new TextMap(Tracer::noop());
        $this->spanContext = $textMap->extract($this->carrier);
    }

    private function thenTheExtractedSpanContextIsTheExpected()
    {
        /** @var DdSpan $ddSpan */
        $ddSpan = $this->spanContext->context()->value(self::DATA_DOG_TRACE_SPAN);
        $this->assertEquals(self::TEST_SPAN_ID, $ddSpan->spanId());
    }
}
