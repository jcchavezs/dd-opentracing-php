<?php

namespace DdOpenTracing;

use Nanotime\Nanotime;
use OpenTracing\Exceptions\SpanAlreadyFinished;
use OpenTracing\Ext\Tags;
use OpenTracing\Log;
use OpenTracing\Span as OTSpan;
use DdTrace\Span as DdSpan;
use OpenTracing\SpanContext;
use OpenTracing\Tag;
use OpenTracing\Context as OTContext;
use TracingContext\TracingContext;

final class Span implements OTSpan
{
    const DEFAULT_SERVICE = '';
    const DEFAULT_RESOURCE = '';

    private $tracer;
    private $span;
    private $operationName;

    private function __construct(Tracer $tracer, DdSpan $span)
    {
        $this->tracer = $tracer;
        $this->span = $span;
    }

    public static function create(Tracer $tracer, $operationName = '', $service, $resource, $spanId, $traceId, $parentId = null)
    {
        $span = DdSpan::create($tracer->tracer(), $operationName, $service, $resource, $spanId, $traceId, $parentId);

        return new self($tracer, $span);
    }

    public static function createFromDdSpan(Tracer $tracer, DdSpan $span)
    {
        return new self($tracer, $span);
    }

    public function ddSpan()
    {
        return $this->span;
    }

    public function spanId()
    {
        return $this->span->spanId();
    }

    public function traceId()
    {
        return $this->span->traceId();
    }

    public function parentId()
    {
        return $this->span->parentId();
    }

    public function operationName()
    {
        return $this->operationName;
    }

    /** @return SpanContext */
    public function context()
    {
        $context = $this->span->injectIntoContext(TracingContext::create());

        return SpanContext::create(OTContext::create($context));
    }

    public function finish(Nanotime $finishTimestamp = null)
    {
        $this->checkIfSpanIsFinished();

        $this->span->finish();
    }

    public function overwriteOperationName($newOperationName)
    {
        self::validateOperationName($newOperationName);

        $this->checkIfSpanIsFinished();

        $this->operationName = $newOperationName;
    }

    public function setTag(Tag $tag)
    {
        $this->checkIfSpanIsFinished();

        if ($tag->is(Tags::PEER_SERVICE)) {
            $this->span->forService($tag->value());
        } else {
            $this->span->setMeta($tag->key(), $tag->value());
        }
    }

    public function logFields(Log ...$logs)
    {
        $this->checkIfSpanIsFinished();
    }

    public function setBaggageItem($key, $value)
    {
        $this->checkIfSpanIsFinished();
    }

    public function baggageItem($key)
    {
        $this->checkIfSpanIsFinished();
    }

    private static function validateOperationName($operationName)
    {
        if ($operationName == '') {
            throw new \InvalidArgumentException('Empty operation name.');
        }
    }

    private function checkIfSpanIsFinished()
    {
        if ($this->span->isFinished()) {
            throw SpanAlreadyFinished::create($this);
        }
    }
}
