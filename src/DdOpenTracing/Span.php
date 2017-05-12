<?php

namespace DdOpenTracing;

use Exception;
use InvalidArgumentException;
use Nanotime\Nanotime;
use OpenTracing\Exceptions\SpanAlreadyFinished;
use OpenTracing\Ext\Tags;
use OpenTracing\LogField;
use OpenTracing\Span as OTSpan;
use DdTrace\Span as DdSpan;
use OpenTracing\SpanContext;
use OpenTracing\Tag;
use OpenTracing\Context as OTContext;
use Throwable;
use TracingContext\TracingContext;

final class Span implements OTSpan
{
    const DEFAULT_SERVICE = '';
    const DEFAULT_RESOURCE = '';

    private $tracer;
    private $span;

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
        return $this->span->name();
    }

    /** @return SpanContext */
    public function context()
    {
        $context = $this->span->injectIntoContext(TracingContext::create());

        return SpanContext::create(OTContext::create($context));
    }

    public function finish(Nanotime $finishTimestamp = null, $logRecords = [])
    {
        $this->checkIfSpanIsFinished();

        $this->span->finish();
    }

    public function overwriteOperationName($newOperationName)
    {
        self::validateOperationName($newOperationName);

        $this->checkIfSpanIsFinished();

        $this->span->withName($newOperationName);
    }

    public function setTag(Tag $tag)
    {
        $this->checkIfSpanIsFinished();

        if ($tag->is(Tags::PEER_SERVICE)) {
            $this->span->forService($tag->value());
        } else if ($tag->is(Tags::ERROR)) {
            $this->setErrorTag($tag);
        } else {
            $this->span->setMeta($tag->key(), $tag->value());
        }
    }

    private function setErrorTag(Tag $tag)
    {
        $this->span->setMeta(Tags::ERROR, true);

        if ($tag->value() instanceof Throwable) {
            $this->span->setError($tag->value());
            return;
        }

        if ($tag->value() instanceof Exception) {
            $this->span->setError($tag->value());
            return;
        }

        $tagStrVal = strval($tag->value());

        if ($tagStrVal) {
            $this->span->setError(new Exception($tagStrVal));
        }
    }

    public function logFields(LogField ...$logs)
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
            throw new InvalidArgumentException('Empty operation name.');
        }
    }

    private function checkIfSpanIsFinished()
    {
        if ($this->span->isFinished()) {
            throw SpanAlreadyFinished::create($this);
        }
    }
}
