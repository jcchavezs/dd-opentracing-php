<?php

namespace DdOpenTracing\Propagators;

use DdOpenTracing\Span;
use DdOpenTracing\Tracer;
use OpenTracing\Propagator;
use OpenTracing\Propagators\TextMapReader;
use OpenTracing\Propagators\TextMapWriter;
use OpenTracing\Span as OTSpan;
use OpenTracing\SpanContext;

final class TextMap implements Propagator
{
    const FIELD_TRACE_ID = "dd-trace-traceid";
    const FIELD_SPAN_ID = "dd-trace-spanid";
    const FIELD_PARENT_ID = "dd-trace-parentid";

    private $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    public function inject(OTSpan $span, TextMapWriter $carrier)
    {
        $carrier->set(self::FIELD_SPAN_ID, base_convert($span->spanId(), 10, 16));
        $carrier->set(self::FIELD_TRACE_ID, base_convert($span->traceId(), 10, 16));
        $carrier->set(self::FIELD_PARENT_ID, base_convert($span->parentId(), 10, 16));
    }

    /** @return SpanContext */
    public function extract(TextMapReader $carrier)
    {
        $spanId = null;
        $traceId = null;
        $parentId = null;

        $carrier->foreachKey(function($key, $value) use (&$spanId, &$traceId, &$parentId) {
            switch ($key) {
                case self::FIELD_TRACE_ID:
                    $traceId = base_convert($value, 16, 10);
                    break;
                case self::FIELD_SPAN_ID:
                    $spanId = base_convert($value, 16, 10);
                    break;
                case self::FIELD_PARENT_ID:
                    $parentId = base_convert($value, 16, 10);
                    break;
            }
        });

        $defaultService = "localhost";
        $defaultResource = "/";

        $span = Span::create($this->tracer, "", $defaultService, $defaultResource, $spanId, $traceId, $parentId);

        return $span->context();
    }
}
