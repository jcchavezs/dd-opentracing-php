<?php

namespace DdOpenTracing;

use DdOpenTracing\Propagators\TextMap;
use DdTrace\Context;
use DdTrace\Tracer as DdTracer;
use OpenTracing\Exceptions\UnknownSpanReferenceType;
use OpenTracing\Exceptions\UnsupportedFormat;
use OpenTracing\Propagator;
use OpenTracing\Propagators\TextMapReader;
use OpenTracing\Propagators\TextMapWriter;
use OpenTracing\SpanContext;
use OpenTracing\SpanReference;
use OpenTracing\Tag;
use OpenTracing\Tracer as OTTracer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class Tracer implements OTTracer
{
    const DEFAULT_SERVICE = "";
    const DEFAULT_RESOURCE = "";

    private $tracer;
    private $logger;
    private $propagator;

    public function __construct(
        DdTracer $tracer,
        LoggerInterface $logger
    ) {
        $this->tracer = $tracer;
        $this->logger = $logger;
        $this->propagator = new TextMap($this);
    }

    public static function noop()
    {
        return new self(DdTracer::noop(), new NullLogger);
    }

    public function inject(SpanContext $spanContext, $format, TextMapWriter $carrier)
    {
        $ddSpan = Context::spanFromContext($spanContext->context());

        switch ($format) {
            case Propagator::HTTP_HEADERS:
                $this->propagator->inject(Span::createFromDdSpan($this, $ddSpan), $carrier);
                break;
            default:
                throw UnsupportedFormat::withFormat($format);
                break;
        }
    }

    /** @return SpanContext */
    public function extract($format, TextMapReader $carrier)
    {
        switch ($format) {
            case Propagator::HTTP_HEADERS:
                return $this->propagator->extract($carrier);
                break;
            default:
                throw UnsupportedFormat::withFormat($format);
                break;
        }
    }

    /** @return DdTracer */
    public function tracer()
    {
        return $this->tracer;
    }

    public function enableDebugLogging()
    {
        $this->tracer->enableDebugLogging();
    }

    public function disableDebugLogging()
    {
        $this->tracer->disableDebugLogging();
    }

    public function startSpan($operationName = '', SpanReference $parentReference = null, $startTimestamp = null, Tag ...$tags)
    {
        if ($startTimestamp !== null) {
            $this->logger->debug('startTimestamp parameter is supported by OpenTracing but not by DataDog Trace, it will be ignored.');
        }

        if ($parentReference === null) {
            return $this->newRootSpan($operationName, $tags);
        } else if ($parentReference->isTypeChildOf()) {
            return $this->newChildSpanFromContext($operationName, $parentReference->referencedContext(), $tags);
        }

        throw UnknownSpanReferenceType::create($parentReference);
    }

    private function newRootSpan($operationName = '', array $tags)
    {
        $span = Span::createFromDdSpan(
            $this,
            $this->tracer->createRootSpan($operationName, self::DEFAULT_SERVICE, self::DEFAULT_RESOURCE)
        );

        if (empty($tags)) {
            return $span;
        }

        array_walk($tags, (function(Tag $tag) use ($span) {
           $span->setTag($tag);
        }));

        return $span;
    }

    private function newChildSpanFromContext($operationName = '', SpanContext $parentContext, array $tags)
    {
        $span = Context::spanFromContext($parentContext->context());

        $span = Span::createFromDdSpan(
            $this,
            $this->tracer->createChildSpan($operationName, $span)
        );

        if (empty($tags)) {
            return $span;
        }

        array_walk($tags, (function(Tag $tag) use ($span) {
            $span->setTag($tag);
        }));

        return $span;
    }
}
