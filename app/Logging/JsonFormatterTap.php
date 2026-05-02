<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger as MonologLogger;

final class JsonFormatterTap
{
    public function __invoke(mixed $logger): void
    {
        $monolog = $this->resolveMonolog($logger);

        if (! $monolog instanceof MonologLogger) {
            return;
        }

        $formatter = new JsonFormatter(
            batchMode: JsonFormatter::BATCH_MODE_JSON,
            appendNewline: true
        );

        $formatter->includeStacktraces(false);

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter($formatter);
            }
        }
    }

    private function resolveMonolog(mixed $logger): ?MonologLogger
    {
        if ($logger instanceof IlluminateLogger) {
            if (method_exists($logger, 'getLogger')) {
                $inner = $logger->getLogger();

                return $inner instanceof MonologLogger ? $inner : null;
            }

            if (method_exists($logger, 'getMonolog')) {
                $inner = $logger->getMonolog();

                return $inner instanceof MonologLogger ? $inner : null;
            }
        }

        return $logger instanceof MonologLogger ? $logger : null;
    }
}
