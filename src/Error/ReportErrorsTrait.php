<?php

namespace Lexide\Syringe\Error;

use Lexide\Syringe\Exception\ConfigException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

trait ReportErrorsTrait
{

    protected ?LoggerInterface $errorLogger = null;

    /**
     * @param SyringeError[] $errors
     * @param bool $ignoreWarnings
     * @throws ConfigException
     */
    protected function reportErrors(array $errors, bool $ignoreWarnings): void
    {
        if (empty($errors)) {
            return;
        }

        if ($ignoreWarnings) {
            $errors = array_filter($errors, function ($error) {
                return $error->getType() != "warning";
            });
        }
        if (!empty($errors)) {
            if ($this->errorLogger instanceof LoggerInterface) {
                foreach ($errors as $error) {
                    $this->errorLogger->log(
                        $error->getType() == "warning" ? LogLevel::WARNING : LogLevel::ERROR,
                        $error->getMessage(),
                        $error->getContext()
                    );
                }
            }
            $errorCount = count($errors);
            $message = $errorCount == 1
                ? "Error: {$errors[0]->getMessage()} " . json_encode($errors[0]->getContext())
                : "There were $errorCount validation errors. See the log for more details";
            throw new ConfigException($message);
        }
    }

}